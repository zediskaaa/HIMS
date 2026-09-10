<?php

namespace App\Services\Analytics;

use App\Models\InventoryItem;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class RecommendationEngine
{
    /**
     * Synthesize cross-domain analytical findings into actionable, evidence-based recommendations.
     *
     * @param  Collection<int, array<string, mixed>>  $supplierScores
     * @param  array<string, mixed>  $savingsData
     * @param  array<string, mixed>  $bottleneckData
     * @param  array<string, mixed>  $stockAccuracyData
     * @return Collection<int, array<string, mixed>>
     */
    public function generate(
        Collection $supplierScores,
        array $savingsData,
        array $bottleneckData,
        array $stockAccuracyData
    ): Collection {
        $recommendations = collect();

        // 1. Supplier Recommendations
        foreach ($supplierScores as $sc) {
            $supplier = Supplier::find($sc['supplier_id']);
            $supplierName = $supplier?->name ?? $sc['supplier_name'];

            if ($sc['recommendation'] === 'suspend') {
                $recommendations->push([
                    'category' => 'vendor_management',
                    'target_type' => Supplier::class,
                    'target_id' => $sc['supplier_id'],
                    'target_name' => $supplierName,
                    'problem_detected' => "Critical vendor failure: Total score {$sc['total_score']}% with {$sc['late_deliveries_count']} late deliveries and {$sc['non_conformance_count']} quality exceptions.",
                    'evidence_metrics' => [
                        'score' => $sc['total_score'],
                        'late_count' => $sc['late_deliveries_count'],
                        'non_conformance' => $sc['non_conformance_count'],
                        'lto_valid' => $sc['has_valid_lto'],
                        'cpr_valid' => $sc['has_valid_cpr'],
                    ],
                    'root_cause_analysis' => 'Vendor repeatedly breached contractual delivery deadlines and technical quality standards.',
                    'recommended_action' => "Suspend vendor {$supplierName} from bidding and active PO issuance pending formal BAC review.",
                    'expected_operational_benefit' => 'Prevents patient care compromise and eliminates stock delivery delays.',
                    'priority' => 'critical',
                ]);
            } elseif ($sc['recommendation'] === 'conditional_capa') {
                $recommendations->push([
                    'category' => 'vendor_management',
                    'target_type' => Supplier::class,
                    'target_id' => $sc['supplier_id'],
                    'target_name' => $supplierName,
                    'problem_detected' => "Sub-optimal supplier performance (Score: {$sc['total_score']}%) with average lead time of {$sc['avg_lead_time_days']} days against {$sc['promised_lead_time_days']} promised days.",
                    'evidence_metrics' => [
                        'score' => $sc['total_score'],
                        'avg_lead_time' => $sc['avg_lead_time_days'],
                        'promised_lead_time' => $sc['promised_lead_time_days'],
                    ],
                    'root_cause_analysis' => 'Logistical slippage and regulatory document renewal bottlenecks.',
                    'recommended_action' => "Issue formal Corrective and Preventive Action (CAPA) demand letter to {$supplierName} requiring resolution within 15 days.",
                    'expected_operational_benefit' => 'Restores supplier accountability and enforces hospital service level agreements.',
                    'priority' => 'high',
                ]);
            }

            // Lead time adjustment recommendation
            if ($sc['avg_lead_time_days'] > ($sc['promised_lead_time_days'] + 2.0)) {
                $recommendations->push([
                    'category' => 'reorder_parameters',
                    'target_type' => Supplier::class,
                    'target_id' => $sc['supplier_id'],
                    'target_name' => $supplierName,
                    'problem_detected' => "Operational lead time drift: Realized average lead time ({$sc['avg_lead_time_days']} days) exceeds system master parameter ({$sc['promised_lead_time_days']} days).",
                    'evidence_metrics' => [
                        'realized_lead_days' => $sc['avg_lead_time_days'],
                        'master_lead_days' => $sc['promised_lead_time_days'],
                        'drift_days' => round($sc['avg_lead_time_days'] - $sc['promised_lead_time_days'], 1),
                    ],
                    'root_cause_analysis' => 'Static master data fails to reflect realistic transit and customs/port turnaround times.',
                    'recommended_action' => "Recalibrate supplier standard lead time in catalogue to {$sc['avg_lead_time_days']} days and recalculate affected item reorder points.",
                    'expected_operational_benefit' => 'Prevents stockouts caused by optimistic lead time assumptions.',
                    'priority' => 'medium',
                ]);
            }
        }

        // 2. DPRI Pricing Ceiling Breaches
        $breachedLogs = $savingsData['logs']->filter(fn ($l) => $l['is_above_ceiling']);
        if ($breachedLogs->isNotEmpty()) {
            $breachedCount = $breachedLogs->count();
            $recommendations->push([
                'category' => 'audit_compliance',
                'target_type' => null,
                'target_id' => null,
                'target_name' => "{$breachedCount} Drug Procurement Line(s)",
                'problem_detected' => "Procurement prices exceeded DOH Drug Price Reference Index (DPRI) ceiling for {$breachedCount} item line(s).",
                'evidence_metrics' => [
                    'breached_lines_count' => $breachedCount,
                    'sample_items' => $breachedLogs->pluck('item_name')->take(3)->values()->all(),
                ],
                'root_cause_analysis' => 'Purchase orders executed without automated pre-validation against active DOH DPRI reference tables.',
                'recommended_action' => 'Mandate BAC price renegotiation with suppliers or file statutory price justification under DOH AO 2019-0040.',
                'expected_operational_benefit' => 'Guarantees compliance with DOH maximum drug retail and hospital price ceiling regulations.',
                'priority' => 'high',
            ]);
        }

        // 3. Bottleneck Analysis
        $criticalStage = $bottleneckData['critical_bottleneck'] ?? null;
        if ($criticalStage && $criticalStage['mean_tat_days'] > $criticalStage['sla_target_days']) {
            $recommendations->push([
                'category' => 'bottleneck_resolution',
                'target_type' => null,
                'target_id' => null,
                'target_name' => $criticalStage['name'],
                'problem_detected' => "Critical process lag in '{$criticalStage['name']}': Mean turnaround time is {$criticalStage['mean_tat_days']} days against SLA target of {$criticalStage['sla_target_days']} days (Variance Sigma: {$criticalStage['variance_sigma']} days).",
                'evidence_metrics' => $criticalStage,
                'root_cause_analysis' => 'Administrative backlog, routing delays, or manual sign-off dependencies.',
                'recommended_action' => "Implement fast-track sign-off protocol or automated notification escalation for {$criticalStage['name']}.",
                'expected_operational_benefit' => 'Reduces total procurement cycle time and accelerates stock availability.',
                'priority' => 'high',
            ]);
        }

        // 4. Inventory Shrinkage & COA Escalations
        $escalatedReports = $stockAccuracyData['reports']->filter(fn ($r) => $r['requires_admin_escalation']);
        if ($escalatedReports->isNotEmpty()) {
            foreach ($escalatedReports as $rep) {
                $recommendations->push([
                    'category' => 'audit_compliance',
                    'target_type' => InventoryItem::class,
                    'target_id' => $rep['inventory_item_id'],
                    'target_name' => "{$rep['item_name']} ({$rep['location_name']})",
                    'problem_detected' => "Excessive inventory shrinkage of {$rep['shrinkage_quantity']} units ({$rep['shrinkage_rate_pct']}%) valued at ₱".number_format($rep['total_loss_value'], 2).' exceeds COA 2% threshold.',
                    'evidence_metrics' => [
                        'shrinkage_qty' => $rep['shrinkage_quantity'],
                        'shrinkage_pct' => $rep['shrinkage_rate_pct'],
                        'loss_value' => $rep['total_loss_value'],
                        'location' => $rep['location_name'],
                    ],
                    'root_cause_analysis' => 'Unrecorded dispensary pilferage, documentation slippage, or physical damage.',
                    'recommended_action' => "Convene Administrative Fact-Finding Board under COA Circular 2020-006 and conduct root-cause security audit for {$rep['location_name']}.",
                    'expected_operational_benefit' => 'Protects public hospital assets and satisfies resident COA auditor compliance.',
                    'priority' => 'critical',
                ]);
            }
        }

        return $recommendations;
    }
}
