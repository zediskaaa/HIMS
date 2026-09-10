<?php

namespace App\Services\Inventory;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\MaterialRequisition;
use App\Models\MaterialRequisitionLine;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryAutomationService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssuanceEngine
{
    public function __construct(
        private readonly InventoryAutomationService $automationService,
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Create a new store requisition for a hospital department.
     *
     * @param  array<string, mixed>  $data
     */
    public function createRequisition(array $data, User $requester): MaterialRequisition
    {
        return DB::transaction(function () use ($data, $requester) {
            $reqNumber = self::generateRequisitionNumber();

            $requisition = MaterialRequisition::create([
                'requisition_number' => $reqNumber,
                'requesting_user_id' => $requester->id,
                'department' => $data['department'] ?? $requester->department ?? 'General Clinic',
                'cost_center_id' => $data['cost_center_id'] ?? null,
                'required_date' => isset($data['required_date']) ? Carbon::parse($data['required_date']) : now()->addDays(2),
                'status' => 'pending_approval',
                'urgency' => $data['urgency'] ?? 'routine',
                'justification' => $data['justification'] ?? null,
            ]);

            $lines = $data['lines'] ?? [];
            if (empty($lines)) {
                throw ValidationException::withMessages([
                    'lines' => ['Requisition must contain at least one item line.']
                ]);
            }

            foreach ($lines as $lineData) {
                $item = InventoryItem::findOrFail($lineData['item_id']);
                $qty = (int) $lineData['requested_quantity'];

                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        'lines' => ["Requested quantity for {$item->name} must be greater than zero."]
                    ]);
                }

                MaterialRequisitionLine::create([
                    'material_requisition_id' => $requisition->id,
                    'item_id' => $item->id,
                    'requested_quantity' => $qty,
                    'reserved_quantity' => 0,
                    'issued_quantity' => 0,
                    'allocation_strategy' => $lineData['allocation_strategy'] ?? ($item->is_batch_tracked ? 'FEFO' : 'FIFO'),
                    'line_status' => 'pending',
                    'notes' => $lineData['notes'] ?? null,
                ]);
            }

            $this->auditLogger->record(
                AuditAction::CreatedMaterialRequisition,
                actor: $requester,
                target: $requisition,
                description: "Submitted Store Requisition {$requisition->requisition_number} for {$requisition->department}",
                newValues: [
                    'requisition_number' => $requisition->requisition_number,
                    'line_count' => count($lines),
                ]
            );

            return $requisition;
        });
    }

    /**
     * Approve a store requisition and place hard reservation on ATP stock.
     */
    public function approveRequisition(MaterialRequisition $requisition, User $approver): MaterialRequisition
    {
        return DB::transaction(function () use ($requisition, $approver) {
            $req = MaterialRequisition::lockForUpdate()->with('lines.item')->findOrFail($requisition->id);

            // Segregation of Duties: Requester cannot approve their own requisition
            if ($req->requesting_user_id === $approver->id) {
                throw new DomainException('Segregation of Duties Violation: You cannot approve your own store requisition.');
            }

            if ($req->status !== 'pending_approval') {
                throw new DomainException("Cannot approve requisition in status {$req->status}.");
            }

            foreach ($req->lines as $line) {
                $item = InventoryItem::lockForUpdate()->findOrFail($line->item_id);
                $requestedQty = $line->requested_quantity;

                // Check Available-to-Promise (ATP)
                if ($item->availableQuantity() < $requestedQty) {
                    throw ValidationException::withMessages([
                        'inventory' => ["Insufficient ATP stock for {$item->name}. Available: {$item->availableQuantity()}, Requested: {$requestedQty}."]
                    ]);
                }

                // Place hard reservation on the primary stock location or default location
                $locId = $item->default_location_id ?? StorageLocation::where('status', 'active')->value('id');
                if (!$locId) {
                    throw new DomainException("No active storage location found for item {$item->name}.");
                }

                $this->automationService->reserveStock($item->id, $locId, null, $requestedQty);

                $line->reserved_quantity = $requestedQty;
                $line->storage_location_id = $locId;
                $line->line_status = 'reserved';
                $line->save();
            }

            $req->status = 'approved';
            $req->approved_by_id = $approver->id;
            $req->approved_at = now();
            $req->save();

            $this->auditLogger->record(
                AuditAction::ApprovedMaterialRequisition,
                actor: $approver,
                target: $req,
                description: "Approved Store Requisition {$req->requisition_number} and reserved ATP stock",
                newValues: [
                    'requisition_number' => $req->requisition_number,
                    'approved_at' => now()->toIso8601String(),
                ]
            );

            return $req;
        });
    }

    /**
     * Generate an atomic, collision-safe reference number for store requisitions.
     */
    public static function generateRequisitionNumber(): string
    {
        $prefix = 'MR-' . now()->format('Ymd') . '-';
        $countToday = MaterialRequisition::where('requisition_number', 'like', "{$prefix}%")->count();
        $seq = $countToday + 1;
        do {
            $candidate = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while (MaterialRequisition::where('requisition_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * Reject a store requisition with an explicit clinical or administrative justification.
     */
    public function rejectRequisition(MaterialRequisition $requisition, User $approver, string $reason): MaterialRequisition
    {
        return DB::transaction(function () use ($requisition, $approver, $reason) {
            $req = MaterialRequisition::lockForUpdate()->findOrFail($requisition->id);

            // Segregation of Duties: Requester cannot review or reject their own requisition
            if ($req->requesting_user_id === $approver->id) {
                throw new DomainException('Segregation of Duties Violation: You cannot review or reject your own store requisition.');
            }

            if (!in_array($req->status, ['pending_approval', 'submitted'], true)) {
                throw new DomainException("Cannot reject requisition in status {$req->status}.");
            }

            $req->status = 'rejected';
            $req->approved_by_id = $approver->id;
            $req->rejection_reason = $reason;
            $req->save();

            $this->auditLogger->record(
                AuditAction::RejectedMaterialRequisition,
                actor: $approver,
                target: $req,
                description: "Rejected Store Requisition {$req->requisition_number}: {$reason}",
                newValues: [
                    'requisition_number' => $req->requisition_number,
                    'rejection_reason' => $reason,
                ]
            );

            return $req;
        });
    }

    /**
     * Cancel a store requisition and release any reserved ATP stock.
     */
    public function cancelRequisition(MaterialRequisition $requisition, User $actor, ?string $reason = null): MaterialRequisition
    {
        return DB::transaction(function () use ($requisition, $actor, $reason) {
            $req = MaterialRequisition::lockForUpdate()->with('lines.item')->findOrFail($requisition->id);

            if (in_array($req->status, ['issued', 'acknowledged', 'cancelled'], true)) {
                throw new DomainException("Cannot cancel requisition already in status {$req->status}.");
            }

            // If requisition was approved, release any placed ATP reservations
            if ($req->status === 'approved') {
                foreach ($req->lines as $line) {
                    if ($line->reserved_quantity > 0 && $line->storage_location_id) {
                        $this->automationService->releaseReservation(
                            $line->item_id,
                            $line->storage_location_id,
                            $line->item_batch_id,
                            $line->reserved_quantity
                        );
                        $line->reserved_quantity = 0;
                        $line->line_status = 'cancelled';
                        $line->save();
                    }
                }
            }

            $req->status = 'cancelled';
            $req->rejection_reason = $reason ?? 'Cancelled by user';
            $req->save();

            $this->auditLogger->record(
                AuditAction::CancelledMaterialRequisition,
                actor: $actor,
                target: $req,
                description: "Cancelled Store Requisition {$req->requisition_number}",
                newValues: [
                    'requisition_number' => $req->requisition_number,
                    'reason' => $reason,
                ]
            );

            return $req;
        });
    }

    /**
     * Algorithmic FEFO pick list generation for an approved requisition.
     *
     * @return array<int, array<string, mixed>>
     */
    public function generatePickList(MaterialRequisition $requisition): array
    {
        $pickList = [];

        foreach ($requisition->lines as $line) {
            $item = $line->item;
            $remaining = $line->requested_quantity - $line->issued_quantity;

            if ($remaining <= 0) {
                continue;
            }

            // Find stock levels ordered by FEFO (earliest expiry first, null last)
            $stockLevels = ItemStockLevel::query()
                ->where('item_stock_levels.item_id', $item->id)
                ->where('item_stock_levels.quantity', '>', 0)
                ->with(['batch', 'location'])
                ->leftJoin('item_batches', 'item_stock_levels.item_batch_id', '=', 'item_batches.id')
                ->orderByRaw('item_batches.expiry_date is null')
                ->orderBy('item_batches.expiry_date')
                ->orderBy('item_stock_levels.id')
                ->select('item_stock_levels.*')
                ->get();

            $allocatedForLine = [];
            foreach ($stockLevels as $level) {
                if ($remaining <= 0) {
                    break;
                }

                // Never pick expired batches
                if ($level->batch && $level->batch->isExpired()) {
                    continue;
                }

                $available = (int) $level->quantity;
                $take = min($remaining, $available);

                $allocatedForLine[] = [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'sku' => $item->sku,
                    'location_id' => $level->storage_location_id,
                    'location_name' => $level->location->fullPath(),
                    'batch_id' => $level->item_batch_id,
                    'batch_number' => $level->batch?->batch_number ?? 'N/A',
                    'expiry_date' => $level->batch?->expiry_date?->toDateString() ?? 'N/A',
                    'quantity_to_pick' => $take,
                    'pick_quantity' => $take,
                ];

                $remaining -= $take;
            }

            $pickList[$line->id] = $allocatedForLine;
        }

        return $pickList;
    }

    /**
     * Atomically issue goods against an approved store requisition.
     *
     * @param  array<string, mixed>  $issueData
     */
    public function issueRequisition(MaterialRequisition $requisition, array $issueData, User $picker): MaterialRequisition
    {
        return DB::transaction(function () use ($requisition, $issueData, $picker) {
            $req = MaterialRequisition::lockForUpdate()->with('lines.item')->findOrFail($requisition->id);

            if (!in_array($req->status, ['approved', 'picking'], true)) {
                throw new DomainException("Cannot issue requisition in status {$req->status}.");
            }

            $linesInput = $issueData['lines'] ?? [];

            foreach ($req->lines as $line) {
                $lineInput = collect($linesInput)->firstWhere('line_id', $line->id) ?? [];
                $issueQty = isset($lineInput['quantity']) ? (int) $lineInput['quantity'] : ($line->requested_quantity - $line->issued_quantity);

                if ($issueQty <= 0) {
                    continue;
                }

                $item = InventoryItem::lockForUpdate()->findOrFail($line->item_id);
                $locId = $lineInput['location_id'] ?? $line->storage_location_id ?? $item->default_location_id;
                $batchId = $lineInput['batch_id'] ?? $line->item_batch_id;

                // Release reserved quantity first
                $reservedToRelease = min($line->reserved_quantity, $issueQty);
                if ($reservedToRelease > 0) {
                    $this->automationService->releaseReservation($item->id, (int) $locId, null, $reservedToRelease);
                    $line->reserved_quantity -= $reservedToRelease;
                }

                // Execute physical stock out / issuance movement
                $this->automationService->recordMovement([
                    'item_id' => $item->id,
                    'movement_type' => MovementType::Issuance,
                    'quantity' => $issueQty,
                    'from_location_id' => $locId,
                    'item_batch_id' => $batchId,
                    'remarks' => "Store Issuance for Requisition {$req->requisition_number} to {$req->department}",
                ], $picker->id, $req);

                $line->issued_quantity += $issueQty;
                if ($line->issued_quantity >= $line->requested_quantity) {
                    $line->line_status = 'issued';
                } else {
                    $line->line_status = 'partially_issued';
                }
                $line->save();
            }

            // Determine if completely issued
            $allIssued = $req->lines->every(fn ($l) => $l->issued_quantity >= $l->requested_quantity);
            $req->status = $allIssued ? 'issued' : 'picking';
            $req->save();

            $this->auditLogger->record(
                AuditAction::IssuedMaterialRequisition,
                actor: $picker,
                target: $req,
                description: "Issued goods for Store Requisition {$req->requisition_number} to {$req->department}",
                newValues: [
                    'requisition_number' => $req->requisition_number,
                    'status' => $req->status,
                ]
            );

            return $req;
        });
    }

    /**
     * Department handover confirmation and recipient acknowledgment.
     */
    public function acknowledgeHandover(MaterialRequisition $requisition, User $recipient, ?string $notes = null): MaterialRequisition
    {
        return DB::transaction(function () use ($requisition, $recipient, $notes) {
            $req = MaterialRequisition::lockForUpdate()->findOrFail($requisition->id);
            $req->status = 'acknowledged';
            $req->acknowledged_at = now();
            $req->save();

            $this->auditLogger->record(
                AuditAction::AcknowledgedMaterialIssuance,
                actor: $recipient,
                target: $req,
                description: "Recipient {$recipient->name} ({$req->department}) acknowledged handover of Store Requisition {$req->requisition_number}",
                newValues: [
                    'requisition_number' => $req->requisition_number,
                    'recipient' => $recipient->name,
                    'notes' => $notes,
                ]
            );

            return $req;
        });
    }
}
