<?php

namespace App\Services\Inventory;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use App\Enums\Permission;
use App\Enums\WarehouseTaskType;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use App\Models\InventoryItem;
use App\Models\InventorySerial;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\QualityInspection;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\HimsNotificationService;
use App\Services\InventoryAutomationService;
use App\Services\Procurement\BudgetEncumbranceService;
use App\Services\Warehouse\LocationCompatibilityService;
use App\Services\Warehouse\WarehouseTaskService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QualityControlService
{
    public function __construct(
        private readonly InventoryAutomationService $inventory,
        private readonly AuditLogger $audit,
        private readonly LocationCompatibilityService $compatibility,
        private readonly WarehouseTaskService $tasks,
        private readonly HimsNotificationService $notifications,
        private readonly BudgetEncumbranceService $budget,
    ) {}

    public function releaseLot(QualityInspection $inspection, int $acceptedQuantity, int $targetLocationId, User $inspector, ?string $findings = null, ?string $decisionKey = null): QualityInspection
    {
        return $this->dispose($inspection, $acceptedQuantity, true, $inspector, $targetLocationId, $findings, $decisionKey);
    }

    public function rejectLot(QualityInspection $inspection, int $rejectedQuantity, string $reason, User $inspector, ?string $decisionKey = null): QualityInspection
    {
        return $this->dispose($inspection, $rejectedQuantity, false, $inspector, null, $reason, $decisionKey);
    }

    public function returnRejected(GoodsReceiptNoteLine $receiptLine, int $baseQuantity, User $actor, ?string $key = null): GoodsReceiptNoteLine
    {
        return DB::transaction(function () use ($receiptLine, $baseQuantity, $actor, $key): GoodsReceiptNoteLine {
            $line = GoodsReceiptNoteLine::lockForUpdate()->with('goodsReceiptNote')->findOrFail($receiptLine->id);
            if ($key && ($previous = StockMovement::where('idempotency_key', $key)->first())) {
                if ($previous->reference_type !== $line->getMorphClass() || (int) $previous->reference_id !== $line->id
                    || $previous->movement_type !== MovementType::ReturnToSupplier || $previous->quantity !== $baseQuantity) {
                    throw ValidationException::withMessages(['return_key' => ['This return key belongs to a different transaction.']]);
                }
                return $line;
            }
            $remaining = (int) round($line->rejected_quantity * $line->conversionFactor()) - $line->returned_quantity;
            if ($baseQuantity < 1 || $baseQuantity > $remaining) {
                throw ValidationException::withMessages(['quantity' => ["Only {$remaining} rejected base units remain available for return."]]);
            }
            $grn = $line->goodsReceiptNote;
            $quarantineId = $grn->quarantine_location_id ?? StorageLocation::where('code', 'LOC-QUARANTINE')->value('id');
            if (! $quarantineId) {
                throw new DomainException('The rejected stock has no identifiable quarantine location.');
            }
            $this->inventory->adjustBlockedStock($line->item_id, $quarantineId, $line->item_batch_id, -$baseQuantity);
            $line->returned_quantity += $baseQuantity;
            $line->save();
            InventorySerial::where('source_type', $line->getMorphClass())->where('source_id', $line->id)
                ->where('status', 'rejected')->update(['status' => 'returned', 'storage_location_id' => null]);
            StockMovement::create([
                'item_id' => $line->item_id,
                'item_batch_id' => $line->item_batch_id,
                'purchase_order_id' => $grn->purchase_order_id,
                'goods_receipt_note_id' => $grn->id,
                'purchase_unit' => $line->purchase_unit,
                'purchase_quantity' => fmod((float) $baseQuantity, $line->conversionFactor()) === 0.0
                    ? (int) ($baseQuantity / $line->conversionFactor()) : null,
                'base_unit' => $line->item?->unit,
                'serial_number' => $line->serial_number,
                'idempotency_key' => $key,
                'movement_type' => MovementType::ReturnToSupplier,
                'quantity' => $baseQuantity,
                'unit_cost' => round((float) $line->unit_cost / $line->conversionFactor(), 4),
                'from_location_id' => $quarantineId,
                'to_location_id' => null,
                'reference_type' => $line->getMorphClass(),
                'reference_id' => $line->id,
                'remarks' => "Rejected stock returned to supplier from GRN {$grn->grn_number}.",
                'moved_at' => now(),
                'user_id' => $actor->id,
            ]);
            $this->audit->record(AuditAction::RecordedStockMovement, actor: $actor, target: $line,
                description: "Returned {$baseQuantity} rejected base units to supplier from GRN {$grn->grn_number}.",
                newValues: ['grn_id' => $grn->id, 'returned_quantity' => $line->returned_quantity]);

            return $line;
        });
    }

    private function dispose(QualityInspection $inspection, int $quantity, bool $accept, User $actor, ?int $targetId, ?string $reason, ?string $key): QualityInspection
    {
        return DB::transaction(function () use ($inspection, $quantity, $accept, $actor, $targetId, $reason, $key): QualityInspection {
            $qi = QualityInspection::lockForUpdate()->findOrFail($inspection->id);
            if ($key && ($previous = StockMovement::where('idempotency_key', $key)->first())) {
                if ($previous->reference_type !== $qi->getMorphClass() || (int) $previous->reference_id !== (int) $qi->id
                    || $previous->movement_type !== ($accept ? MovementType::QualityRelease : MovementType::QualityReject)
                    || (int) $previous->purchase_quantity !== $quantity) {
                    throw ValidationException::withMessages(['decision_key' => ['This QC decision key belongs to a different disposition.']]);
                }
                return $qi;
            }
            if (! in_array($qi->inspection_status, ['pending_sample', 'partially_disposed'], true)) {
                throw new DomainException("Inspection #{$qi->id} is already complete.");
            }
            $line = GoodsReceiptNoteLine::lockForUpdate()->with('goodsReceiptNote.purchaseOrder')->findOrFail($qi->grn_line_item_id);
            $item = InventoryItem::lockForUpdate()->findOrFail($line->item_id);
            $grn = $line->goodsReceiptNote;
            $po = $grn->purchaseOrder;
            $quarantineId = $grn->quarantine_location_id ?? StorageLocation::where('code', 'LOC-QUARANTINE')->value('id');
            if (! $quarantineId) {
                throw new DomainException('The receipt has no identifiable quarantine location.');
            }
            if ($quantity < 1 || $quantity > $line->quarantined_quantity) {
                throw ValidationException::withMessages(['quantity' => ["Disposition exceeds {$line->quarantined_quantity} unresolved purchase units."]]);
            }
            $factor = $line->conversionFactor();
            $baseQuantity = (int) round($quantity * $factor);
            if ($baseQuantity < 1) {
                throw ValidationException::withMessages(['quantity' => ['Converted base quantity must be positive.']]);
            }
            $target = null;
            $staging = null;
            if ($accept) {
                if ($line->item_condition !== 'good'
                    || ($line->discrepancy_type !== null && $line->discrepancy_type !== 'shortage')
                    || in_array($line->discrepancy_action, ['reject', 'return_to_supplier', 'hold'], true)
                    || in_array(ItemBatch::classifyExpiryDate($line->expiry_date), [ItemBatch::EXPIRY_EXPIRED, ItemBatch::EXPIRY_CRITICAL], true)) {
                    throw ValidationException::withMessages(['accepted_quantity' => ['Resolve the safety discrepancy before releasing this stock.']]);
                }
                $target = StorageLocation::findOrFail($targetId);
                if ($target->is_receiving_staging || $target->code === 'LOC-STAGING') {
                    throw ValidationException::withMessages(['target_location_id' => ['Select a final storage location.']]);
                }
                $this->compatibility->assertCompatible($target, $item, $baseQuantity);
                $staging = StorageLocation::firstOrCreate(
                    ['code' => 'LOC-STAGING'],
                    ['name' => 'Central Receiving Staging Area', 'type' => 'staging', 'status' => 'active', 'is_receiving_staging' => true],
                );
                if ($staging->status !== 'active') {
                    throw new DomainException('Receiving staging is inactive.');
                }
                if (! $staging->is_receiving_staging) {
                    $staging->is_receiving_staging = true;
                    $staging->save();
                }
                $this->compatibility->assertCompatible($staging, $item, $baseQuantity);
            }

            $this->inventory->adjustQuarantinedStock($item->id, $quarantineId, $qi->item_batch_id, -$baseQuantity);
            if ($accept) {
                $this->inventory->adjustInTransitStock($item->id, $staging->id, $qi->item_batch_id, $baseQuantity);
                $line->accepted_quantity += $quantity;
                $line->pending_put_away_quantity += $baseQuantity;
                $line->staging_location_id = $staging->id;
                $line->destination_location_id = $target->id;
                $qi->accepted_quantity += $quantity;
                $this->budget->recordFulfillmentSpent($po, $quantity * (float) $line->unit_cost);
                $task = $this->tasks->create([
                    'task_type' => WarehouseTaskType::PutAway,
                    'priority' => 'high',
                    'source_location_id' => $staging->id,
                    'destination_location_id' => $target->id,
                    'item_id' => $item->id,
                    'item_batch_id' => $qi->item_batch_id,
                    'requested_quantity' => $baseQuantity,
                    'idempotency_key' => 'put-away-inspection-'.$qi->id.'-'.($key ?: Str::ulid()),
                    'due_at' => now()->addHours(4),
                    'recommendation_reason' => 'QC accepted stock awaiting physical put-away.',
                ], $actor, $qi);
                InventorySerial::where('source_type', $line->getMorphClass())->where('source_id', $line->id)
                    ->update(['storage_location_id' => $staging->id, 'status' => 'awaiting_put_away']);
            } else {
                $this->inventory->adjustBlockedStock($item->id, $quarantineId, $qi->item_batch_id, $baseQuantity);
                $line->rejected_quantity += $quantity;
                $qi->rejected_quantity += $quantity;
                $qi->rejection_reason = $reason;
                InventorySerial::where('source_type', $line->getMorphClass())->where('source_id', $line->id)->update(['status' => 'rejected']);
            }
            $line->quarantined_quantity -= $quantity;
            $qi->inspected_by_id = $actor->id;
            $qi->inspection_date = now();
            if ($accept) {
                $qi->findings = $reason ?: 'Accepted by QC; awaiting put-away.';
            }
            $qi->inspection_status = $line->quarantined_quantity > 0 ? 'partially_disposed'
                : ($qi->accepted_quantity && $qi->rejected_quantity ? 'partially_accepted'
                    : ($qi->accepted_quantity ? 'approved' : 'rejected'));
            $qi->save();
            $line->status = $line->quarantined_quantity > 0 ? 'under_inspection'
                : ($line->pending_put_away_quantity > 0 ? 'awaiting_put_away'
                    : ($line->accepted_quantity > 0 ? 'accepted' : 'rejected'));
            $line->save();

            if ($qi->item_batch_id) {
                $batch = ItemBatch::findOrFail($qi->item_batch_id);
                if ($accept) {
                    $batch->status = 'active';
                } elseif (! ItemStockLevel::where('item_batch_id', $batch->id)
                    ->where(fn ($query) => $query->where('quantity', '>', 0)->orWhere('in_transit_quantity', '>', 0))->exists()) {
                    $batch->status = 'rejected';
                }
                $batch->save();
            }
            if ($line->po_line_id) {
                $poLine = $po->lines()->lockForUpdate()->findOrFail($line->po_line_id);
                if ($accept) {
                    $poLine->accepted_quantity += $quantity;
                } else {
                    $poLine->rejected_quantity += $quantity;
                }
                $poLine->line_status = $poLine->accepted_quantity >= $poLine->ordered_quantity ? 'accepted'
                    : ($poLine->accepted_quantity > 0 ? 'partially_accepted' : 'under_inspection');
                $poLine->save();
            }
            $po->syncReceivingStatus();
            $this->syncReceiptStatus($grn);

            $purchaseUnit = $line->purchase_unit ?: $item->unit ?: 'unit';
            $baseUnit = $item->unit ?: 'unit';
            StockMovement::create([
                'item_id' => $item->id,
                'item_batch_id' => $qi->item_batch_id,
                'purchase_order_id' => $po->id,
                'goods_receipt_note_id' => $grn->id,
                'purchase_unit' => $purchaseUnit,
                'purchase_quantity' => $quantity,
                'base_unit' => $baseUnit,
                'serial_number' => $line->serial_number,
                'idempotency_key' => $key,
                'movement_type' => $accept ? MovementType::QualityRelease : MovementType::QualityReject,
                'quantity' => $baseQuantity,
                'unit_cost' => round((float) $line->unit_cost / $factor, 4),
                'from_location_id' => $quarantineId,
                'to_location_id' => $staging?->id,
                'reference_type' => QualityInspection::class,
                'reference_id' => $qi->id,
                'remarks' => $accept ? "QC accepted {$quantity} {$purchaseUnit} ({$baseQuantity} {$baseUnit}) pending put-away."
                    : "QC rejected {$quantity} {$purchaseUnit} ({$baseQuantity} {$baseUnit}). {$reason}",
                'moved_at' => now(),
                'user_id' => $actor->id,
            ]);
            $this->audit->record(
                $accept ? AuditAction::ReleasedQuarantineStock : AuditAction::RejectedQuarantineStock,
                actor: $actor,
                target: $qi,
                description: ($accept ? 'Accepted ' : 'Rejected ')."{$quantity} {$purchaseUnit} on GRN {$grn->grn_number}",
                newValues: ['grn_id' => $grn->id, 'quantity' => $quantity, 'base_quantity' => $baseQuantity,
                    'remaining_unresolved' => $line->quarantined_quantity],
            );
            if ($accept) {
                if ($po->isFullyAccepted()) {
                    $this->notifications->sendToPermission(
                        Permission::ViewProcurement, 'po-fully-accepted-'.$po->id,
                        'Purchase Order Fully Accepted: '.$po->po_number,
                        "All ordered units on {$po->po_number} passed QC. Accepted stock still requires put-away before it is available.",
                        NotificationPriority::Info, NotificationDestination::GoodsReceipt, ['grn' => $grn->id],
                    );
                }
                $this->notifications->sendToPermission(
                    Permission::ExecuteWarehouseTasks, 'putaway-task-ready-'.$task->id,
                    'Put-Away Task Ready: '.$task->task_number,
                    "{$baseQuantity} {$baseUnit} of {$item->name} await put-away into {$target->name}.",
                    NotificationPriority::Info, NotificationDestination::WarehouseTask, ['task' => $task->id],
                );
                $this->notifications->sendToPermission(
                    Permission::ViewProcurement, 'qc-stock-accepted-'.$qi->id.'-'.$line->accepted_quantity,
                    $qi->inspection_status === 'approved' ? 'Delivery QC Accepted: '.$item->name : 'Delivery Partially Accepted: '.$item->name,
                    "{$quantity} {$purchaseUnit} accepted from GRN {$grn->grn_number}; put-away is pending.",
                    NotificationPriority::Info, NotificationDestination::GoodsReceipt, ['grn' => $grn->id],
                );
            } else {
                $this->notifications->sendToPermission(
                    Permission::ViewProcurement, 'qc-stock-rejected-'.$qi->id.'-'.$line->rejected_quantity,
                    'Delivery Stock Rejected in QC: '.$item->name,
                    "{$quantity} {$purchaseUnit} rejected from GRN {$grn->grn_number}. {$reason}",
                    NotificationPriority::Critical, NotificationDestination::GoodsReceipt, ['grn' => $grn->id],
                );
            }
            return $qi;
        });
    }

    private function syncReceiptStatus(GoodsReceiptNote $grn): void
    {
        $lines = $grn->lines()->get();
        $grn->receipt_status = $lines->sum('quarantined_quantity') > 0 ? 'under_inspection'
            : ($lines->sum('pending_put_away_quantity') > 0 ? 'awaiting_put_away'
                : ($lines->sum('accepted_quantity') > 0 ? 'stored' : 'rejected'));
        $grn->save();
    }
}
