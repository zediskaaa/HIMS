<?php

namespace App\Services\Analytics;

use App\Models\CycleCountDoc;
use App\Models\CycleCountLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StockAccuracyService
{
    /**
     * Analyze inventory shrinkage and physical count discrepancies based on COA guidelines.
     *
     * @return array{reports: Collection<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function evaluate(Carbon $startDate, Carbon $endDate): array
    {
        $cycleDocs = CycleCountDoc::query()
            ->whereBetween('scheduled_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->whereIn('status', ['completed', 'approved', 'posted'])
            ->with(['lines.item', 'lines.location', 'location'])
            ->get();

        $reports = collect();
        $totalAuditedItems = 0;
        $totalBookQuantity = 0.0;
        $totalCountedQuantity = 0.0;
        $totalLossValue = 0.0;
        $escalationCount = 0;

        foreach ($cycleDocs as $doc) {
            foreach ($doc->lines as $line) {
                $item = $line->item;
                $location = $line->location ?? $doc->location;

                if (! $item || ! $location) {
                    continue;
                }

                $bookQty = (float) $line->book_quantity_snapshot;
                $countedQty = (float) $line->counted_quantity_blind;
                $shrinkageQty = max(0.0, $bookQty - $countedQty);
                $shrinkagePct = $bookQty > 0 ? round(($shrinkageQty / $bookQty) * 100, 2) : 0.0;
                $unitCost = (float) ($item->unit_cost ?? 0.0);
                $lossValue = round($shrinkageQty * $unitCost, 2);

                // COA Circular 2020-006: Any shrinkage > 2% or loss value > 10,000 requires escalation
                $requiresEscalation = ($shrinkagePct > 2.0 && $shrinkageQty > 0) || $lossValue >= 10000.0;

                if ($requiresEscalation) {
                    $escalationCount++;
                    $reason = 'COA Rule 4 Discrepancy (>2% threshold)';
                    $notes = 'Significant inventory shrinkage detected during physical count. Administrative report required under COA Circular 2020-006.';
                } elseif ($shrinkageQty > 0) {
                    $reason = 'Minor count variance (within operational tolerance)';
                    $notes = 'Routine dispensary variance.';
                } else {
                    $reason = '100% Physical Count Reconciliation';
                    $notes = 'Zero count discrepancy.';
                }

                $totalAuditedItems++;
                $totalBookQuantity += $bookQty;
                $totalCountedQuantity += $countedQty;
                $totalLossValue += $lossValue;

                $reports->push([
                    'cycle_count_doc_id' => $doc->id,
                    'storage_location_id' => $location->id,
                    'inventory_item_id' => $item->id,
                    'item_name' => $item->name,
                    'location_name' => $location->name,
                    'ledger_book_quantity' => $bookQty,
                    'physical_counted_quantity' => $countedQty,
                    'shrinkage_quantity' => $shrinkageQty,
                    'shrinkage_rate_pct' => $shrinkagePct,
                    'unit_cost' => $unitCost,
                    'total_loss_value' => $lossValue,
                    'shrinkage_reason' => $reason,
                    'requires_admin_escalation' => $requiresEscalation,
                    'notes' => $notes,
                ]);
            }
        }

        $overallAccuracy = $totalBookQuantity > 0
            ? max(0.0, min(100.0, round((1.0 - ($totalLossValue > 0 ? ($totalBookQuantity - $totalCountedQuantity) / $totalBookQuantity : 0.0)) * 100, 2)))
            : 100.0;

        return [
            'reports' => $reports,
            'summary' => [
                'total_audits_count' => $cycleDocs->count(),
                'total_items_audited' => $totalAuditedItems,
                'total_loss_value' => round($totalLossValue, 2),
                'overall_accuracy_rate' => $overallAccuracy,
                'escalation_count' => $escalationCount,
            ],
        ];
    }
}
