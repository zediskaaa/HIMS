<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CostCenter;
use App\Models\CycleCountDoc;
use App\Models\CycleCountLine;
use App\Models\DpriReferencePrice;
use App\Models\InspectionAcceptanceReport;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\KpiProcessReview;
use App\Models\ProcessRecommendation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvidenceBasedProcessReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $evaluator;
    protected User $approver;
    protected User $viewer;
    protected Supplier $supplier;
    protected InventoryItem $item;
    protected StorageLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = User::factory()->create([
            'role' => UserRole::InventoryManager,
        ]);

        $this->approver = User::factory()->create([
            'role' => UserRole::Administrator,
        ]);

        $this->viewer = User::factory()->create([
            'role' => UserRole::Viewer,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Metro Health Pharma Corp '.uniqid(),
            'code' => 'SUP-'.strtoupper(uniqid()),
            'status' => 'active',
            'accreditation_status' => 'approved',
            'standard_lead_time_days' => 5,
        ]);

        $category = ItemCategory::firstOrCreate(
            ['name' => 'Pharmaceuticals'],
            ['code' => 'PHARM', 'description' => 'Medicines']
        );

        $this->location = StorageLocation::create([
            'name' => 'Central Pharmacy Vault '.uniqid(),
            'code' => 'CPV-'.strtoupper(uniqid()),
            'status' => 'active',
            'type' => 'warehouse',
        ]);

        $this->item = InventoryItem::create([
            'name' => 'Amoxicillin 500mg Capsule '.uniqid(),
            'sku' => 'MED-'.strtoupper(uniqid()),
            'category_id' => $category->id,
            'unit' => 'capsule',
            'generic_name' => 'Amoxicillin',
            'pndf_code' => 'PNDF-AMOX-500',
            'unit_cost' => 3.0000,
            'quantity_on_hand' => 1000,
            'supplier_id' => $this->supplier->id,
            'default_location_id' => $this->location->id,
            'status' => 'active',
        ]);

        DpriReferencePrice::updateOrCreate(
            ['pndf_code' => 'PNDF-AMOX-500', 'edition_year' => 2026],
            [
                'drug_name' => 'Amoxicillin',
                'dosage_form_strength' => '500 mg capsule',
                'unit_of_measure' => 'capsule',
                'ceiling_price' => 3.5000,
                'is_active' => true,
            ]
        );
    }

    public function test_viewer_can_view_reviews_list_but_cannot_create(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('reviews.index'));
        $response->assertOk();

        $createResponse = $this->actingAs($this->viewer)->get(route('reviews.create'));
        $createResponse->assertForbidden();
    }

    public function test_cannot_create_review_when_no_operational_data_exists(): void
    {
        // Far future date with zero transactions
        $response = $this->actingAs($this->evaluator)->post(route('reviews.store'), [
            'title' => 'Empty Review Period',
            'period_start' => '2040-01-01',
            'period_end' => '2040-01-31',
        ]);

        $response->assertSessionHasErrors('period_start');
    }

    public function test_data_availability_ajax_pre_check(): void
    {
        $response = $this->actingAs($this->evaluator)->postJson(route('reviews.check-availability'), [
            'period_start' => '2040-01-01',
            'period_end' => '2040-01-31',
        ]);

        $response->assertOk()
            ->assertJson([
                'has_sufficient_data' => false,
                'pos_count' => 0,
            ]);
    }

    public function test_full_evidence_based_review_lifecycle_and_sod(): void
    {
        $costCenter = CostCenter::firstOrCreate(
            ['code' => 'PHARM-CC'],
            ['name' => 'Pharmacy Department', 'department' => 'Pharmacy', 'is_active' => true]
        );

        // 1. Create a PO within current month
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-'.strtoupper(uniqid()),
            'supplier_id' => $this->supplier->id,
            'cost_center_id' => $costCenter->id,
            'item_id' => $this->item->id,
            'quantity' => 200,
            'unit_cost' => 3.00,
            'total_amount' => 600.00,
            'currency' => 'PHP',
            'status' => 'received',
            'delivery_date' => now()->subDays(5),
            'received_at' => now()->subDays(2),
            'dispatched_at' => now()->subDays(10),
            'conforme_date' => now()->subDays(9),
        ]);

        $line = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'item_id' => $this->item->id,
            'line_number' => 1,
            'ordered_quantity' => 200,
            'received_quantity' => 200,
            'unit_price' => 3.0000,
            'total_line_amount' => 600.00,
            'line_status' => 'received',
        ]);

        // 2. Create GRN and IAR
        $grn = \App\Models\GoodsReceiptNote::create([
            'grn_number' => 'GRN-TEST-'.strtoupper(uniqid()),
            'dr_number' => 'DR-TEST-'.strtoupper(uniqid()),
            'purchase_order_id' => $po->id,
            'supplier_id' => $this->supplier->id,
            'received_by_id' => $this->evaluator->id,
            'receipt_status' => 'received',
            'delivery_status' => 'full',
            'received_at' => now()->subDays(2),
        ]);

        $iar = InspectionAcceptanceReport::create([
            'goods_receipt_note_id' => $grn->id,
            'iar_number' => 'IAR-TEST-'.strtoupper(uniqid()),
            'purchase_order_id' => $po->id,
            'supplier_id' => $this->supplier->id,
            'iar_date' => now()->subDays(2),
            'inspection_date' => now()->subDays(1),
            'acceptance_date' => now(),
            'inspection_status' => 'passed',
            'delivery_status' => 'full',
            'status' => 'accepted',
            'days_delayed' => 0,
        ]);

        // 3. Create Cycle Count with 3% shrinkage (exceeds COA 2% threshold)
        $cycleDoc = CycleCountDoc::create([
            'document_number' => 'CC-TEST-'.strtoupper(uniqid()),
            'count_type' => 'blind',
            'scheduled_date' => now()->subDays(1),
            'storage_location_id' => $this->location->id,
            'assigned_counter_id' => $this->evaluator->id,
            'snapshot_timestamp' => now()->subDays(1),
            'status' => 'completed',
        ]);

        $cycleLine = CycleCountLine::create([
            'cycle_count_doc_id' => $cycleDoc->id,
            'item_id' => $this->item->id,
            'storage_location_id' => $this->location->id,
            'book_quantity_snapshot' => 1000,
            'counted_quantity_blind' => 960, // 40 units missing = 4% shrinkage
            'variance_quantity' => 40,
            'variance_value' => 120.00,
            'recount_required' => false,
            'status' => 'verified',
        ]);

        // 4. Generate Review
        $response = $this->actingAs($this->evaluator)->post(route('reviews.store'), [
            'title' => 'Comprehensive Q3 Process Review',
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
            'qualitative_context' => 'Routine operations with periodic batch delivery.',
            'executive_summary' => 'Tested end-to-end evaluation.',
        ]);

        $response->assertSessionHasNoErrors();
        $review = KpiProcessReview::latest('id')->first();
        $this->assertNotNull($review);
        $this->assertEquals('draft', $review->status);
        $this->assertEquals($this->evaluator->id, $review->evaluator_id);

        // Verify Synthesized Records
        $this->assertGreaterThan(0, $review->supplierScorecards()->count());
        $this->assertGreaterThan(0, $review->procurementSavingsLogs()->count());
        $this->assertGreaterThan(0, $review->inventoryShrinkageReports()->count());
        $this->assertGreaterThan(0, $review->processRecommendations()->count());

        // Verify COA Rule 4 Escalation was flagged for 4% shrinkage
        $shrinkageReport = $review->inventoryShrinkageReports()->first();
        $this->assertTrue($shrinkageReport->requires_admin_escalation);
        $this->assertEquals(4.00, (float) $shrinkageReport->shrinkage_rate_pct);

        // Verify DPRI price savings (3.50 ceiling - 3.00 actual = 0.50 savings/unit * 200 = ₱100 savings)
        $savingsLog = $review->procurementSavingsLogs()->first();
        $this->assertEquals(100.00, (float) $savingsLog->variance_amount);
        $this->assertFalse($savingsLog->is_above_ceiling);

        // 5. Submit for Approval
        $submitRes = $this->actingAs($this->evaluator)->post(route('reviews.submit', $review));
        $submitRes->assertRedirect(route('reviews.show', $review));
        $review->refresh();
        $this->assertEquals('submitted', $review->status);

        // 6. Maker-Checker Segregation of Duties:
        // A) Evaluator (InventoryManager) lacks ApproveProcessReview permission -> 403 Forbidden
        $selfApproveRes = $this->actingAs($this->evaluator)->post(route('reviews.approve', $review));
        $selfApproveRes->assertForbidden();
        $review->refresh();
        $this->assertEquals('submitted', $review->status);

        // B) If an Administrator creates a review, they have the permission but SoD prevents self-approval
        $adminReview = \App\Models\KpiProcessReview::create([
            'review_number' => 'REV-TEST-ADMIN',
            'title' => 'Admin Draft',
            'period_start' => now()->subDays(5),
            'period_end' => now(),
            'evaluator_id' => $this->approver->id,
            'status' => 'submitted',
        ]);
        $adminSelfApprove = $this->actingAs($this->approver)->post(route('reviews.approve', $adminReview));
        $adminSelfApprove->assertSessionHasErrors('approver');
        $this->assertEquals('submitted', $adminReview->fresh()->status);

        // 7. Legitimate Approver (Administrator / BAC) approves the evaluator's review
        $validApproveRes = $this->actingAs($this->approver)->post(route('reviews.approve', $review));
        $validApproveRes->assertSessionHasNoErrors();
        $review->refresh();
        $this->assertEquals('approved', $review->status);
        $this->assertEquals($this->approver->id, $review->approved_by_id);
        $this->assertNotNull($review->approved_at);

        // 8. Implement a recommendation
        $rec = $review->processRecommendations()->first();
        $this->assertNotNull($rec);
        $implementRes = $this->actingAs($this->evaluator)->post(route('reviews.recommendations.implement', $rec), [
            'implementation_notes' => 'Corrective plan acknowledged and executed.',
        ]);
        $implementRes->assertRedirect(route('reviews.show', $review));
        $rec->refresh();
        $this->assertEquals('implemented', $rec->status);
        $this->assertEquals($this->evaluator->id, $rec->implemented_by_id);
    }

    public function test_dpri_catalogue_registration(): void
    {
        $response = $this->actingAs($this->evaluator)->post(route('reviews.dpri.store'), [
            'pndf_code' => 'PNDF-TEST-DRUG',
            'drug_name' => 'Testing Antibiotic',
            'dosage_form_strength' => '250mg vial',
            'unit_of_measure' => 'vial',
            'ceiling_price' => 75.50,
            'edition_year' => 2026,
            'notes' => 'Test regulatory reference price',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dpri_reference_prices', [
            'pndf_code' => 'PNDF-TEST-DRUG',
            'drug_name' => 'Testing Antibiotic',
            'ceiling_price' => 75.5000,
        ]);
    }

    public function test_reviews_screens_render_successfully(): void
    {
        // 1. Create a review to view
        $review = KpiProcessReview::create([
            'review_number' => 'REV-VIEW-TEST',
            'title' => 'Test View Review',
            'period_start' => now()->subDays(30),
            'period_end' => now(),
            'evaluator_id' => $this->evaluator->id,
            'status' => 'draft',
            'metrics_summary' => [
                'total_spend' => 50000,
                'net_savings_amount' => 5000,
                'aggregate_savings_pct' => 10,
                'avg_supplier_score' => 88.5,
                'overall_stock_accuracy' => 99.2,
                'critical_bottleneck' => [
                    'name' => 'PR Creation to Approval',
                    'mean_tat_days' => 4.2,
                    'sla_target_days' => 3.0,
                    'variance_sigma' => 1.5,
                ],
                'bottleneck_stages' => [
                    'stage_1' => [
                        'name' => 'PR Creation to Approval',
                        'sample_count' => 10,
                        'mean_tat_days' => 4.2,
                        'min_tat_days' => 1.0,
                        'max_tat_days' => 7.0,
                        'variance_sigma' => 1.5,
                        'sla_target_days' => 3.0,
                        'sla_breach_rate' => 25.0,
                        'status' => 'elevated',
                    ],
                ],
            ],
        ]);

        // Index page
        $this->actingAs($this->evaluator)->get(route('reviews.index'))->assertOk();

        // Create page
        $this->actingAs($this->evaluator)->get(route('reviews.create'))->assertOk();

        // Show page
        $this->actingAs($this->evaluator)->get(route('reviews.show', $review))->assertOk();

        // DPRI Index page
        $this->actingAs($this->evaluator)->get(route('reviews.dpri'))->assertOk();
    }
}
