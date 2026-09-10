<?php

namespace App\Services\Inventory;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\PurchaseOrder;
use App\Models\QualityInspection;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryAutomationService;
use App\Services\Procurement\BudgetEncumbranceService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use App\Models\InventorySerial;

class GoodsReceiptService
{
    public function __construct(
        private readonly InventoryAutomationService $automationService,
        private readonly BudgetEncumbranceService $budgetService,
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Ingest an inbound shipment against a Purchase Order and place into Quarantine.
     *
     * @param  array<string, mixed>  $data
     */
    public function receiveOrder(PurchaseOrder $purchaseOrder, array $data, User $actor): GoodsReceiptNote
    {
        return DB::transaction(function () use ($purchaseOrder, $data, $actor) {
            // Lock PO to avoid race condition on concurrent receiving
            $po = PurchaseOrder::lockForUpdate()->with(['lines.item', 'supplier'])->findOrFail($purchaseOrder->id);

            if (in_array($po->status, ['cancelled', 'rejected'], true)) {
                throw new DomainException("Cannot receive delivery against {$po->status} Purchase Order {$po->po_number}.");
            }

            if ($po->isFullyReceived()) {
                throw new DomainException("Purchase Order {$po->po_number} is already fully received.");
            }

            // Ensure Quarantine location exists
            $quarantineLocation = StorageLocation::firstOrCreate(
                ['code' => 'LOC-QUARANTINE'],
                [
                    'name' => 'Receiving Quarantine Holding Area',
                    'type' => 'zone',
                    'zone' => 'Quarantine',
                    'status' => 'active',
                    'description' => 'Designated holding zone for inbound receipts awaiting QA/QC inspection.'
                ]
            );

            $grnNumber = 'GRN-'.now()->format('Ymd').'-'.Str::ulid();

            $grn = GoodsReceiptNote::create([
                'grn_number' => $grnNumber,
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'carrier_name' => $data['carrier_name'] ?? null,
                'waybill_number' => $data['waybill_number'] ?? null,
                'packing_slip_number' => $data['packing_slip_number'] ?? null,
                'received_by_id' => $actor->id,
                'receipt_status' => 'quarantined',
                'received_at' => isset($data['received_at']) ? Carbon::parse($data['received_at']) : now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $linesData = $data['lines'] ?? [];

            if (empty($linesData)) {
                throw ValidationException::withMessages([
                    'lines' => ['Enter the actual received quantity and manufacturer identifiers for at least one purchase-order line.'],
                ]);
            }

            $totalReceivedValue = 0.00;

            foreach ($linesData as $lineInput) {
                $poLine = $po->lines()->where('id', $lineInput['po_line_id'])->first();
                if (!$poLine) {
                    throw ValidationException::withMessages([
                        'lines' => ["Purchase order line #{$lineInput['po_line_id']} does not belong to {$po->po_number}."]
                    ]);
                }

                $item = InventoryItem::lockForUpdate()->findOrFail($poLine->item_id);
                $receivedQty = (int) ($lineInput['received_quantity'] ?? 0);

                if ($receivedQty <= 0) {
                    continue;
                }

                // Tolerance check: max 5% over-delivery allowed
                $openQty = $poLine->remainingQuantity();
                $maxAllowedQty = (int) ceil($openQty * 1.05);

                if ($receivedQty > $maxAllowedQty) {
                    throw ValidationException::withMessages([
                        'lines' => ["Line for {$item->name} exceeds the allowable +5% over-delivery tolerance. Open: {$openQty}, Max Allowed: {$maxAllowedQty}, Received: {$receivedQty}."]
                    ]);
                }

                // Batch & Expiry validation
                $batchNumber = $lineInput['batch_number'] ?? null;
                $expiryDate = isset($lineInput['expiry_date']) && $lineInput['expiry_date'] !== '' ? Carbon::parse($lineInput['expiry_date']) : null;
                $manufacturedDate = isset($lineInput['manufactured_date']) && $lineInput['manufactured_date'] !== '' ? Carbon::parse($lineInput['manufactured_date']) : null;

                if ($item->is_batch_tracked && empty($batchNumber)) {
                    throw ValidationException::withMessages([
                        'lines' => ["Item {$item->name} requires a batch/lot number."]
                    ]);
                }

                if ($item->is_expiry_tracked && ! $expiryDate) {
                    throw ValidationException::withMessages([
                        'lines' => ["Item {$item->name} requires an expiration date."]
                    ]);
                }

                if ($expiryDate && $expiryDate->isPast()) {
                    throw ValidationException::withMessages([
                        'lines' => ["Expiration date for {$item->name} cannot be in the past."]
                    ]);
                }

                if ($manufacturedDate && $manufacturedDate->isFuture()) {
                    throw ValidationException::withMessages([
                        'lines' => ["Manufacturing date for {$item->name} cannot be in the future."]
                    ]);
                }

                $serialNumber = trim((string) ($lineInput['serial_number'] ?? ''));
                if ($item->is_serial_tracked && ($receivedQty !== 1 || $serialNumber === '')) {
                    throw ValidationException::withMessages([
                        'lines' => ["Serial-tracked item {$item->name} must be received one unit per line with its manufacturer serial number."],
                    ]);
                }

                $batch = null;
                if ($batchNumber) {
                    $batch = ItemBatch::firstOrCreate(
                        ['item_id' => $item->id, 'batch_number' => $batchNumber],
                        [
                            'lot_number' => $lineInput['lot_number'] ?? null,
                            'manufactured_date' => $manufacturedDate,
                            'expiry_date' => $expiryDate,
                            'received_at' => now()->toDateString(),
                            'unit_cost' => $poLine->unit_price ?? $item->unit_cost,
                            'initial_quantity' => $receivedQty,
                            'status' => 'quarantine',
                        ]
                    );
                }

                $grnLine = GoodsReceiptNoteLine::create([
                    'goods_receipt_note_id' => $grn->id,
                    'po_line_id' => $poLine->id,
                    'item_id' => $item->id,
                    'item_batch_id' => $batch?->id,
                    'ordered_quantity' => $poLine->ordered_quantity,
                    'shipped_quantity' => $receivedQty,
                    'received_quantity' => $receivedQty,
                    'quarantined_quantity' => $receivedQty,
                    'accepted_quantity' => 0,
                    'rejected_quantity' => 0,
                    'unit_cost' => $poLine->unit_price ?? $item->unit_cost,
                    'destination_location_id' => $quarantineLocation->id,
                    'batch_number' => $batchNumber,
                    'lot_number' => $lineInput['lot_number'] ?? null,
                    'expiry_date' => $expiryDate,
                    'manufactured_date' => $manufacturedDate,
                    'serial_number' => $serialNumber ?: null,
                    'status' => 'quarantined',
                    'notes' => $lineInput['notes'] ?? null,
                ]);

                if ($item->is_serial_tracked) {
                    InventorySerial::create([
                        'item_id' => $item->id,
                        'item_batch_id' => $batch?->id,
                        'storage_location_id' => $quarantineLocation->id,
                        'serial_number' => $serialNumber,
                        'status' => 'quarantined',
                        'source_type' => GoodsReceiptNoteLine::class,
                        'source_id' => $grnLine->id,
                    ]);
                }

                // Place into quarantine stock balance
                $this->automationService->adjustQuarantinedStock($item->id, $quarantineLocation->id, $batch?->id, $receivedQty);

                // Record movement to immutable ledger
                StockMovement::create([
                    'item_id' => $item->id,
                    'item_batch_id' => $batch?->id,
                    'movement_type' => MovementType::Quarantine,
                    'quantity' => $receivedQty,
                    'unit_cost' => $poLine->unit_price ?? $item->unit_cost,
                    'from_location_id' => null,
                    'to_location_id' => $quarantineLocation->id,
                    'reference_type' => GoodsReceiptNote::class,
                    'reference_id' => $grn->id,
                    'remarks' => "Inbound Dock Receipt {$grn->grn_number} against PO {$po->po_number} Line #{$poLine->line_number}",
                    'moved_at' => now(),
                    'user_id' => $actor->id,
                ]);

                // Create Quality Inspection item
                QualityInspection::create([
                    'grn_line_item_id' => $grnLine->id,
                    'item_id' => $item->id,
                    'item_batch_id' => $batch?->id,
                    'inspected_by_id' => $actor->id,
                    'inspection_status' => 'pending_sample',
                    'sample_quantity' => $receivedQty,
                    'accepted_quantity' => 0,
                    'rejected_quantity' => 0,
                    'inspection_date' => now(),
                ]);

                // Update PO Line received quantity
                $poLine->received_quantity += $receivedQty;
                if ($poLine->received_quantity >= $poLine->ordered_quantity) {
                    $poLine->line_status = 'fully_received';
                } else {
                    $poLine->line_status = 'partially_received';
                }
                $poLine->save();

                $totalReceivedValue += ($receivedQty * (float) ($poLine->unit_price ?? 0));
            }

            // Update Purchase Order fulfillment status
            $po->refresh();
            if ($po->isFullyReceived()) {
                $po->status = PurchaseOrderStatus::Fulfilled->value;
                $po->received_at = now();
            } else {
                $po->status = 'partially_received';
            }
            $po->save();

            // Record budget fulfillment spent
            if ($totalReceivedValue > 0) {
                $this->budgetService->recordFulfillmentSpent($po, $totalReceivedValue);
            }

            $this->auditLogger->record(
                AuditAction::CreatedGoodsReceipt,
                actor: $actor,
                target: $grn,
                description: "Created and posted Goods Receipt Note {$grn->grn_number} into Quarantine for PO {$po->po_number}",
                newValues: [
                    'grn_number' => $grn->grn_number,
                    'po_number' => $po->po_number,
                    'received_value' => $totalReceivedValue,
                ]
            );

            return $grn;
        });
    }
}
