<?php

namespace App\Services\Analytics;

use App\Models\DpriReferencePrice;
use App\Models\PurchaseOrderLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProcurementSavingsService
{
    /**
     * Analyze procurement price variance against DOH DPRI reference ceilings.
     *
     * @return array{logs: Collection<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function evaluate(Carbon $startDate, Carbon $endDate): array
    {
        $lines = PurchaseOrderLine::query()
            ->with(['purchaseOrder', 'item'])
            ->whereHas('purchaseOrder', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);
            })
            ->get();

        $logs = collect();
        $totalSpend = 0.0;
        $totalBenchmark = 0.0;
        $totalSavings = 0.0;
        $ceilingBreaches = 0;

        foreach ($lines as $line) {
            $item = $line->item;
            if (! $item) {
                continue;
            }

            // Match DPRI by item's pndf_code, or fallback by generic name
            $dpri = null;
            if (! empty($item->pndf_code)) {
                $dpri = DpriReferencePrice::active()
                    ->where('pndf_code', $item->pndf_code)
                    ->orderByDesc('edition_year')
                    ->first();
            }

            if (! $dpri && ! empty($item->generic_name)) {
                $dpri = DpriReferencePrice::active()
                    ->where('drug_name', 'like', '%'.$item->generic_name.'%')
                    ->orderByDesc('edition_year')
                    ->first();
            }

            if (! $dpri) {
                continue;
            }

            $quantity = (float) ($line->received_quantity > 0 ? $line->received_quantity : $line->ordered_quantity);
            if ($quantity <= 0) {
                continue;
            }

            $actualPrice = (float) $line->unit_price;
            $ceilingPrice = (float) $dpri->ceiling_price;
            $lineSpend = $actualPrice * $quantity;
            $benchmarkValue = $ceilingPrice * $quantity;
            $varianceAmount = $benchmarkValue - $lineSpend; // positive = hospital saved money

            $isAboveCeiling = $actualPrice > $ceilingPrice;
            $savingsPct = $ceilingPrice > 0 ? round((($ceilingPrice - $actualPrice) / $ceilingPrice) * 100, 2) : 0.0;

            if ($isAboveCeiling) {
                $ceilingBreaches++;
                $justification = 'Exceeds DOH DPRI ceiling of ₱'.number_format($ceilingPrice, 2).'/'.$dpri->unit_of_measure.'. Non-compliant with DOH AO 2019-0040.';
            } else {
                $justification = 'Compliant with DOH DPRI ceiling price. Procured at or below government reference threshold.';
            }

            $totalSpend += $lineSpend;
            $totalBenchmark += $benchmarkValue;
            $totalSavings += $varianceAmount;

            $logs->push([
                'purchase_order_id' => $line->purchase_order_id,
                'purchase_order_line_id' => $line->id,
                'inventory_item_id' => $item->id,
                'pndf_code' => $dpri->pndf_code,
                'item_name' => $item->name,
                'uom' => $dpri->unit_of_measure ?? $item->unit ?? 'unit',
                'quantity_procured' => $quantity,
                'actual_unit_price' => $actualPrice,
                'dpri_ceiling_price' => $ceilingPrice,
                'variance_amount' => round($varianceAmount, 4),
                'savings_percentage' => $savingsPct,
                'is_above_ceiling' => $isAboveCeiling,
                'justification' => $justification,
            ]);
        }

        $aggregateSavingsPct = $totalBenchmark > 0 ? round(($totalSavings / $totalBenchmark) * 100, 2) : 0.0;

        return [
            'logs' => $logs,
            'summary' => [
                'evaluated_items_count' => $logs->count(),
                'total_spend' => round($totalSpend, 2),
                'total_benchmark_value' => round($totalBenchmark, 2),
                'net_savings_amount' => round($totalSavings, 2),
                'aggregate_savings_pct' => $aggregateSavingsPct,
                'ceiling_breaches_count' => $ceilingBreaches,
            ],
        ];
    }
}
