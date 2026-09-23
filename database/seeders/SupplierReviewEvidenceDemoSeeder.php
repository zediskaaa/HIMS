<?php

namespace Database\Seeders;

use App\Enums\SupplierStatus;
use App\Models\InventoryItem;
use App\Models\KpiProcessReview;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SupplierReviewEvidenceDemoSeeder extends Seeder
{
    private const ORDERS_PER_SUPPLIER = 3;

    public function run(): void
    {
        $review = KpiProcessReview::query()
            ->where('status', 'approved')
            ->latest('period_end')
            ->firstOrFail();
        $items = InventoryItem::query()->orderBy('id')->get();

        if ($items->isEmpty()) {
            throw new RuntimeException('Supplier review evidence requires at least one inventory item.');
        }

        $suppliers = Supplier::query()
            ->where('status', '!=', SupplierStatus::Archived->value)
            ->whereDoesntHave('purchaseOrders', fn ($query) => $query->whereBetween('created_at', [
                $review->period_start->copy()->startOfDay(),
                $review->period_end->copy()->endOfDay(),
            ]))
            ->orderBy('id')
            ->get();

        foreach ($suppliers as $supplier) {
            DB::transaction(function () use ($supplier, $review, $items): void {
                $seed = hexdec(substr(hash('sha256', $supplier->identity_key ?? $supplier->name), 0, 8));

                for ($sequence = 1; $sequence <= self::ORDERS_PER_SUPPLIER; $sequence++) {
                    $item = $items[($seed + $sequence) % $items->count()];
                    $orderedQuantity = 40 + (($seed + ($sequence * 17)) % 61);
                    $receivedQuantity = $orderedQuantity - (($seed + ($sequence * 7)) % 6);
                    $unitCost = max(1, (float) $item->unit_cost);
                    $promisedLeadTime = max(1, (int) ($supplier->standard_lead_time_days ?? 5));
                    $requestedAt = $review->period_start->copy()->addDays(10 + (($seed + ($sequence * 11)) % 50));
                    $dispatchedAt = $requestedAt->copy()->addDay();
                    $actualLeadTime = max(1, $promisedLeadTime + ((($seed + $sequence) % 5 === 0) ? 1 : -1));
                    $receivedAt = $dispatchedAt->copy()->addDays($actualLeadTime);
                    $deliveryDate = $dispatchedAt->copy()->addDays($promisedLeadTime);
                    $totalAmount = round($orderedQuantity * $unitCost, 2);

                    $purchaseOrder = PurchaseOrder::updateOrCreate(
                        ['po_number' => "PO-{$review->review_number}-{$supplier->id}-{$sequence}"],
                        [
                            'supplier_id' => $supplier->id,
                            'item_id' => $item->id,
                            'quantity' => $orderedQuantity,
                            'purchase_unit' => $item->unit,
                            'conversion_factor' => 1,
                            'unit_cost' => $unitCost,
                            'total_amount' => $totalAmount,
                            'total_encumbered_amount' => $totalAmount,
                            'status' => 'received',
                            'requested_at' => $requestedAt,
                            'dispatched_at' => $dispatchedAt,
                            'received_at' => $receivedAt,
                            'delivery_date' => $deliveryDate,
                            'notes' => 'Persisted demo procurement evidence for supplier performance scoring.',
                        ]
                    );

                    $purchaseOrder->forceFill([
                        'created_at' => $requestedAt,
                        'updated_at' => $receivedAt,
                    ])->save();

                    PurchaseOrderLine::updateOrCreate(
                        ['purchase_order_id' => $purchaseOrder->id, 'line_number' => 1],
                        [
                            'item_id' => $item->id,
                            'purchase_unit' => $item->unit,
                            'conversion_factor' => 1,
                            'ordered_quantity' => $orderedQuantity,
                            'received_quantity' => $receivedQuantity,
                            'invoiced_quantity' => $receivedQuantity,
                            'unit_price' => $unitCost,
                            'total_line_amount' => $totalAmount,
                            'line_status' => $receivedQuantity === $orderedQuantity ? 'received' : 'partially_received',
                        ]
                    );
                }
            });
        }
    }
}
