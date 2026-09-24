<?php

namespace App\Services\Inventory;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use App\Models\InventoryItem;
use App\Models\InventorySerial;
use App\Models\ItemBatch;
use App\Models\PurchaseOrder;
use App\Models\QualityInspection;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\HimsNotificationService;
use App\Services\InventoryAutomationService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    public function __construct(
        private readonly InventoryAutomationService $automationService,
        private readonly AuditLogger $auditLogger,
        private readonly HimsNotificationService $notifications,
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

            if (! empty($data['receipt_key'])) {
                $existing = GoodsReceiptNote::with('lines')->where('receipt_key', $data['receipt_key'])->first();
                if ($existing) {
                    if ((int) $existing->purchase_order_id !== (int) $po->id) {
                        throw ValidationException::withMessages(['receipt_key' => ['This receiving key belongs to another purchase order.']]);
                    }
                    $submitted = collect($data['lines'] ?? [])->map(fn ($line) => (int) $line['po_line_id'].':'.(int) $line['received_quantity'])->sort()->values()->all();
                    $recorded = $existing->lines->map(fn ($line) => $line->po_line_id.':'.$line->received_quantity)->sort()->values()->all();
                    if ($submitted !== $recorded
                        || ($data['waybill_number'] ?? null) !== $existing->waybill_number
                        || ($data['packing_slip_number'] ?? null) !== $existing->packing_slip_number) {
                        throw ValidationException::withMessages(['receipt_key' => ['This receiving key was already used for a different delivery.']]);
                    }
                    return $existing;
                }
            }

            $status = PurchaseOrderStatus::tryFrom((string) $po->status);
            if (! ($status?->canReceiveStock() ?? in_array($po->status, ['approved', 'dispatched', 'acknowledged', 'partially_fulfilled', 'partially_received', 'issued'], true))) {
                throw new DomainException("Purchase Order {$po->po_number} must be approved before receiving.");
            }

            if ($po->isFullyAccepted()) {
                throw new DomainException("Purchase Order {$po->po_number} is already fully accepted.");
            }

            foreach (['packing_slip_number', 'waybill_number'] as $referenceField) {
                if (filled($data[$referenceField] ?? null) && GoodsReceiptNote::where('purchase_order_id', $po->id)
                    ->whereRaw('lower('.$referenceField.') = ?', [mb_strtolower(trim((string) $data[$referenceField]))])->exists()) {
                    throw ValidationException::withMessages([$referenceField => ['This delivery reference was already received for the purchase order.']]);
                }
            }

            if (isset($data['actual_supplier_id']) && (int) $data['actual_supplier_id'] !== (int) $po->supplier_id) {
                throw ValidationException::withMessages(['actual_supplier_id' => ['The delivering supplier does not match the approved purchase order.']]);
            }

            // Ensure Quarantine location exists
            $quarantineLocation = StorageLocation::firstOrCreate(
                ['code' => 'LOC-QUARANTINE'],
                [
                    'name' => 'Receiving Quarantine Holding Area',
                    'type' => 'zone',
                    'zone' => 'Quarantine',
                    'status' => 'active',
                    'is_quarantine' => true,
                    'description' => 'Designated holding zone for inbound receipts awaiting QA/QC inspection.',
                ]
            );

            if ($quarantineLocation->status !== 'active') {
                throw new DomainException("Receiving location {$quarantineLocation->name} ({$quarantineLocation->code}) is inactive and cannot receive new inventory.");
            }
            if (! $quarantineLocation->is_quarantine) {
                $quarantineLocation->is_quarantine = true;
                $quarantineLocation->save();
            }

            $grnNumber = 'GRN-'.now()->format('Ymd').'-'.Str::ulid();

            $grn = GoodsReceiptNote::create([
                'grn_number' => $grnNumber,
                'purchase_order_id' => $po->id,
                'quarantine_location_id' => $quarantineLocation->id,
                'receipt_key' => $data['receipt_key'] ?? null,
                'packing_slip_key' => filled($data['packing_slip_number'] ?? null)
                    ? $po->id.':'.hash('sha256', mb_strtolower(trim((string) $data['packing_slip_number']))) : null,
                'waybill_key' => filled($data['waybill_number'] ?? null)
                    ? $po->id.':'.hash('sha256', mb_strtolower(trim((string) $data['waybill_number']))) : null,
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
            $hasDiscrepancies = false;

            foreach ($linesData as $lineInput) {
                $poLine = $po->lines()->where('id', $lineInput['po_line_id'])->first();
                if (! $poLine) {
                    throw ValidationException::withMessages([
                        'lines' => ["Purchase order line #{$lineInput['po_line_id']} does not belong to {$po->po_number}."],
                    ]);
                }

                $item = InventoryItem::lockForUpdate()->findOrFail($poLine->item_id);
                if (filled($lineInput['actual_sku'] ?? null)
                    && strcasecmp(trim((string) $lineInput['actual_sku']), (string) $item->sku) !== 0) {
                    throw ValidationException::withMessages(['lines' => ["Delivered SKU does not match PO line #{$poLine->line_number}."]]);
                }
                if (isset($lineInput['actual_item_id']) && (int) $lineInput['actual_item_id'] !== (int) $item->id) {
                    throw ValidationException::withMessages(['lines' => ["Delivered item does not match PO line #{$poLine->line_number}. Record the wrong item separately; it cannot be booked as {$item->name}."]]);
                }
                if (filled($lineInput['actual_purchase_unit'] ?? null)
                    && strcasecmp((string) $lineInput['actual_purchase_unit'], (string) ($poLine->purchase_unit ?: $item->unit)) !== 0) {
                    throw ValidationException::withMessages(['lines' => ["Delivered UOM does not match PO line #{$poLine->line_number}."]]);
                }
                $receivedQty = (int) ($lineInput['received_quantity'] ?? 0);

                if ($receivedQty <= 0) {
                    continue;
                }

                // Receipt capacity includes replacement of quantities rejected by QC.
                $openQty = $poLine->remainingQuantity();
                if ($receivedQty > $openQty) {
                    throw ValidationException::withMessages([
                        'lines' => ["Line for {$item->name} exceeds the remaining receivable quantity. Open: {$openQty}, Received: {$receivedQty}."],
                    ]);
                }

                // Batch & Expiry validation
                $batchNumber = $lineInput['batch_number'] ?? null;
                $expiryDate = isset($lineInput['expiry_date']) && $lineInput['expiry_date'] !== '' ? Carbon::parse($lineInput['expiry_date']) : null;
                $manufacturedDate = isset($lineInput['manufactured_date']) && $lineInput['manufactured_date'] !== '' ? Carbon::parse($lineInput['manufactured_date']) : null;

                if ($item->is_batch_tracked && empty($batchNumber)) {
                    throw ValidationException::withMessages([
                        'lines' => ["Item {$item->name} requires a batch/lot number."],
                    ]);
                }

                if ($item->is_expiry_tracked && ! $expiryDate) {
                    throw ValidationException::withMessages([
                        'lines' => ["Item {$item->name} requires an expiration date."],
                    ]);
                }

                if ($manufacturedDate && $manufacturedDate->isFuture()) {
                    throw ValidationException::withMessages([
                        'lines' => ["Manufacturing date for {$item->name} cannot be in the future."],
                    ]);
                }

                $serialNumber = trim((string) ($lineInput['serial_number'] ?? ''));
                if ($item->is_serial_tracked) {
                    if ($receivedQty !== 1 || $serialNumber === '') {
                        throw ValidationException::withMessages([
                            'lines' => ["Serial-tracked item {$item->name} must be received one base unit per line with its manufacturer serial number."],
                        ]);
                    }

                    if (InventorySerial::where('item_id', $item->id)->where('serial_number', $serialNumber)->exists()) {
                        throw ValidationException::withMessages([
                            'lines' => ["Serial number '{$serialNumber}' for item {$item->name} has already been registered in the system."],
                        ]);
                    }
                }

                // Discrepancy & Item Condition Detection
                $itemCondition = $lineInput['item_condition'] ?? 'good';
                $discrepancyType = $lineInput['discrepancy_type'] ?? null;
                if ($itemCondition !== 'good' && empty($discrepancyType)) {
                    $discrepancyType = $itemCondition === 'damaged' || $itemCondition === 'compromised' ? 'damage' : $itemCondition;
                } elseif ($expiryDate && $expiryDate->isPast() && empty($discrepancyType)) {
                    $discrepancyType = 'expired';
                } elseif ($receivedQty < $openQty && empty($discrepancyType)) {
                    $discrepancyType = 'shortage';
                } elseif ($receivedQty > $openQty && empty($discrepancyType)) {
                    $discrepancyType = 'overage';
                } elseif ($expiryDate && ! $expiryDate->isPast() && now()->diffInDays($expiryDate) < 30 && empty($discrepancyType)) {
                    $discrepancyType = 'near_expiry';
                }

                $discrepancyAction = $lineInput['discrepancy_action'] ?? ($discrepancyType ? 'quarantine' : null);
                $discrepancyNotes = $lineInput['discrepancy_notes'] ?? null;

                if ($discrepancyType) {
                    $hasDiscrepancies = true;
                }

                $destLocationId = $lineInput['destination_location_id'] ?? $data['destination_location_id'] ?? null;
                if ($destLocationId) {
                    $targetLoc = StorageLocation::find($destLocationId);
                    if ($targetLoc && $targetLoc->status !== 'active') {
                        throw ValidationException::withMessages([
                            'lines' => ["Destination storage location {$targetLoc->name} ({$targetLoc->code}) is inactive."],
                        ]);
                    }
                }

                $conversionFactor = $poLine->conversionFactor();
                if ($conversionFactor <= 1.0 && filled($poLine->purchase_unit)) {
                    $conversionFactor = $item->conversionFactorFor($poLine->purchase_unit);
                }
                if ($item->is_serial_tracked && $conversionFactor !== 1.0) {
                    throw ValidationException::withMessages(['lines' => ["Serial-tracked item {$item->name} must use one base unit per serial number."]]);
                }
                $receivedBaseQty = (int) round($receivedQty * $conversionFactor);
                $baseUnitCost = ($poLine->unit_price && $conversionFactor > 0)
                    ? round((float) $poLine->unit_price / $conversionFactor, 4) : (float) $item->unit_cost;

                $batch = null;
                if ($batchNumber) {
                    $batch = ItemBatch::firstOrCreate(
                        ['item_id' => $item->id, 'batch_number' => $batchNumber],
                        [
                            'lot_number' => $lineInput['lot_number'] ?? null,
                            'manufactured_date' => $manufacturedDate,
                            'expiry_date' => $expiryDate,
                            'received_at' => $grn->received_at->toDateString(),
                            'unit_cost' => $baseUnitCost,
                            'initial_quantity' => $receivedBaseQty,
                            'status' => 'quarantine',
                        ]
                    );
                    if (($expiryDate?->toDateString() !== $batch->expiry_date?->toDateString())
                        || ($manufacturedDate?->toDateString() !== $batch->manufactured_date?->toDateString())
                        || (filled($lineInput['lot_number'] ?? null) && $batch->lot_number !== $lineInput['lot_number'])
                        || abs((float) $batch->unit_cost - $baseUnitCost) > 0.01) {
                        throw ValidationException::withMessages(['lines' => ["Existing batch {$batchNumber} has different expiry, manufacturing, lot, or cost metadata."]]);
                    }
                    if (! $batch->wasRecentlyCreated) {
                        $batch->initial_quantity += $receivedBaseQty;
                        $batch->save();
                    }
                }

                $grnLine = GoodsReceiptNoteLine::create([
                    'goods_receipt_note_id' => $grn->id,
                    'po_line_id' => $poLine->id,
                    'item_id' => $item->id,
                    'item_batch_id' => $batch?->id,
                    'purchase_unit' => $poLine->purchase_unit,
                    'conversion_factor' => $conversionFactor,
                    'ordered_quantity' => $poLine->ordered_quantity,
                    'shipped_quantity' => $receivedQty,
                    'received_quantity' => $receivedQty,
                    'received_base_quantity' => $receivedBaseQty,
                    'quarantined_quantity' => $receivedQty,
                    'accepted_quantity' => 0,
                    'rejected_quantity' => 0,
                    'unit_cost' => $poLine->unit_price ?? $item->unit_cost,
                    'destination_location_id' => $destLocationId ?: $quarantineLocation->id,
                    'batch_number' => $batchNumber,
                    'lot_number' => $lineInput['lot_number'] ?? null,
                    'expiry_date' => $expiryDate,
                    'manufactured_date' => $manufacturedDate,
                    'serial_number' => $serialNumber ?: null,
                    'status' => 'quarantined',
                    'item_condition' => $itemCondition,
                    'discrepancy_type' => $discrepancyType,
                    'discrepancy_action' => $discrepancyAction,
                    'discrepancy_notes' => $discrepancyNotes,
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

                // Place into quarantine stock balance using base units
                $this->automationService->adjustQuarantinedStock($item->id, $quarantineLocation->id, $batch?->id, $receivedBaseQty);

                $pUnit = $poLine->purchase_unit ?: $item->unit ?: 'unit';
                $bUnit = $item->unit ?: 'unit';
                $unitDisplay = $conversionFactor > 1.0
                    ? "{$receivedQty} {$pUnit} ({$receivedBaseQty} {$bUnit})"
                    : "{$receivedBaseQty} {$bUnit}";

                // Record movement to immutable ledger
                StockMovement::create([
                    'item_id' => $item->id,
                    'item_batch_id' => $batch?->id,
                    'purchase_order_id' => $po->id,
                    'goods_receipt_note_id' => $grn->id,
                    'purchase_unit' => $pUnit,
                    'purchase_quantity' => $receivedQty,
                    'base_unit' => $bUnit,
                    'serial_number' => $serialNumber ?: null,
                    'movement_type' => MovementType::Quarantine,
                    'quantity' => $receivedBaseQty,
                    'unit_cost' => $baseUnitCost,
                    'from_location_id' => null,
                    'to_location_id' => $quarantineLocation->id,
                    'reference_type' => GoodsReceiptNote::class,
                    'reference_id' => $grn->id,
                    'remarks' => "Inbound Dock Receipt {$grn->grn_number} against PO {$po->po_number} Line #{$poLine->line_number}: {$unitDisplay}",
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
                $poLine->line_status = $poLine->remainingQuantity() === 0 ? 'awaiting_inspection' : 'partially_delivered';
                $poLine->save();

                $totalReceivedValue += ($receivedQty * (float) ($poLine->unit_price ?? 0));
            }

            if (! $grn->lines()->exists()) {
                throw ValidationException::withMessages(['lines' => ['At least one line must have a positive delivered quantity.']]);
            }

            // Update Purchase Order fulfillment status
            $po->syncReceivingStatus();

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

            // Real-time notifications
            $this->notifications->sendToPermission(
                Permission::InspectStock,
                "grn-qc-pending-{$grn->id}",
                "Inbound Delivery Awaiting QC",
                "Goods Receipt Note {$grn->grn_number} for PO {$po->po_number} has been received into Quarantine and is awaiting QC inspection.",
                NotificationPriority::Info,
                NotificationDestination::QualityControl,
            );

            if ($hasDiscrepancies) {
                $this->notifications->sendToPermission(
                    Permission::ViewProcurement,
                    "grn-discrepancy-{$grn->id}",
                    "Delivery Discrepancy Flagged",
                    "Discrepancies were noted during dock intake for PO {$po->po_number} (GRN: {$grn->grn_number}).",
                    NotificationPriority::Warning,
                    NotificationDestination::GoodsReceipt,
                    ['grn' => $grn->id],
                );
            }

            if ($po->created_by_user_id) {
                $buyer = User::find($po->created_by_user_id);
                if ($buyer) {
                    $isFull = $po->isFullyReceived();
                    $this->notifications->sendToUser(
                        $buyer,
                        "po-receipt-status-{$grn->id}",
                        $isFull ? "PO {$po->po_number} Delivery Recorded" : "PO {$po->po_number} Partial Delivery Recorded",
                        $isFull ? "Purchase Order {$po->po_number} has been delivered to quarantine for QC." : "A partial delivery was recorded for Purchase Order {$po->po_number}; QC is pending.",
                        NotificationPriority::Info,
                        NotificationDestination::GoodsReceipt,
                        ['grn' => $grn->id],
                    );
                }
            }

            return $grn;
        });
    }
}
