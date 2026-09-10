<?php

namespace App\Services\Inventory;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\QualityInspection;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\InventorySerial;
use App\Services\AuditLogger;
use App\Services\InventoryAutomationService;
use App\Services\Warehouse\LocationCompatibilityService;
use App\Services\Warehouse\WarehouseTaskService;
use App\Enums\WarehouseTaskType;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QualityControlService
{
    public function __construct(
        private readonly InventoryAutomationService $automationService,
        private readonly AuditLogger $auditLogger,
        private readonly LocationCompatibilityService $compatibilityService,
        private readonly WarehouseTaskService $taskService,
    ) {}

    /**
     * Release accepted quantity from Quarantine into unrestricted stock at the target location.
     */
    public function releaseLot(
        QualityInspection $inspection,
        int $acceptedQuantity,
        int $targetLocationId,
        User $inspector,
        ?string $findings = null
    ): QualityInspection {
        return DB::transaction(function () use ($inspection, $acceptedQuantity, $targetLocationId, $inspector, $findings) {
            $qi = QualityInspection::lockForUpdate()->with(['grnLine.goodsReceiptNote', 'item', 'batch'])->findOrFail($inspection->id);

            if ($qi->inspection_status === 'approved') {
                throw new DomainException("Inspection #{$qi->id} has already been approved.");
            }

            $grnLine = $qi->grnLine;
            $item = InventoryItem::lockForUpdate()->findOrFail($qi->item_id);
            $quarantineLocId = $grnLine->destination_location_id;

            if ($acceptedQuantity <= 0 || $acceptedQuantity > $grnLine->quarantined_quantity) {
                throw ValidationException::withMessages([
                    'accepted_quantity' => ["Accepted quantity ({$acceptedQuantity}) exceeds available quarantined quantity ({$grnLine->quarantined_quantity})."]
                ]);
            }

            $targetLocation = StorageLocation::findOrFail($targetLocationId);
            $this->compatibilityService->assertCompatible($targetLocation, $item, $acceptedQuantity);
            $receivingStaging = StorageLocation::query()
                ->active()
                ->where('is_receiving_staging', true)
                ->first() ?? $targetLocation;

            // 1. Decrement quarantined balance
            $this->automationService->adjustQuarantinedStock($item->id, $quarantineLocId, $qi->item_batch_id, -$acceptedQuantity);

            // 2. Release into receiving staging. Physical put-away happens only
            // after the generated task is scan-validated and completed.
            $this->automationService->adjustStockLevel($item->id, $receivingStaging->id, $qi->item_batch_id, $acceptedQuantity);

            // 3. Flip batch to active if batch exists
            if ($qi->item_batch_id) {
                $batch = ItemBatch::find($qi->item_batch_id);
                if ($batch) {
                    $batch->status = 'active';
                    $batch->save();
                }
            }

            // 4. Record QualityRelease movement in immutable ledger
            StockMovement::create([
                'item_id' => $item->id,
                'item_batch_id' => $qi->item_batch_id,
                'movement_type' => MovementType::QualityRelease,
                'quantity' => $acceptedQuantity,
                'unit_cost' => $grnLine->unit_cost ?? $item->unit_cost,
                'from_location_id' => $quarantineLocId,
                'to_location_id' => $receivingStaging->id,
                'reference_type' => QualityInspection::class,
                'reference_id' => $qi->id,
                'remarks' => "QC Release by {$inspector->name}. Findings: " . ($findings ?? 'Conforms to standards'),
                'moved_at' => now(),
                'user_id' => $inspector->id,
            ]);

            // 5. Update inspection and GRN line records
            $qi->inspection_status = 'approved';
            $qi->accepted_quantity = $acceptedQuantity;
            $qi->findings = $findings ?? 'Approved and released to unrestricted inventory.';
            $qi->inspected_by_id = $inspector->id;
            $qi->inspection_date = now();
            $qi->save();

            $grnLine->accepted_quantity += $acceptedQuantity;
            $grnLine->quarantined_quantity -= $acceptedQuantity;
            if ($grnLine->quarantined_quantity <= 0) {
                $grnLine->status = 'accepted';
            }
            $grnLine->save();

            // 6. Sync item totals and alerts
            $this->automationService->syncItemTotals($item);

            if ($receivingStaging->id !== $targetLocation->id) {
                $this->taskService->create([
                    'task_type' => WarehouseTaskType::PutAway,
                    'priority' => 'high',
                    'source_location_id' => $receivingStaging->id,
                    'destination_location_id' => $targetLocation->id,
                    'item_id' => $item->id,
                    'item_batch_id' => $qi->item_batch_id,
                    'requested_quantity' => $acceptedQuantity,
                    'idempotency_key' => "put-away-for-inspection-{$qi->id}",
                    'due_at' => now()->addHours(4),
                    'recommendation_reason' => 'QC-approved stock routed from receiving staging to a compatible active location.',
                ], $inspector, $qi);
            }

            InventorySerial::query()
                ->where('source_type', $grnLine->getMorphClass())
                ->where('source_id', $grnLine->id)
                ->update([
                    'storage_location_id' => $receivingStaging->id,
                    'status' => 'available',
                ]);

            $this->auditLogger->record(
                AuditAction::ReleasedQuarantineStock,
                actor: $inspector,
                target: $qi,
                description: "Released {$acceptedQuantity} units of {$item->name} from quarantine to receiving staging",
                newValues: [
                    'inspection_id' => $qi->id,
                    'item_id' => $item->id,
                    'accepted_quantity' => $acceptedQuantity,
                    'staging_location' => $receivingStaging->name,
                    'recommended_destination' => $targetLocation->name,
                ]
            );

            return $qi;
        });
    }

    /**
     * Reject non-conforming or damaged stock in Quarantine and move to Blocked status.
     */
    public function rejectLot(
        QualityInspection $inspection,
        int $rejectedQuantity,
        string $rejectionReason,
        User $inspector
    ): QualityInspection {
        return DB::transaction(function () use ($inspection, $rejectedQuantity, $rejectionReason, $inspector) {
            $qi = QualityInspection::lockForUpdate()->with(['grnLine.goodsReceiptNote', 'item', 'batch'])->findOrFail($inspection->id);

            if ($qi->inspection_status === 'rejected') {
                throw new DomainException("Inspection #{$qi->id} has already been rejected.");
            }

            $grnLine = $qi->grnLine;
            $item = InventoryItem::lockForUpdate()->findOrFail($qi->item_id);
            $quarantineLocId = $grnLine->destination_location_id;

            if ($rejectedQuantity <= 0 || $rejectedQuantity > $grnLine->quarantined_quantity) {
                throw ValidationException::withMessages([
                    'rejected_quantity' => ["Rejected quantity ({$rejectedQuantity}) exceeds available quarantined quantity ({$grnLine->quarantined_quantity})."]
                ]);
            }

            // 1. Decrement quarantined balance
            $this->automationService->adjustQuarantinedStock($item->id, $quarantineLocId, $qi->item_batch_id, -$rejectedQuantity);

            // 2. Increment blocked balance
            $this->automationService->adjustBlockedStock($item->id, $quarantineLocId, $qi->item_batch_id, $rejectedQuantity);

            // 3. Mark batch as rejected if batch exists
            if ($qi->item_batch_id) {
                $batch = ItemBatch::find($qi->item_batch_id);
                if ($batch) {
                    $batch->status = 'rejected';
                    $batch->notes = ($batch->notes ? $batch->notes . ' | ' : '') . "QC Rejected: {$rejectionReason}";
                    $batch->save();
                }
            }

            // 4. Record QualityReject movement in ledger
            StockMovement::create([
                'item_id' => $item->id,
                'item_batch_id' => $qi->item_batch_id,
                'movement_type' => MovementType::QualityReject,
                'quantity' => $rejectedQuantity,
                'unit_cost' => $grnLine->unit_cost ?? $item->unit_cost,
                'from_location_id' => $quarantineLocId,
                'to_location_id' => null,
                'reference_type' => QualityInspection::class,
                'reference_id' => $qi->id,
                'remarks' => "QC Rejection: {$rejectionReason}",
                'moved_at' => now(),
                'user_id' => $inspector->id,
            ]);

            // 5. Update inspection and GRN line records
            $qi->inspection_status = 'rejected';
            $qi->rejected_quantity = $rejectedQuantity;
            $qi->rejection_reason = $rejectionReason;
            $qi->inspected_by_id = $inspector->id;
            $qi->inspection_date = now();
            $qi->save();

            $grnLine->rejected_quantity += $rejectedQuantity;
            $grnLine->quarantined_quantity -= $rejectedQuantity;
            if ($grnLine->quarantined_quantity <= 0) {
                $grnLine->status = 'rejected';
            }
            $grnLine->save();

            // 6. Sync item totals and alerts
            $this->automationService->syncItemTotals($item);

            $this->auditLogger->record(
                AuditAction::RejectedQuarantineStock,
                actor: $inspector,
                target: $qi,
                description: "Rejected {$rejectedQuantity} units of {$item->name} in Quarantine. Reason: {$rejectionReason}",
                newValues: [
                    'inspection_id' => $qi->id,
                    'item_id' => $item->id,
                    'rejected_quantity' => $rejectedQuantity,
                    'reason' => $rejectionReason,
                ]
            );

            return $qi;
        });
    }
}
