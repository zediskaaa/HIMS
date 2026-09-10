<?php

namespace App\Services\Inventory;

use App\Enums\AuditAction;
use App\Enums\RequisitionStatus;
use App\Models\CostCenter;
use App\Models\InventoryItem;
use App\Models\ProcurementCategory;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class ReplenishmentDaemon
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Safety Stock formula:
     * SS = Z * sqrt( Avg_Lead_Time * Variance_Demand + (Avg_Demand^2) * Variance_Lead_Time )
     * where Z = 1.645 (95% service level factor).
     */
    public function calculateSafetyStock(InventoryItem $item, float $serviceFactor = 1.645): int
    {
        $leadTime = $item->lead_time_days > 0 ? (float) $item->lead_time_days : 7.0;
        $annualDemand = $item->annual_demand > 0 ? (float) $item->annual_demand : max(120.0, (float) ($item->quantity_on_hand * 2));
        $avgDailyDemand = $annualDemand / 365.0;

        // Variability parameters (assuming standard deviation ~25% of mean if unrecorded)
        $stdDevDemand = max(1.0, $avgDailyDemand * 0.25);
        $varianceDemand = pow($stdDevDemand, 2);

        $stdDevLeadTime = max(1.0, $leadTime * 0.20);
        $varianceLeadTime = pow($stdDevLeadTime, 2);

        $term1 = $leadTime * $varianceDemand;
        $term2 = pow($avgDailyDemand, 2) * $varianceLeadTime;

        $ss = (int) ceil($serviceFactor * sqrt($term1 + $term2));

        return max(1, $ss);
    }

    /**
     * Dynamic Reorder Point formula:
     * ROP = (Avg_Daily_Demand * Avg_Lead_Time) + SS
     */
    public function calculateReorderPoint(InventoryItem $item): int
    {
        $leadTime = $item->lead_time_days > 0 ? (float) $item->lead_time_days : 7.0;
        $annualDemand = $item->annual_demand > 0 ? (float) $item->annual_demand : max(120.0, (float) ($item->quantity_on_hand * 2));
        $avgDailyDemand = $annualDemand / 365.0;

        $ss = $item->safety_stock > 0 ? (float) $item->safety_stock : (float) $this->calculateSafetyStock($item);

        $rop = (int) ceil(($avgDailyDemand * $leadTime) + $ss);

        return max(1, $rop);
    }

    /**
     * Economic Order Quantity formula:
     * EOQ = sqrt( (2 * Annual_Demand * PO_Order_Cost) / Annual_Holding_Cost )
     */
    public function calculateEconomicOrderQuantity(InventoryItem $item): int
    {
        $annualDemand = $item->annual_demand > 0 ? (float) $item->annual_demand : max(120.0, (float) ($item->quantity_on_hand * 2));
        $orderCost = 500.00; // Fixed administrative cost per PO issuance (₱500 default)
        $unitCost = max(1.00, (float) ($item->unit_cost ?? 10.00));
        $holdingCostRate = 0.20; // 20% annual carrying/holding cost
        $annualHoldingCost = max(1.00, $unitCost * $holdingCostRate);

        $eoq = (int) ceil(sqrt((2.0 * $annualDemand * $orderCost) / $annualHoldingCost));

        return max(1, $eoq);
    }

    public function evaluateAndTriggerReplenishment(InventoryItem $item, ?User $actor = null): ?PurchaseRequest
    {
        return $this->evaluateAndReplenish($item, $actor);
    }

    /**
     * Check if an item requires replenishment and instantiate a draft Purchase Request if breached.
     */
    public function evaluateAndReplenish(InventoryItem $item, ?User $actor = null): ?PurchaseRequest
    {
        return DB::transaction(function () use ($item, $actor) {
            $lockedItem = InventoryItem::lockForUpdate()->findOrFail($item->id);

            $rop = $lockedItem->reorder_point > 0 ? $lockedItem->reorder_point : $this->calculateReorderPoint($lockedItem);
            $eoq = $lockedItem->economic_order_quantity > 0 ? $lockedItem->economic_order_quantity : $this->calculateEconomicOrderQuantity($lockedItem);

            $atp = $lockedItem->availableToPromise();

            // On-Order: open quantities from active unfulfilled Purchase Orders
            $onOrder = (int) PurchaseOrderLine::query()
                ->where('item_id', $lockedItem->id)
                ->whereHas('purchaseOrder', fn ($q) => $q->whereNotIn('status', ['received', 'cancelled', 'rejected']))
                ->selectRaw('coalesce(sum(ordered_quantity - received_quantity), 0) as open_qty')
                ->value('open_qty');

            // Replenishment condition: (ATP + On-Order) <= ROP
            if (($atp + $onOrder) > $rop) {
                return null;
            }

            // Prevent duplicate open PRs for the same item
            $existingPr = PurchaseRequest::query()
                ->whereIn('status', [RequisitionStatus::Draft->value, RequisitionStatus::PendingApproval->value])
                ->whereHas('lines', fn ($q) => $q->where('item_id', $lockedItem->id))
                ->exists();

            if ($existingPr) {
                return null;
            }

            $user = $actor ?? User::first() ?? User::factory()->inventoryManager()->create();
            $category = ProcurementCategory::first() ?? ProcurementCategory::create([
                'name' => 'General Medical Supplies',
                'code' => 'GEN-MED',
                'is_active' => true,
            ]);
            $costCenter = CostCenter::first() ?? CostCenter::create([
                'code' => 'CC-DEFAULT',
                'name' => 'Default Operations',
                'department' => 'Operations',
                'is_active' => true,
            ]);

            $prNumber = 'PR-AUTO-' . now()->format('Ymd') . '-' . str_pad((string) (PurchaseRequest::count() + 1), 4, '0', STR_PAD_LEFT);
            $totalEst = round($eoq * (float) ($lockedItem->unit_cost ?? 10.00), 2);

            $pr = PurchaseRequest::create([
                'pr_number' => $prNumber,
                'title' => "Automated Replenishment: {$lockedItem->name} (ROP Breached)",
                'description' => "Triggered automatically when ATP ({$atp}) + On-Order ({$onOrder}) <= ROP ({$rop}). Recommended EOQ: {$eoq} units.",
                'requester_id' => $user->id,
                'cost_center_id' => $costCenter->id,
                'procurement_category_id' => $category->id,
                'procurement_method' => 'shopping',
                'total_estimated_amount' => $totalEst,
                'currency' => 'PHP',
                'priority' => ($atp <= 0) ? 'high' : 'medium',
                'status' => RequisitionStatus::Draft->value,
                'is_emergency' => false,
            ]);

            PurchaseRequestLine::create([
                'purchase_request_id' => $pr->id,
                'item_id' => $lockedItem->id,
                'line_number' => 1,
                'item_description' => $lockedItem->name . ' (' . $lockedItem->sku . ')',
                'quantity' => $eoq,
                'uom' => $lockedItem->unit ?? 'pcs',
                'estimated_unit_price' => $lockedItem->unit_cost ?? 10.00,
                'estimated_total_price' => $totalEst,
                'need_by_date' => now()->addDays($lockedItem->lead_time_days > 0 ? $lockedItem->lead_time_days : 7),
                'is_contracted_catalog' => false,
            ]);

            if ($user) {
                $this->auditLogger->record(
                    AuditAction::CreatedPurchaseRequest,
                    actor: $user,
                    target: $pr,
                    description: "Instantiated draft Purchase Request {$pr->pr_number} for {$lockedItem->name} (EOQ: {$eoq})",
                    newValues: [
                        'pr_number' => $pr->pr_number,
                        'item_id' => $lockedItem->id,
                        'atp' => $atp,
                        'on_order' => $onOrder,
                        'rop' => $rop,
                        'eoq' => $eoq,
                    ]
                );
            }

            return $pr;
        });
    }
}
