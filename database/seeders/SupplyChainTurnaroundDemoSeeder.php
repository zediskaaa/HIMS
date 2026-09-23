<?php

namespace Database\Seeders;

use App\Enums\ProcurementMethod;
use App\Enums\RequisitionStatus;
use App\Enums\RfqBiddingType;
use App\Enums\RfqStatus;
use App\Enums\SupplierStatus;
use App\Models\CostCenter;
use App\Models\GoodsReceiptNote;
use App\Models\InspectionAcceptanceReport;
use App\Models\InventoryItem;
use App\Models\KpiProcessReview;
use App\Models\ProcurementCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\RfqLineItem;
use App\Models\SourcingRfq;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Analytics\BottleneckAnalysisService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SupplyChainTurnaroundDemoSeeder extends Seeder
{
    private const TRANSACTIONS_PER_REVIEW = 6;

    public function run(): void
    {
        $reviews = KpiProcessReview::query()
            ->whereIn('review_number', ['REV-2026-Q3', 'REV-2026-Q4'])
            ->orderBy('period_start')
            ->get();

        if ($reviews->isEmpty()) {
            throw new RuntimeException('Supply chain turnaround evidence requires a seeded KPI process review.');
        }

        $users = User::query()->where('status', 'active')->orderBy('id')->get();
        $suppliers = Supplier::query()
            ->where('status', '!=', SupplierStatus::Archived->value)
            ->orderBy('id')
            ->get();
        $items = InventoryItem::query()->orderBy('id')->get();

        if ($users->isEmpty() || $suppliers->isEmpty() || $items->isEmpty()) {
            throw new RuntimeException('Supply chain turnaround evidence requires active users, suppliers, and inventory items.');
        }

        $costCenter = CostCenter::firstOrCreate(
            ['code' => 'CC-SCM-DEMO'],
            [
                'name' => 'Supply Chain Operations',
                'department' => 'Supply Chain Management',
                'manager_id' => $users->first()->id,
                'is_active' => true,
            ]
        );
        $category = ProcurementCategory::firstOrCreate(
            ['code' => 'SCM-DEMO'],
            [
                'name' => 'Supply Chain Review Evidence',
                'category_manager_id' => $users->first()->id,
                'description' => 'Persisted demonstration transactions used by process velocity analytics.',
                'is_active' => true,
            ]
        );

        foreach ($reviews as $review) {
            DB::transaction(function () use ($review, $users, $suppliers, $items, $costCenter, $category): void {
                for ($index = 0; $index < self::TRANSACTIONS_PER_REVIEW; $index++) {
                    $this->seedTransaction(
                        $review,
                        $index,
                        $users[$index % $users->count()],
                        $users[($index + 1) % $users->count()],
                        $suppliers[$index % $suppliers->count()],
                        $items[$index % $items->count()],
                        $costCenter,
                        $category,
                    );
                }

                $analysis = app(BottleneckAnalysisService::class)
                    ->evaluate($review->period_start, $review->period_end);
                $metrics = $review->fresh()->metrics_summary ?? [];
                $metrics['bottleneck_stages'] = $analysis['stages'];
                $metrics['critical_bottleneck'] = $analysis['critical_bottleneck'];

                $review->update(['metrics_summary' => $metrics]);
            });
        }
    }

    private function seedTransaction(
        KpiProcessReview $review,
        int $index,
        User $requester,
        User $receiver,
        Supplier $supplier,
        InventoryItem $item,
        CostCenter $costCenter,
        ProcurementCategory $category,
    ): void {
        $sequence = $index + 1;
        $reference = "{$review->review_number}-".str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
        $baseDate = $review->period_start->copy()->addDays(3 + ($index * 10));
        $prDurations = [1, 2, 4, 3, 2, 1];
        $rfqDurations = [3, 5, 8, 6, 4, 7];
        $conformeDurations = [1, 2, 3, 1, 4, 2];
        $deliveryDurations = [4, 6, 11, 8, 13, 5];
        $inspectionDurations = [0, 1, 2, 1, 0, 2];
        $acceptanceDurations = [1, 1, 2, 0, 1, 1];

        $approvedAt = $baseDate->copy()->addDays($prDurations[$index]);
        $rfqCreatedAt = $approvedAt->copy()->addDay();
        $awardedAt = $rfqCreatedAt->copy()->addDays($rfqDurations[$index]);
        $poCreatedAt = $awardedAt->copy()->addDay();
        $dispatchedAt = $poCreatedAt->copy();
        $conformeDate = $dispatchedAt->copy()->addDays($conformeDurations[$index]);
        $receivedAt = $dispatchedAt->copy()->addDays($deliveryDurations[$index]);
        $inspectionDate = $receivedAt->copy()->addDays($inspectionDurations[$index]);
        $acceptanceDate = $inspectionDate->copy()->addDays($acceptanceDurations[$index]);
        $quantity = 40 + ($sequence * 10);
        $unitCost = max(1, (float) ($item->unit_cost ?? 1));
        $totalAmount = round($quantity * $unitCost, 2);

        $purchaseRequest = PurchaseRequest::updateOrCreate(
            ['pr_number' => "PR-{$reference}"],
            [
                'title' => "Supply chain lifecycle evidence {$reference}",
                'description' => 'Persisted workflow evidence for process turnaround analytics.',
                'requester_id' => $requester->id,
                'cost_center_id' => $costCenter->id,
                'procurement_category_id' => $category->id,
                'procurement_method' => ProcurementMethod::RequestForQuotation->value,
                'total_estimated_amount' => $totalAmount,
                'currency' => 'PHP',
                'priority' => 'medium',
                'status' => RequisitionStatus::Approved->value,
                'is_emergency' => false,
                'submitted_at' => $baseDate->copy()->addHours(2),
                'approved_at' => $approvedAt,
            ]
        );
        $this->setTimestamps($purchaseRequest, $baseDate, $approvedAt);

        $prLine = PurchaseRequestLine::updateOrCreate(
            ['purchase_request_id' => $purchaseRequest->id, 'line_number' => 1],
            [
                'item_id' => $item->id,
                'item_description' => $item->name,
                'quantity' => $quantity,
                'uom' => $item->unit ?? 'unit',
                'estimated_unit_price' => $unitCost,
                'estimated_total_price' => $totalAmount,
                'need_by_date' => $receivedAt->copy()->addDays(2),
            ]
        );

        $rfq = SourcingRfq::updateOrCreate(
            ['rfq_number' => "RFQ-{$reference}"],
            [
                'title' => "RFQ for {$item->name}",
                'description' => 'Awarded sourcing event retained as process review evidence.',
                'purchase_request_id' => $purchaseRequest->id,
                'created_by_user_id' => $requester->id,
                'procurement_method' => ProcurementMethod::RequestForQuotation->value,
                'bidding_type' => RfqBiddingType::Sealed->value,
                'submission_deadline' => $rfqCreatedAt->copy()->addDays(2),
                'status' => RfqStatus::Awarded->value,
                'currency' => 'PHP',
                'published_at' => $rfqCreatedAt,
                'unsealed_at' => $awardedAt->copy()->subHour(),
                'unsealed_by_user_id' => $receiver->id,
            ]
        );
        $this->setTimestamps($rfq, $rfqCreatedAt, $awardedAt);

        RfqLineItem::updateOrCreate(
            ['sourcing_rfq_id' => $rfq->id, 'line_number' => 1],
            [
                'pr_line_id' => $prLine->id,
                'item_id' => $item->id,
                'target_quantity' => $quantity,
                'uom' => $item->unit ?? 'unit',
                'item_description' => $item->name,
                'max_budget_unit_price' => $unitCost,
            ]
        );

        $purchaseOrder = PurchaseOrder::updateOrCreate(
            ['po_number' => "PO-{$reference}"],
            [
                'purchase_request_id' => $purchaseRequest->id,
                'sourcing_rfq_id' => $rfq->id,
                'cost_center_id' => $costCenter->id,
                'supplier_id' => $supplier->id,
                'item_id' => $item->id,
                'quantity' => $quantity,
                'purchase_unit' => $item->unit ?? 'unit',
                'conversion_factor' => 1,
                'unit_cost' => $unitCost,
                'total_amount' => $totalAmount,
                'currency' => 'PHP',
                'total_encumbered_amount' => $totalAmount,
                'status' => 'received',
                'delivery_date' => $dispatchedAt->copy()->addDays(10),
                'conforme_date' => $conformeDate,
                'conforme_signed_by' => $supplier->contact_person ?? $supplier->name,
                'created_by_user_id' => $requester->id,
                'requested_at' => $poCreatedAt,
                'dispatched_at' => $dispatchedAt,
                'received_at' => $receivedAt,
                'notes' => 'Persisted end-to-end evidence for supply chain turnaround reporting.',
            ]
        );
        $this->setTimestamps($purchaseOrder, $poCreatedAt, $receivedAt);

        PurchaseOrderLine::updateOrCreate(
            ['purchase_order_id' => $purchaseOrder->id, 'line_number' => 1],
            [
                'pr_line_id' => $prLine->id,
                'item_id' => $item->id,
                'purchase_unit' => $item->unit ?? 'unit',
                'conversion_factor' => 1,
                'ordered_quantity' => $quantity,
                'received_quantity' => $quantity,
                'invoiced_quantity' => $quantity,
                'unit_price' => $unitCost,
                'total_line_amount' => $totalAmount,
                'line_status' => 'received',
            ]
        );

        $goodsReceipt = GoodsReceiptNote::updateOrCreate(
            ['grn_number' => "GRN-{$reference}"],
            [
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $supplier->id,
                'received_by_id' => $receiver->id,
                'receipt_status' => 'posted',
                'delivery_status' => 'complete',
                'received_at' => $receivedAt,
                'notes' => 'Completed receipt supporting lifecycle turnaround evidence.',
            ]
        );
        $this->setTimestamps($goodsReceipt, $receivedAt, $receivedAt);

        $inspectionReport = InspectionAcceptanceReport::updateOrCreate(
            ['iar_number' => "IAR-{$reference}"],
            [
                'goods_receipt_note_id' => $goodsReceipt->id,
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $supplier->id,
                'iar_date' => $receivedAt->toDateString(),
                'inspection_date' => $inspectionDate->toDateString(),
                'inspected_by_id' => $receiver->id,
                'inspection_status' => 'in_order',
                'inspection_findings' => 'Quantity, packaging, and specifications verified against the purchase order.',
                'acceptance_date' => $acceptanceDate->toDateString(),
                'accepted_by_id' => $requester->id,
                'delivery_status' => 'complete',
                'status' => 'accepted',
                'days_delayed' => max(0, $deliveryDurations[$index] - 10),
                'liquidated_damages_amount' => 0,
                'notes' => 'Completed inspection and custodial acceptance evidence.',
            ]
        );
        $this->setTimestamps($inspectionReport, $receivedAt, $acceptanceDate);
    }

    private function setTimestamps($model, $createdAt, $updatedAt): void
    {
        $model->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ])->saveQuietly();
    }
}
