<?php

namespace Database\Seeders;

use App\Enums\SupplierDocumentStatus;
use App\Enums\UserRole;
use App\Models\CycleCountDoc;
use App\Models\InventoryAdjustment;
use App\Models\InventoryItem;
use App\Models\InventoryShrinkageReport;
use App\Models\ItemBatch;
use App\Models\KpiProcessReview;
use App\Models\ProcessRecommendation;
use App\Models\ProcurementSavingsLog;
use App\Models\PurchaseOrder;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\SupplierContract;
use App\Models\SupplierDocument;
use App\Models\SupplierScorecard;
use App\Models\User;
use App\Support\DemoPdfBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class OperationalMetricsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::active()->role(UserRole::SuperAdministrator)->oldest('id')->first();
        $admin = User::active()->role(UserRole::Administrator)->oldest('id')->first();
        $inventoryManager = User::active()->role(UserRole::InventoryManager)->oldest('id')->first();
        $warehouseStaff = User::active()->role(UserRole::WarehouseStaff)->oldest('id')->first();
        $pharmacyStaff = User::active()->role(UserRole::PharmacyStaff)->oldest('id')->first();

        // 1. Seed KPI Process Reviews
        $evaluatorId = $inventoryManager?->id ?? $admin?->id ?? 1;
        $approverId = $superAdmin?->id ?? $admin?->id ?? 1;

        $reviewQ3 = KpiProcessReview::updateOrCreate(
            ['review_number' => 'REV-2026-Q3'],
            [
                'title' => 'Q3 2026 Clinical Sourcing & Hospital Pharmacy Supply Chain Review',
                'period_start' => '2026-07-01',
                'period_end' => '2026-09-30',
                'evaluator_id' => $evaluatorId,
                'status' => 'approved',
                'approved_by_id' => $approverId,
                'approved_at' => now()->subDays(5),
                'qualitative_context' => 'Quarterly multi-department review assessing DOH Drug Price Reference Index (DPRI) price ceilings, critical antibiotic batch replenishment, cold chain compliance, and hospital inventory shrinkage.',
                'executive_summary' => 'Procurement realized ₱42,500 savings under DPRI ceilings across essential anti-infectives and analgesics. Inventory shrinkage was maintained below the 0.35% hospital tolerance threshold.',
                'metrics_summary' => [
                    'total_spend' => 1250000.00,
                    'total_spend_evaluated' => 1250000.00,
                    'net_savings_amount' => 42500.00,
                    'aggregate_savings_pct' => 3.4,
                    'dpri_compliance_pct' => 98.4,
                    'fill_rate_pct' => 94.8,
                    'on_time_delivery_pct' => 92.1,
                    'ceiling_breaches_count' => 0,
                    'suppliers_evaluated' => 3,
                    'avg_supplier_score' => 91.7,
                    'overall_stock_accuracy' => 99.6,
                    'critical_bottleneck' => [
                        'key' => 'stage_3_po_conforme',
                        'name' => 'PO Issuance to Conforme',
                        'mean_tat_days' => 2.8,
                        'sla_target_days' => 2.0,
                        'variance_sigma' => 1.42,
                    ],
                    'bottleneck_stages' => [
                        'stage_1_pr_approval' => [
                            'name' => 'PR Creation to Approval',
                            'sample_count' => 18,
                            'mean_tat_days' => 2.40,
                            'min_tat_days' => 0.80,
                            'max_tat_days' => 4.50,
                            'variance_sigma' => 1.15,
                            'sla_target_days' => 3.0,
                            'sla_breach_rate' => 5.56,
                            'status' => 'healthy',
                        ],
                        'stage_2_sourcing_award' => [
                            'name' => 'Sourcing RFQ to Award',
                            'sample_count' => 8,
                            'mean_tat_days' => 5.80,
                            'min_tat_days' => 3.00,
                            'max_tat_days' => 9.20,
                            'variance_sigma' => 1.85,
                            'sla_target_days' => 7.0,
                            'sla_breach_rate' => 12.50,
                            'status' => 'healthy',
                        ],
                        'stage_3_po_conforme' => [
                            'name' => 'PO Issuance to Conforme',
                            'sample_count' => 24,
                            'mean_tat_days' => 2.80,
                            'min_tat_days' => 1.00,
                            'max_tat_days' => 5.50,
                            'variance_sigma' => 1.42,
                            'sla_target_days' => 2.0,
                            'sla_breach_rate' => 25.00,
                            'status' => 'elevated',
                        ],
                        'stage_4_vendor_lead_time' => [
                            'name' => 'Dispatch to Gate Arrival (Lead Time)',
                            'sample_count' => 26,
                            'mean_tat_days' => 5.40,
                            'min_tat_days' => 2.00,
                            'max_tat_days' => 8.50,
                            'variance_sigma' => 1.95,
                            'sla_target_days' => 10.0,
                            'sla_breach_rate' => 0.00,
                            'status' => 'healthy',
                        ],
                        'stage_5_technical_inspection' => [
                            'name' => 'Receiving to Technical Inspection',
                            'sample_count' => 26,
                            'mean_tat_days' => 0.80,
                            'min_tat_days' => 0.20,
                            'max_tat_days' => 1.50,
                            'variance_sigma' => 0.35,
                            'sla_target_days' => 1.0,
                            'sla_breach_rate' => 3.85,
                            'status' => 'healthy',
                        ],
                        'stage_6_custodial_acceptance' => [
                            'name' => 'Inspection to Custodial Acceptance',
                            'sample_count' => 26,
                            'mean_tat_days' => 0.60,
                            'min_tat_days' => 0.10,
                            'max_tat_days' => 1.20,
                            'variance_sigma' => 0.25,
                            'sla_target_days' => 1.0,
                            'sla_breach_rate' => 0.00,
                            'status' => 'healthy',
                        ],
                    ],
                ],
            ]
        );

        $reviewQ4 = KpiProcessReview::updateOrCreate(
            ['review_number' => 'REV-2026-Q4'],
            [
                'title' => 'Q4 2026 Pre-Audit Sourcing & Inventory Shrinkage Governance Review',
                'period_start' => '2026-10-01',
                'period_end' => '2026-12-31',
                'evaluator_id' => $evaluatorId,
                'status' => 'submitted',
                'approved_by_id' => null,
                'approved_at' => null,
                'qualitative_context' => 'Preliminary pre-audit review focused on surgical consignment supplies, cold chain IoT monitoring, and vendor accreditation renewals.',
                'executive_summary' => 'Identified 3 active suppliers requiring license to operate renewal and 2 cold storage zones needing telemetry sensor recalibration. Preliminary Q4 procurement realized ₱18,500 savings under DPRI ceilings.',
                'metrics_summary' => [
                    'total_spend' => 450000.00,
                    'total_spend_evaluated' => 450000.00,
                    'net_savings_amount' => 18500.00,
                    'aggregate_savings_pct' => 4.1,
                    'dpri_compliance_pct' => 96.0,
                    'fill_rate_pct' => 91.5,
                    'on_time_delivery_pct' => 89.0,
                    'ceiling_breaches_count' => 0,
                    'suppliers_evaluated' => 3,
                    'avg_supplier_score' => 88.3,
                    'overall_stock_accuracy' => 98.8,
                    'critical_bottleneck' => [
                        'key' => 'stage_4_vendor_lead_time',
                        'name' => 'Dispatch to Gate Arrival (Lead Time)',
                        'mean_tat_days' => 6.8,
                        'sla_target_days' => 10.0,
                        'variance_sigma' => 2.80,
                    ],
                    'bottleneck_stages' => [
                        'stage_1_pr_approval' => [
                            'name' => 'PR Creation to Approval',
                            'sample_count' => 12,
                            'mean_tat_days' => 2.60,
                            'min_tat_days' => 1.00,
                            'max_tat_days' => 4.80,
                            'variance_sigma' => 1.20,
                            'sla_target_days' => 3.0,
                            'sla_breach_rate' => 8.33,
                            'status' => 'healthy',
                        ],
                        'stage_2_sourcing_award' => [
                            'name' => 'Sourcing RFQ to Award',
                            'sample_count' => 6,
                            'mean_tat_days' => 6.20,
                            'min_tat_days' => 3.50,
                            'max_tat_days' => 8.00,
                            'variance_sigma' => 1.65,
                            'sla_target_days' => 7.0,
                            'sla_breach_rate' => 16.67,
                            'status' => 'healthy',
                        ],
                        'stage_3_po_conforme' => [
                            'name' => 'PO Issuance to Conforme',
                            'sample_count' => 15,
                            'mean_tat_days' => 1.80,
                            'min_tat_days' => 0.80,
                            'max_tat_days' => 3.00,
                            'variance_sigma' => 0.75,
                            'sla_target_days' => 2.0,
                            'sla_breach_rate' => 6.67,
                            'status' => 'healthy',
                        ],
                        'stage_4_vendor_lead_time' => [
                            'name' => 'Dispatch to Gate Arrival (Lead Time)',
                            'sample_count' => 15,
                            'mean_tat_days' => 6.80,
                            'min_tat_days' => 3.00,
                            'max_tat_days' => 12.00,
                            'variance_sigma' => 2.80,
                            'sla_target_days' => 10.0,
                            'sla_breach_rate' => 13.33,
                            'status' => 'elevated',
                        ],
                        'stage_5_technical_inspection' => [
                            'name' => 'Receiving to Technical Inspection',
                            'sample_count' => 14,
                            'mean_tat_days' => 0.90,
                            'min_tat_days' => 0.30,
                            'max_tat_days' => 1.80,
                            'variance_sigma' => 0.40,
                            'sla_target_days' => 1.0,
                            'sla_breach_rate' => 7.14,
                            'status' => 'healthy',
                        ],
                        'stage_6_custodial_acceptance' => [
                            'name' => 'Inspection to Custodial Acceptance',
                            'sample_count' => 14,
                            'mean_tat_days' => 0.70,
                            'min_tat_days' => 0.20,
                            'max_tat_days' => 1.10,
                            'variance_sigma' => 0.28,
                            'sla_target_days' => 1.0,
                            'sla_breach_rate' => 0.00,
                            'status' => 'healthy',
                        ],
                    ],
                ],
            ]
        );

        // 2. Seed Supplier Scorecards
        $suppliers = Supplier::take(3)->get();
        if ($suppliers->isNotEmpty()) {
            $scorecardData = [
                [
                    'delivery_score' => 94.50,
                    'quality_score' => 98.00,
                    'fill_rate_score' => 96.00,
                    'total_score' => 95.80,
                    'total_pos_count' => 12,
                    'completed_pos_count' => 12,
                    'late_deliveries_count' => 1,
                    'avg_lead_time_days' => 4.20,
                    'promised_lead_time_days' => 5.00,
                    'non_conformance_count' => 0,
                    'temperature_excursions_count' => 0,
                    'has_valid_lto' => true,
                    'has_valid_cpr' => true,
                    'recommendation' => 'retain',
                    'notes' => 'Consistently reliable on sterile surgical supplies and hospital consumables.',
                ],
                [
                    'delivery_score' => 91.00,
                    'quality_score' => 95.00,
                    'fill_rate_score' => 92.50,
                    'total_score' => 92.70,
                    'total_pos_count' => 8,
                    'completed_pos_count' => 8,
                    'late_deliveries_count' => 1,
                    'avg_lead_time_days' => 5.10,
                    'promised_lead_time_days' => 5.00,
                    'non_conformance_count' => 0,
                    'temperature_excursions_count' => 0,
                    'has_valid_lto' => true,
                    'has_valid_cpr' => true,
                    'recommendation' => 'retain',
                    'notes' => 'Primary cooperative supplier for non-regulated hospital warehouse supplies.',
                ],
                [
                    'delivery_score' => 86.00,
                    'quality_score' => 89.00,
                    'fill_rate_score' => 84.00,
                    'total_score' => 86.50,
                    'total_pos_count' => 6,
                    'completed_pos_count' => 5,
                    'late_deliveries_count' => 2,
                    'avg_lead_time_days' => 7.50,
                    'promised_lead_time_days' => 5.00,
                    'non_conformance_count' => 1,
                    'temperature_excursions_count' => 0,
                    'has_valid_lto' => true,
                    'has_valid_cpr' => true,
                    'recommendation' => 'under_observation',
                    'notes' => 'Lead time delays observed during recent adverse weather; recommended buffer inventory increase.',
                ],
            ];

            foreach ($suppliers as $idx => $sup) {
                if (isset($scorecardData[$idx])) {
                    SupplierScorecard::updateOrCreate(
                        [
                            'kpi_process_review_id' => $reviewQ3->id,
                            'supplier_id' => $sup->id,
                        ],
                        $scorecardData[$idx]
                    );

                    // Also seed active supplier scorecards for Q4 review
                    SupplierScorecard::updateOrCreate(
                        [
                            'kpi_process_review_id' => $reviewQ4->id,
                            'supplier_id' => $sup->id,
                        ],
                        array_merge($scorecardData[$idx], [
                            'recommendation' => $idx === 2 ? 'under_observation' : 'retain',
                            'notes' => 'Preliminary Q4 evaluation: Vendor accreditation and License to Operate (LTO) renewal pending.',
                        ])
                    );
                }
            }
        }

        // 3. Seed Procurement Savings Logs
        $pos = PurchaseOrder::where('status', '!=', 'cancelled')->take(3)->get();
        $items = InventoryItem::take(3)->get();

        if ($pos->isNotEmpty() && $items->isNotEmpty()) {
            $savingsDataQ3 = [
                [
                    'pndf_code' => 'PNDF-PARA-500',
                    'item_name' => 'Paracetamol 500mg Tablets',
                    'uom' => 'bottle',
                    'quantity_procured' => 25000,
                    'actual_unit_price' => 1.0500,
                    'dpri_ceiling_price' => 1.7500,
                    'variance_amount' => 17500.0000,
                    'savings_percentage' => 40.00,
                    'is_above_ceiling' => false,
                    'justification' => 'Bulk tier hospital negotiated rate below DOH DPRI 2026 reference ceiling price.',
                ],
                [
                    'pndf_code' => 'PNDF-AMOX-500',
                    'item_name' => 'Amoxicillin 500mg Capsules',
                    'uom' => 'box',
                    'quantity_procured' => 10000,
                    'actual_unit_price' => 3.1000,
                    'dpri_ceiling_price' => 5.6000,
                    'variance_amount' => 25000.0000,
                    'savings_percentage' => 44.64,
                    'is_above_ceiling' => false,
                    'justification' => 'Framework supply contract pricing with cooperative distributor.',
                ],
            ];

            foreach ($savingsDataQ3 as $i => $sData) {
                $po = $pos[$i % $pos->count()];
                $item = $items[$i % $items->count()];

                ProcurementSavingsLog::updateOrCreate(
                    [
                        'kpi_process_review_id' => $reviewQ3->id,
                        'inventory_item_id' => $item->id,
                    ],
                    array_merge($sData, [
                        'purchase_order_id' => $po->id,
                        'purchase_order_line_id' => null,
                        'item_name' => $item->name,
                        'uom' => $item->unit ?: 'box',
                    ])
                );
            }

            // Q4 Preliminary Savings Log
            $poQ4 = $pos->last() ?? $pos->first();
            $itemQ4 = $items->last() ?? $items->first();
            ProcurementSavingsLog::updateOrCreate(
                [
                    'kpi_process_review_id' => $reviewQ4->id,
                    'inventory_item_id' => $itemQ4->id,
                ],
                [
                    'purchase_order_id' => $poQ4->id,
                    'purchase_order_line_id' => null,
                    'pndf_code' => 'PNDF-SVR-GLV',
                    'item_name' => $itemQ4->name,
                    'uom' => $itemQ4->unit ?: 'box',
                    'quantity_procured' => 5000,
                    'actual_unit_price' => 28.3000,
                    'dpri_ceiling_price' => 32.0000,
                    'variance_amount' => 18500.0000,
                    'savings_percentage' => 11.56,
                    'is_above_ceiling' => false,
                    'justification' => 'Consignment tiered hospital volume rate under DOH DPRI 2026 pricing.',
                ]
            );
        }

        // 4. Seed Inventory Shrinkage Reports
        $locations = StorageLocation::where('status', 'active')->take(2)->get();
        $cycleDoc = CycleCountDoc::first();

        if ($locations->isNotEmpty() && $items->isNotEmpty()) {
            $shrinkageData = [
                [
                    'ledger_book_quantity' => 500.00,
                    'physical_counted_quantity' => 498.00,
                    'shrinkage_quantity' => 2.00,
                    'shrinkage_rate_pct' => 0.40,
                    'unit_cost' => 1.05,
                    'total_loss_value' => 2.10,
                    'shrinkage_reason' => 'handling_damage',
                    'requires_admin_escalation' => false,
                    'notes' => 'Two tablet blister packs dropped and cracked during shelf replenishment; logged in ward incident log.',
                ],
                [
                    'ledger_book_quantity' => 1200.00,
                    'physical_counted_quantity' => 1200.00,
                    'shrinkage_quantity' => 0.00,
                    'shrinkage_rate_pct' => 0.00,
                    'unit_cost' => 25.00,
                    'total_loss_value' => 0.00,
                    'shrinkage_reason' => 'none',
                    'requires_admin_escalation' => false,
                    'notes' => 'Zero variance match during Q3 blind cycle count.',
                ],
            ];

            foreach ($shrinkageData as $j => $shData) {
                $item = $items[$j % $items->count()];
                $loc = $locations[$j % $locations->count()];

                InventoryShrinkageReport::updateOrCreate(
                    [
                        'kpi_process_review_id' => $reviewQ3->id,
                        'storage_location_id' => $loc->id,
                        'inventory_item_id' => $item->id,
                    ],
                    array_merge($shData, [
                        'cycle_count_doc_id' => $cycleDoc?->id,
                    ])
                );
            }
        }

        // 5. Seed Process Recommendations
        $recommendations = [
            [
                'category' => 'warehousing',
                'target_name' => 'Cold Storage Vaccine Vault (Zone C)',
                'problem_detected' => 'Transient telemetry dropouts observed during monthly battery backup test cycle.',
                'evidence_metrics' => ['dropout_duration_minutes' => 12, 'temperature_drift_c' => 0.4],
                'root_cause_analysis' => 'Legacy sensor telemetry backup batteries nearing end of 3-year operating lifecycle.',
                'recommended_action' => 'Deploy redundant dual-probe digital data logger with automated SMS gateway failover.',
                'expected_operational_benefit' => 'Guarantees 24/7 continuous temperature logging and PhilHealth cold chain accreditation compliance.',
                'priority' => 'critical',
                'status' => 'pending',
                'implemented_by_id' => null,
                'implemented_at' => null,
                'implementation_notes' => null,
            ],
            [
                'category' => 'procurement',
                'target_name' => 'DOH DPRI Ceiling Price Enforcement',
                'problem_detected' => 'Manual ceiling price verification created bottlenecks in urgent pharmacy purchase orders.',
                'evidence_metrics' => ['avg_po_delay_hours' => 18.5, 'audited_pos_count' => 24],
                'root_cause_analysis' => 'Procurement officers were cross-referencing PDF pricelists manually without automated system checks.',
                'recommended_action' => 'Enable automated DPRI ceiling lock in Purchase Order generation workflow.',
                'expected_operational_benefit' => 'Eliminates risk of audit disallowances from COA / DOH price ceiling exceedances.',
                'priority' => 'high',
                'status' => 'implemented',
                'implemented_by_id' => $superAdmin?->id,
                'implemented_at' => now()->subDays(2),
                'implementation_notes' => 'Configured DPRI validation check in purchase request approval pipeline.',
            ],
            [
                'category' => 'inventory',
                'target_name' => 'Central Pharmacy Fast-Moving Pick Face',
                'problem_detected' => 'High travel time for pharmacy staff retrieving common analgesics and oral antibiotics.',
                'evidence_metrics' => ['daily_dispense_trips' => 140, 'avg_retrieval_seconds' => 95],
                'root_cause_analysis' => 'Fast-moving Class A items dispersed across outer racking aisles.',
                'recommended_action' => 'Re-slot fast-moving Class A items to Golden Zone shelves 2 and 3 near dispatch counter.',
                'expected_operational_benefit' => 'Estimated 22% reduction in requisition fulfillment cycle time.',
                'priority' => 'medium',
                'status' => 'pending',
                'implemented_by_id' => null,
                'implemented_at' => null,
                'implementation_notes' => null,
            ],
        ];

        foreach ($recommendations as $rec) {
            ProcessRecommendation::updateOrCreate(
                [
                    'kpi_process_review_id' => $reviewQ3->id,
                    'target_name' => $rec['target_name'],
                ],
                $rec
            );
        }

        $recommendationsQ4 = [
            [
                'category' => 'warehousing',
                'target_name' => 'Cold Storage Telemetry Sensor Recalibration (Zone A & B)',
                'problem_detected' => 'Two cold storage zones exhibit telemetry drift and require sensor recalibration prior to audit.',
                'evidence_metrics' => ['telemetry_drift_c' => 0.6, 'affected_zones_count' => 2],
                'root_cause_analysis' => 'Annual sensor calibration cycle due for renewal.',
                'recommended_action' => 'Dispatch certified biomedical calibration team to re-zero telemetry thermal probes.',
                'expected_operational_benefit' => 'Maintains PhilHealth cold chain accreditation and zero temperature excursions.',
                'priority' => 'high',
                'status' => 'pending',
                'implemented_by_id' => null,
                'implemented_at' => null,
                'implementation_notes' => null,
            ],
            [
                'category' => 'procurement',
                'target_name' => 'Supplier License to Operate (LTO) Accreditation Renewal',
                'problem_detected' => '3 active vendor licenses expiring within 45 days requiring BAC renewal verification.',
                'evidence_metrics' => ['expiring_suppliers_count' => 3, 'days_to_expiry' => 38],
                'root_cause_analysis' => 'Annual FDA Philippine regulatory vendor license renewals.',
                'recommended_action' => 'Issue formal compliance notice to vendors for updated LTO and CPR certificate submission.',
                'expected_operational_benefit' => 'Ensures zero supply disruption on critical consignment surgical consumables.',
                'priority' => 'high',
                'status' => 'pending',
                'implemented_by_id' => null,
                'implemented_at' => null,
                'implementation_notes' => null,
            ],
        ];

        foreach ($recommendationsQ4 as $recQ4) {
            ProcessRecommendation::updateOrCreate(
                [
                    'kpi_process_review_id' => $reviewQ4->id,
                    'target_name' => $recQ4['target_name'],
                ],
                $recQ4
            );
        }

        // 6. Seed Inventory Adjustments
        if ($items->isNotEmpty() && $locations->isNotEmpty()) {
            $batch = ItemBatch::first();
            $loc = $locations->first();

            $adjustments = [
                [
                    'adjustment_number' => 'ADJ-2026-0001',
                    'item_id' => $items[0]->id,
                    'storage_location_id' => $loc->id,
                    'item_batch_id' => $batch?->id,
                    'current_quantity' => 450,
                    'adjustment_quantity' => -2,
                    'resulting_quantity' => 448,
                    'unit_cost' => 1.05,
                    'total_variance_value' => -2.10,
                    'adjustment_type' => 'damage',
                    'reason_code' => 'damage',
                    'explanation' => 'Two tablet blister strips crushed during ward cart transport.',
                    'status' => 'posted',
                    'requested_by_id' => $pharmacyStaff?->id ?? $admin->id,
                    'approved_by_id' => $inventoryManager?->id ?? $admin->id,
                    'second_approved_by_id' => null,
                    'posted_at' => now()->subDays(3),
                    'rejection_reason' => null,
                ],
                [
                    'adjustment_number' => 'ADJ-2026-0002',
                    'item_id' => ($items->count() > 1 ? $items[1]->id : $items[0]->id),
                    'storage_location_id' => $loc->id,
                    'item_batch_id' => $batch?->id,
                    'current_quantity' => 1200,
                    'adjustment_quantity' => 50,
                    'resulting_quantity' => 1250,
                    'unit_cost' => 25.00,
                    'total_variance_value' => 1250.00,
                    'adjustment_type' => 'count_variance',
                    'reason_code' => 'count_variance',
                    'explanation' => 'Found unopened carton in overflow mezzanine during annual blind cycle count.',
                    'status' => 'approved',
                    'requested_by_id' => $warehouseStaff?->id ?? $admin->id,
                    'approved_by_id' => $inventoryManager?->id ?? $admin->id,
                    'second_approved_by_id' => null,
                    'posted_at' => null,
                    'rejection_reason' => null,
                ],
                [
                    'adjustment_number' => 'ADJ-2026-0003',
                    'item_id' => ($items->count() > 2 ? $items[2]->id : $items[0]->id),
                    'storage_location_id' => $loc->id,
                    'item_batch_id' => $batch?->id,
                    'current_quantity' => 600,
                    'adjustment_quantity' => -5,
                    'resulting_quantity' => 595,
                    'unit_cost' => 45.00,
                    'total_variance_value' => -225.00,
                    'adjustment_type' => 'expiry',
                    'reason_code' => 'expiry',
                    'explanation' => 'Five boxes identified past expiration date during quarantine inspection.',
                    'status' => 'pending_approval',
                    'requested_by_id' => $warehouseStaff?->id ?? $admin->id,
                    'approved_by_id' => null,
                    'second_approved_by_id' => null,
                    'posted_at' => null,
                    'rejection_reason' => null,
                ],
            ];

            foreach ($adjustments as $adj) {
                InventoryAdjustment::updateOrCreate(
                    ['adjustment_number' => $adj['adjustment_number']],
                    $adj
                );
            }
        }

        // 7. Seed Supplier Contacts, Contracts & Documents
        $primarySupplier = Supplier::first();
        if ($primarySupplier) {
            // Contacts
            $contacts = [
                [
                    'name' => 'Rowena Bautista',
                    'contact_type' => 'primary',
                    'position' => 'Senior Hospital Key Account Executive',
                    'email' => 'r.bautista@medsupply.ph',
                    'phone' => '+63 2 8920 4401',
                    'mobile' => '+63 917 555 1201',
                    'is_primary' => true,
                    'is_active' => true,
                ],
                [
                    'name' => 'Engr. Michael Tan',
                    'contact_type' => 'technical',
                    'position' => 'Quality & Regulatory Assurance Director',
                    'email' => 'm.tan@medsupply.ph',
                    'phone' => '+63 2 8920 4405',
                    'mobile' => '+63 918 555 3302',
                    'is_primary' => false,
                    'is_active' => true,
                ],
            ];

            foreach ($contacts as $contact) {
                SupplierContact::updateOrCreate(
                    [
                        'supplier_id' => $primarySupplier->id,
                        'email' => $contact['email'],
                    ],
                    $contact
                );
            }

            // Contracts
            SupplierContract::updateOrCreate(
                [
                    'supplier_id' => $primarySupplier->id,
                    'contract_number' => 'CNT-2026-MED-001',
                ],
                [
                    'contract_type' => 'Master Framework Agreement',
                    'starts_at' => '2026-01-01',
                    'ends_at' => '2026-12-31',
                    'status' => 'active',
                    'payment_terms' => 'Net 30 days after complete DOH Inspection Acceptance Report',
                    'delivery_terms' => 'F.O.R. Hospital Central Receiving Dock',
                    'responsible_user_id' => $admin?->id ?? 1,
                    'notes' => 'Annual hospital supply contract covering sterile PPE, gloves, and surgical consumables.',
                ]
            );

            // Documents
            Storage::disk('local')->makeDirectory('documents/suppliers');
            $fdaPdf = DemoPdfBuilder::create(
                title: 'FOOD AND DRUG ADMINISTRATION PHILIPPINES - LICENSE TO OPERATE',
                sections: [
                    [
                        'heading' => 'LICENSE & REGISTRATION PARTICULARS',
                        'lines' => [
                            'LTO Number: CDRR-NCR-DI/W-104928 | Status: Active & Valid',
                            'Validity Period: 2024-05-15 to 2027-05-14 (3-Year License Cycle)',
                            'Establishment Name: MedSupply Premier Hospital Consumables, Inc.',
                            'Authorized Activity: Wholesaler / Distributor of Medical Devices and Consumables',
                            'Address: Sta. Rosa Commercial Complex, Santa Rosa, Laguna, Philippines',
                        ],
                    ],
                    [
                        'heading' => 'REGULATORY COMPLIANCE & ACCREDITATION CONDITIONS',
                        'lines' => [
                            'Issuing Authority: Food and Drug Administration (FDA) Philippines - CDRR',
                            'Supervising Pharmacist: Registered Pharmacist PRC License # 0049182',
                            'Inspection Result: Compliant with Good Distribution Practices (GDP Standards)',
                            'HIMS Verification: Verified against official FDA Verification Portal with Zero Deficiencies.',
                        ],
                    ],
                ],
                subtitle: 'Department of Health | Center for Device Regulation, Radiation Health, and Research'
            );
            $fdaDocPath = 'documents/suppliers/medsupply_lto_2024_2027.pdf';
            Storage::disk('local')->put($fdaDocPath, $fdaPdf);

            SupplierDocument::updateOrCreate(
                [
                    'supplier_id' => $primarySupplier->id,
                    'document_number' => 'CDRR-NCR-DI/W-104928',
                ],
                [
                    'document_type' => 'FDA_LTO',
                    'issued_at' => '2024-05-15',
                    'expires_at' => '2027-05-14',
                    'issuing_authority' => 'Food and Drug Administration (FDA) Philippines',
                    'disk' => 'local',
                    'path' => $fdaDocPath,
                    'original_name' => 'FDA_LTO_MedSupply_2027.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => strlen($fdaPdf),
                    'verification_status' => SupplierDocumentStatus::Verified,
                    'required_for_accreditation' => true,
                    'blocks_procurement_when_invalid' => true,
                    'uploaded_by' => $inventoryManager?->id ?? $admin?->id,
                    'verified_by' => $admin?->id ?? $superAdmin?->id,
                    'verified_at' => now()->subMonths(6),
                    'review_notes' => 'Verified against FDA Verification Portal; active and valid License to Operate.',
                    'notes' => 'Wholesaler/distributor of medical devices and supplies.',
                    'is_current' => true,
                ]
            );
        }
    }
}
