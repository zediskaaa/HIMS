<?php

namespace App\Services\Analytics;

use App\Models\InspectionAcceptanceReport;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SourcingRfq;
use Carbon\Carbon;

class BottleneckAnalysisService
{
    /**
     * Measure turnaround time (TAT) and lead time variance (sigma) across 6 supply chain stages.
     *
     * @return array<string, mixed>
     */
    public function evaluate(Carbon $startDate, Carbon $endDate): array
    {
        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->endOfDay();

        // 1. Stage 1: PR Creation to Approval
        $prs = PurchaseRequest::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('approved_at')
            ->get();
        $stage1Durations = $prs->map(fn ($pr) => max(0.1, $pr->created_at->floatDiffInDays($pr->approved_at)))->values()->all();

        // 2. Stage 2: Sourcing RFQ to Award
        $rfqs = SourcingRfq::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('awarded_at')
            ->get();
        $stage2Durations = $rfqs->map(fn ($rfq) => max(0.1, $rfq->created_at->floatDiffInDays($rfq->awarded_at)))->values()->all();

        // 3. Stage 3: PO Issuance to Supplier Conforme
        $posWithConforme = PurchaseOrder::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('conforme_date')
            ->get();
        $stage3Durations = $posWithConforme->map(function ($po) {
            $dispatch = $po->dispatched_at ?? $po->created_at;

            return max(0.1, $dispatch->floatDiffInDays($po->conforme_date));
        })->values()->all();

        // 4. Stage 4: Dispatch to Warehouse Gate Arrival (Lead Time)
        $posDelivered = PurchaseOrder::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('received_at')
            ->get();
        $stage4Durations = $posDelivered->map(function ($po) {
            $dispatch = $po->dispatched_at ?? $po->created_at;

            return max(0.1, $dispatch->floatDiffInDays($po->received_at));
        })->values()->all();

        // 5. Stage 5: Receiving to Technical Inspection
        $iarsInspected = InspectionAcceptanceReport::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('inspection_date')
            ->get();
        $stage5Durations = $iarsInspected->map(function ($iar) {
            $startPt = $iar->iar_date ?? $iar->created_at;

            return max(0.1, $startPt->floatDiffInDays($iar->inspection_date));
        })->values()->all();

        // 6. Stage 6: IAR Acceptance to Inventory Ledger Posting
        $iarsAccepted = InspectionAcceptanceReport::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('acceptance_date')
            ->whereNotNull('inspection_date')
            ->get();
        $stage6Durations = $iarsAccepted->map(fn ($iar) => max(0.1, $iar->inspection_date->floatDiffInDays($iar->acceptance_date)))->values()->all();

        $stages = [
            'stage_1_pr_approval' => $this->calculateStageStats('PR Creation to Approval', $stage1Durations, 3.0),
            'stage_2_sourcing_award' => $this->calculateStageStats('Sourcing RFQ to Award', $stage2Durations, 7.0),
            'stage_3_po_conforme' => $this->calculateStageStats('PO Issuance to Conforme', $stage3Durations, 2.0),
            'stage_4_vendor_lead_time' => $this->calculateStageStats('Dispatch to Gate Arrival (Lead Time)', $stage4Durations, 10.0),
            'stage_5_technical_inspection' => $this->calculateStageStats('Receiving to Technical Inspection', $stage5Durations, 1.0),
            'stage_6_custodial_acceptance' => $this->calculateStageStats('Inspection to Custodial Acceptance', $stage6Durations, 1.0),
        ];

        // Determine critical bottleneck
        $worstStage = null;
        $highestDelayFactor = -1.0;

        foreach ($stages as $key => $stage) {
            if ($stage['sample_count'] === 0) {
                continue;
            }
            $factor = $stage['mean_tat_days'] / max(0.1, $stage['sla_target_days']);
            if ($factor > $highestDelayFactor) {
                $highestDelayFactor = $factor;
                $worstStage = [
                    'key' => $key,
                    'name' => $stage['name'],
                    'mean_tat_days' => $stage['mean_tat_days'],
                    'sla_target_days' => $stage['sla_target_days'],
                    'variance_sigma' => $stage['variance_sigma'],
                ];
            }
        }

        return [
            'stages' => $stages,
            'critical_bottleneck' => $worstStage,
        ];
    }

    /**
     * @param  array<int, float>  $durations
     * @return array<string, mixed>
     */
    private function calculateStageStats(string $name, array $durations, float $slaTarget): array
    {
        $count = count($durations);

        if ($count === 0) {
            return [
                'name' => $name,
                'sample_count' => 0,
                'mean_tat_days' => 0.0,
                'min_tat_days' => 0.0,
                'max_tat_days' => 0.0,
                'variance_sigma' => 0.0,
                'sla_target_days' => $slaTarget,
                'sla_breach_rate' => 0.0,
                'status' => 'insufficient_data',
            ];
        }

        $mean = array_sum($durations) / $count;
        $min = min($durations);
        $max = max($durations);

        // Standard deviation (sigma)
        $sumSquares = 0.0;
        $breaches = 0;
        foreach ($durations as $d) {
            $sumSquares += pow($d - $mean, 2);
            if ($d > $slaTarget) {
                $breaches++;
            }
        }
        $sigma = sqrt($sumSquares / $count);
        $breachRate = round(($breaches / $count) * 100, 2);

        $status = 'healthy';
        if ($mean > $slaTarget * 1.5 || $sigma > 4.0) {
            $status = 'critical';
        } elseif ($mean > $slaTarget || $sigma > 2.5) {
            $status = 'elevated';
        }

        return [
            'name' => $name,
            'sample_count' => $count,
            'mean_tat_days' => round($mean, 2),
            'min_tat_days' => round($min, 2),
            'max_tat_days' => round($max, 2),
            'variance_sigma' => round($sigma, 2),
            'sla_target_days' => $slaTarget,
            'sla_breach_rate' => $breachRate,
            'status' => $status,
        ];
    }
}
