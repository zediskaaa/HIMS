<?php

namespace App\Services\Analytics;

use App\Models\InspectionAcceptanceReport;
use App\Models\IoTTelemetryLog;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SupplierScoringService
{
    /**
     * Compute multi-criteria performance scores for suppliers with PO activity during the period.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function evaluate(Carbon $startDate, Carbon $endDate): Collection
    {
        $suppliers = Supplier::query()
            ->with(['documents' => fn ($q) => $q->where('is_current', true)])
            ->whereHas('purchaseOrders', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);
            })
            ->get();

        $results = collect();

        foreach ($suppliers as $supplier) {
            $pos = PurchaseOrder::query()
                ->where('supplier_id', $supplier->id)
                ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->with(['lines', 'inspectionAcceptanceReports'])
                ->get();

            if ($pos->isEmpty()) {
                continue;
            }

            $totalPos = $pos->count();
            $completedPos = $pos->filter(fn ($po) => $po->isFullyReceived() || in_array($po->status, ['received', 'fulfilled'], true))->count();

            // 1. Delivery Performance (40% Weight)
            $iars = InspectionAcceptanceReport::query()
                ->where('supplier_id', $supplier->id)
                ->whereBetween('iar_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->get();

            $lateDeliveries = 0;
            $leadTimes = [];
            $promisedLeadTimes = [];

            foreach ($pos as $po) {
                $promisedLead = $supplier->standard_lead_time_days ?? 7;
                $promisedLeadTimes[] = $promisedLead;

                if ($po->received_at && $po->dispatched_at) {
                    $actualLead = max(0, $po->dispatched_at->diffInDays($po->received_at));
                    $leadTimes[] = $actualLead;
                } elseif ($po->received_at && $po->created_at) {
                    $leadTimes[] = max(0, $po->created_at->diffInDays($po->received_at));
                }

                if ($po->delivery_date && $po->received_at && $po->received_at->gt($po->delivery_date)) {
                    $lateDeliveries++;
                }
            }

            // Also check IAR days_delayed
            foreach ($iars as $iar) {
                if ($iar->days_delayed > 0) {
                    $lateDeliveries++;
                }
            }

            $avgLeadTime = count($leadTimes) > 0 ? array_sum($leadTimes) / count($leadTimes) : (float) ($supplier->standard_lead_time_days ?? 7);
            $avgPromisedLead = count($promisedLeadTimes) > 0 ? array_sum($promisedLeadTimes) / count($promisedLeadTimes) : 7.0;

            $onTimeCount = max(0, $totalPos - $lateDeliveries);
            $deliveryRatio = $totalPos > 0 ? ($onTimeCount / $totalPos) : 1.0;
            $deliveryScore = round(min(40.0, max(0.0, $deliveryRatio * 40.0)), 2);

            // 2. Quality & Regulatory Compliance (40% Weight)
            $nonConformanceCount = 0;
            $qualityBase = 40.0;

            foreach ($iars as $iar) {
                if ($iar->inspection_status === 'rejected') {
                    $nonConformanceCount += 2;
                    $qualityBase -= 15.0;
                } elseif ($iar->inspection_status === 'passed_with_exceptions') {
                    $nonConformanceCount += 1;
                    $qualityBase -= 5.0;
                }
            }

            // Check Cold Chain Excursions
            $temperatureExcursions = IoTTelemetryLog::query()
                ->where('excursion_status', 'excursion')
                ->whereBetween('recorded_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->whereHas('location.stockLevels.item', function ($q) use ($supplier) {
                    // Check if location holds items supplied by this vendor
                    $q->where('supplier_id', $supplier->id);
                })
                ->count();

            $qualityBase -= ($temperatureExcursions * 10.0);

            // Check FDA LTO & CPR Document Validity
            $ltoDoc = $supplier->documents->first(function (SupplierDocument $doc) {
                return str_contains(strtolower($doc->document_type), 'lto') || str_contains(strtolower($doc->document_type), 'license');
            });
            $cprDoc = $supplier->documents->first(function (SupplierDocument $doc) {
                return str_contains(strtolower($doc->document_type), 'cpr') || str_contains(strtolower($doc->document_type), 'registration');
            });

            $hasValidLto = $ltoDoc ? ! $ltoDoc->isExpired() : true;
            $hasValidCpr = $cprDoc ? ! $cprDoc->isExpired() : true;

            if (! $hasValidLto) {
                $qualityBase -= 15.0;
            }
            if (! $hasValidCpr) {
                $qualityBase -= 15.0;
            }

            $qualityScore = round(min(40.0, max(0.0, $qualityBase)), 2);

            // 3. Fill Rate / Completeness (20% Weight)
            $totalOrderedQty = 0;
            $totalReceivedQty = 0;

            foreach ($pos as $po) {
                foreach ($po->lines as $line) {
                    $totalOrderedQty += $line->ordered_quantity;
                    $totalReceivedQty += $line->received_quantity;
                }
            }

            $fillRateRatio = $totalOrderedQty > 0 ? min(1.0, $totalReceivedQty / $totalOrderedQty) : 1.0;
            $fillRateScore = round(min(20.0, max(0.0, $fillRateRatio * 20.0)), 2);

            $totalScore = round($deliveryScore + $qualityScore + $fillRateScore, 2);

            // Recommendation badge logic
            $recommendation = 'retain';
            if ($totalScore >= 90.0 && $hasValidLto && $hasValidCpr && $temperatureExcursions === 0 && $lateDeliveries === 0) {
                $recommendation = 'preferred';
            } elseif ($totalScore >= 75.0 && $hasValidLto && $hasValidCpr) {
                $recommendation = 'retain';
            } elseif ($totalScore >= 60.0 || ! $hasValidLto || ! $hasValidCpr || $lateDeliveries > 1) {
                $recommendation = 'conditional_capa';
            } else {
                $recommendation = 'suspend';
            }

            $notes = [];
            if ($lateDeliveries > 0) {
                $notes[] = "Recorded {$lateDeliveries} delayed delivery event(s).";
            }
            if ($nonConformanceCount > 0) {
                $notes[] = "Logged {$nonConformanceCount} inspection exception(s).";
            }
            if ($temperatureExcursions > 0) {
                $notes[] = "{$temperatureExcursions} cold-chain excursion(s) detected.";
            }
            if (! $hasValidLto) {
                $notes[] = 'FDA License to Operate (LTO) expired or unverified.';
            }
            if (! $hasValidCpr) {
                $notes[] = 'Certificate of Product Registration (CPR) expired.';
            }
            if (empty($notes)) {
                $notes[] = 'Consistent operational conformance with hospital SLA.';
            }

            $results->push([
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'delivery_score' => $deliveryScore,
                'quality_score' => $qualityScore,
                'fill_rate_score' => $fillRateScore,
                'total_score' => $totalScore,
                'total_pos_count' => $totalPos,
                'completed_pos_count' => $completedPos,
                'late_deliveries_count' => $lateDeliveries,
                'avg_lead_time_days' => round($avgLeadTime, 2),
                'promised_lead_time_days' => round($avgPromisedLead, 2),
                'non_conformance_count' => $nonConformanceCount,
                'temperature_excursions_count' => $temperatureExcursions,
                'has_valid_lto' => $hasValidLto,
                'has_valid_cpr' => $hasValidCpr,
                'recommendation' => $recommendation,
                'notes' => implode(' ', $notes),
            ]);
        }

        return $results;
    }
}
