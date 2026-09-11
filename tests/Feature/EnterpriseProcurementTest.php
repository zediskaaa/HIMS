<?php

namespace Tests\Feature;

use App\Enums\ApprovalChainType;
use App\Enums\ApprovalStepStatus;
use App\Enums\AuditAction;
use App\Enums\ProcurementMethod;
use App\Enums\PurchaseOrderStatus;
use App\Enums\QuoteStatus;
use App\Enums\RequisitionStatus;
use App\Enums\RfqBiddingType;
use App\Enums\RfqStatus;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierStatus;
use App\Models\ApprovalChain;
use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use App\Models\InventoryItem;
use App\Models\ProcurementCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\QuoteLineItem;
use App\Models\RfqLineItem;
use App\Models\SourcingEvaluation;
use App\Models\SourcingRfq;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use App\Services\Procurement\ApprovalRoutingEngine;
use App\Services\Procurement\EvaluationEngine;
use App\Services\Procurement\POConversionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnterpriseProcurementTest extends TestCase
{
    use RefreshDatabase;

    private function createManager(): User
    {
        return User::factory()->inventoryManager()->create();
    }

    private function createAdmin(): User
    {
        return User::factory()->administrator()->create();
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->superAdministrator()->create();
    }

    private function createEligibleSupplier(string $name = 'Accredited Med Corp'): Supplier
    {
        return Supplier::create([
            'name' => $name,
            'business_structure' => 'corporation',
            'address' => '77 Medical Drive, Manila',
            'email' => strtolower(str_replace(' ', '', $name)) . '@example.com',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Approved,
        ]);
    }

    private function createIneligibleSupplier(string $name = 'Unaccredited Vendor'): Supplier
    {
        return Supplier::create([
            'name' => $name,
            'business_structure' => 'sole_proprietorship',
            'address' => '10 Side Alley, Manila',
            'email' => 'unaccredited@example.com',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Draft,
        ]);
    }

    private function createCostCenterWithBudget(float $allocated = 500000.00, int $year = 2026): array
    {
        $manager = $this->createManager();
        $costCenter = CostCenter::create([
            'code' => 'CC-PHARM-' . Str::upper(Str::random(4)),
            'name' => 'Department of Pharmacy',
            'department' => 'Pharmacy',
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $budget = CostCenterBudget::create([
            'cost_center_id' => $costCenter->id,
            'fiscal_year' => $year,
            'allocated_budget' => $allocated,
            'soft_encumbered' => 0.00,
            'hard_encumbered' => 0.00,
            'spent_amount' => 0.00,
            'currency' => 'PHP',
        ]);

        return [$costCenter, $budget, $manager];
    }

    private function createItem(string $name = 'Surgical Scalpel #10', string $sku = 'MED-SCALP-01'): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name,
            'sku' => $sku,
            'unit' => 'box',
            'unit_cost' => 150.00,
            'quantity_on_hand' => 50,
            'reorder_point' => 10,
            'status' => 'active',
        ]);
    }

    private function createStorageLocation(): StorageLocation
    {
        return StorageLocation::create([
            'name' => 'Main Central Pharmacy Warehouse',
            'code' => 'LOC-MAIN-' . Str::upper(Str::random(4)),
            'type' => 'warehouse',
            'is_active' => true,
        ]);
    }

    // =========================================================================
    // 1. Requisition & Budget Encumbrance Tests
    // =========================================================================

    public function test_multiline_purchase_request_validates_budget_and_encumbers_soft_commitment(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(100000.00);
        $item1 = $this->createItem('Sterile Syringes 10ml', 'SYR-010');
        $item2 = $this->createItem('IV Infusion Sets', 'IV-SET-01');

        Sanctum::actingAs($manager, ['*']);

        $payload = [
            'cost_center_id' => $costCenter->id,
            'title' => 'Monthly Pharmacy Consumables Replenishment',
            'description' => 'Replenishment for inpatient emergency ward surgical consumption',
            'lines' => [
                [
                    'item_id' => $item1->id,
                    'quantity' => 200,
                    'uom' => 'box',
                    'estimated_unit_price' => 100.00, // 20,000 PHP
                ],
                [
                    'item_id' => $item2->id,
                    'quantity' => 100,
                    'uom' => 'pack',
                    'estimated_unit_price' => 150.00, // 15,000 PHP
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/procurement/requisitions', $payload);
        $response->assertStatus(201);
        $response->assertJsonPath('data.total_estimated_amount', '35000.00');

        // Verify database persistence
        $pr = PurchaseRequest::where('cost_center_id', $costCenter->id)->firstOrFail();
        $this->assertSame(RequisitionStatus::PendingApproval, $pr->status);
        $this->assertCount(2, $pr->lines);
        $this->assertEquals(35000.00, (float) $pr->total_estimated_amount);

        // Verify synchronous budget encumbrance
        $budget->refresh();
        $this->assertEquals(35000.00, (float) $budget->soft_encumbered);
        $this->assertEquals(65000.00, $budget->availableBudget());

        // Verify dual audit logging
        $this->assertDatabaseHas('procurement_audit_logs', [
            'entity_name' => 'PurchaseRequest',
            'entity_id' => $pr->id,
            'action_type' => 'created_purchase_request',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CreatedPurchaseRequest->value,
            'target_id' => (string) $pr->id,
        ]);
    }

    public function test_purchase_request_rejects_when_requested_amount_exceeds_budget(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(50000.00);
        $item = $this->createItem('Diagnostic Ultrasound Transducer Probe', 'US-PROBE-01');

        Sanctum::actingAs($manager, ['*']);

        $payload = [
            'cost_center_id' => $costCenter->id,
            'title' => 'High-end Ultrasound Probe Acquisition',
            'description' => 'Exceeding budget test',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'uom' => 'unit',
                    'estimated_unit_price' => 75000.00, // 75,000 exceeds 50,000 budget!
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/procurement/requisitions', $payload);
        $response->assertStatus(422);
        $response->assertJsonFragment(['status' => 'error']);

        // Assert no PR created and budget untouched
        $this->assertDatabaseMissing('purchase_requests', ['title' => 'High-end Ultrasound Probe Acquisition']);
        $budget->refresh();
        $this->assertEquals(0.00, (float) $budget->soft_encumbered);
        $this->assertEquals(50000.00, $budget->availableBudget());
    }

    // =========================================================================
    // 2. Sourcing RFQ, Supplier Eligibility & Sealed Bidding Tests
    // =========================================================================

    public function test_rfq_packaging_enforces_supplier_accreditation_eligibility(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(200000.00);
        $item = $this->createItem('Orthopedic Implants Titanium', 'ORTHO-IMP-01');

        $pr = PurchaseRequest::create([
            'pr_number' => 'PR-2026-00010',
            'requester_id' => $manager->id,
            'cost_center_id' => $costCenter->id,
            'title' => 'Orthopedic Titanium Plates',
            'description' => 'Trauma surgical implants',
            'status' => RequisitionStatus::Approved,
            'total_estimated_amount' => 80000.00,
        ]);

        $prLine = PurchaseRequestLine::create([
            'purchase_request_id' => $pr->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'item_description' => 'Titanium Plate 3.5mm',
            'quantity' => 20,
            'uom' => 'piece',
            'estimated_unit_price' => 4000.00,
            'estimated_total_price' => 80000.00,
        ]);

        $eligibleSupplier = $this->createEligibleSupplier('Accredited Orthopedics Inc');
        $ineligibleSupplier = $this->createIneligibleSupplier('Unvetted Surgical Co');

        Sanctum::actingAs($manager, ['*']);

        // Attempt to invite an ineligible supplier must be rejected
        $payloadWithIneligible = [
            'purchase_request_id' => $pr->id,
            'title' => 'RFQ for Titanium Plates',
            'bidding_type' => 'sealed',
            'submission_deadline' => now()->addDays(7)->toDateTimeString(),
            'invited_supplier_ids' => [$ineligibleSupplier->id],
            'lines' => [
                ['item_id' => $item->id, 'target_quantity' => 20, 'uom' => 'piece'],
            ],
        ];

        $failResponse = $this->postJson('/api/v1/procurement/rfqs', $payloadWithIneligible);
        $failResponse->assertStatus(422);
        $failResponse->assertJsonFragment(['message' => 'One or more invited suppliers are not accredited or have active compliance blocks.']);

        // Inviting accredited supplier succeeds
        $payloadWithEligible = [
            'purchase_request_id' => $pr->id,
            'title' => 'RFQ for Titanium Plates',
            'bidding_type' => 'sealed',
            'submission_deadline' => now()->addDays(7)->toDateTimeString(),
            'invited_supplier_ids' => [$eligibleSupplier->id],
            'lines' => [
                ['item_id' => $item->id, 'target_quantity' => 20, 'uom' => 'piece'],
            ],
        ];

        $successResponse = $this->postJson('/api/v1/procurement/rfqs', $payloadWithEligible);
        $successResponse->assertStatus(201);

        $rfq = SourcingRfq::where('purchase_request_id', $pr->id)->firstOrFail();
        $this->assertSame(RfqStatus::Published, $rfq->status);
        $this->assertSame(RfqBiddingType::Sealed, $rfq->bidding_type);
        $this->assertDatabaseHas('rfq_supplier_invitations', [
            'sourcing_rfq_id' => $rfq->id,
            'supplier_id' => $eligibleSupplier->id,
        ]);
    }

    public function test_bidding_deadline_enforcement_rejects_late_quotes(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(100000.00);
        $item = $this->createItem();
        $supplier = $this->createEligibleSupplier();

        $rfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-2026-EXP01',
            'title' => 'Expired RFQ Test',
            'bidding_type' => RfqBiddingType::Sealed,
            'status' => RfqStatus::Published,
            'submission_deadline' => now()->subHours(2), // Deadline passed 2 hours ago!
            'published_at' => now()->subDays(5),
            'created_by_user_id' => $manager->id,
        ]);

        $rfqLine = RfqLineItem::create([
            'sourcing_rfq_id' => $rfq->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'item_description' => 'Test item',
            'target_quantity' => 10,
            'uom' => 'box',
        ]);

        Sanctum::actingAs($manager, ['*']);

        $quotePayload = [
            'supplier_id' => $supplier->id,
            'lines' => [
                [
                    'rfq_line_item_id' => $rfqLine->id,
                    'offered_unit_price' => 140.00,
                    'offered_quantity' => 10,
                    'lead_time_days' => 5,
                ],
            ],
        ];

        $response = $this->postJson("/api/v1/procurement/rfqs/{$rfq->id}/quotes", $quotePayload);
        $response->assertStatus(422);
        $response->assertJsonFragment(['status' => 'error']);
    }

    public function test_sealed_quotes_remain_masked_until_unsealed(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(100000.00);
        $item = $this->createItem();
        $supplier = $this->createEligibleSupplier();

        $rfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-2026-SEAL01',
            'title' => 'Sealed Bidding Masking Test',
            'bidding_type' => RfqBiddingType::Sealed,
            'status' => RfqStatus::Published,
            'submission_deadline' => now()->addDays(3), // Deadline is in 3 days
            'published_at' => now()->subDay(),
            'created_by_user_id' => $manager->id,
        ]);

        $rfqLine = RfqLineItem::create([
            'sourcing_rfq_id' => $rfq->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'item_description' => 'Test item',
            'target_quantity' => 50,
            'uom' => 'box',
        ]);

        Sanctum::actingAs($manager, ['*']);

        $quotePayload = [
            'supplier_id' => $supplier->id,
            'lines' => [
                [
                    'rfq_line_item_id' => $rfqLine->id,
                    'offered_unit_price' => 125.00,
                    'offered_quantity' => 50,
                    'lead_time_days' => 4,
                ],
            ],
        ];

        $response = $this->postJson("/api/v1/procurement/rfqs/{$rfq->id}/quotes", $quotePayload);
        $response->assertStatus(201);
        $response->assertJsonPath('data.is_sealed', true);

        $quote = SupplierQuote::where('sourcing_rfq_id', $rfq->id)->firstOrFail();
        $this->assertTrue((bool) $quote->is_sealed);
        $this->assertNull($quote->unsealed_at);

        // Attempting to evaluate RFQ before deadline must throw DomainException
        $evaluationEngine = app(EvaluationEngine::class);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Sealed Bid Protocol Violation');
        $evaluationEngine->evaluateRfq($rfq, $manager);
    }

    // =========================================================================
    // 3. Evaluation Engine Mathematical Scoring & Landed Cost Tests
    // =========================================================================

    public function test_evaluation_engine_computes_landed_cost_tco_and_multi_attribute_scores(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(200000.00);
        $item = $this->createItem('Defibrillator Pads Adult', 'DEFIB-PAD-01');

        $supplierA = $this->createEligibleSupplier('Biomed Solutions Inc');
        $supplierB = $this->createEligibleSupplier('Pacific Med Equip Corp');

        $rfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-2026-EVAL01',
            'title' => 'Defibrillator Pads Evaluation',
            'bidding_type' => RfqBiddingType::Sealed,
            'status' => RfqStatus::Published,
            'submission_deadline' => now()->subMinutes(5), // Deadline just closed
            'published_at' => now()->subDays(3),
            'created_by_user_id' => $manager->id,
            'weight_price' => 0.50,
            'weight_technical' => 0.20,
            'weight_quality' => 0.20,
            'weight_lead_time' => 0.10,
        ]);

        $rfqLine = RfqLineItem::create([
            'sourcing_rfq_id' => $rfq->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'item_description' => 'Defibrillator Pads Adult box of 10',
            'target_quantity' => 100,
            'uom' => 'box',
        ]);

        // Quote A: Price 100 PHP, 100 qty = 10,000, 500 discount (net 9,500), Freight 500, Tariffs 100 = 10,100 Landed Cost
        $quoteA = SupplierQuote::create([
            'sourcing_rfq_id' => $rfq->id,
            'supplier_id' => $supplierA->id,
            'status' => QuoteStatus::Submitted->value,
            'currency' => 'PHP',
            'exchange_rate' => 1.0,
            'total_bid_amount' => 9500.00,
            'is_sealed' => true,
        ]);

        QuoteLineItem::create([
            'supplier_quote_id' => $quoteA->id,
            'rfq_line_item_id' => $rfqLine->id,
            'offered_unit_price' => 100.00,
            'offered_quantity' => 100,
            'discount_amount' => 500.00,
            'shipping_cost' => 500.00,
            'tariffs_cost' => 100.00,
            'handling_cost' => 0.00,
            'lead_time_days' => 5,
            'technical_score' => 90.0,
            'technical_compliance' => true,
        ]);

        // Quote B: Price 120 PHP, 100 qty = 12,000, 0 discount, Freight 200, Tariffs 50 = 12,250 Landed Cost
        $quoteB = SupplierQuote::create([
            'sourcing_rfq_id' => $rfq->id,
            'supplier_id' => $supplierB->id,
            'status' => QuoteStatus::Submitted->value,
            'currency' => 'PHP',
            'exchange_rate' => 1.0,
            'total_bid_amount' => 12000.00,
            'is_sealed' => true,
        ]);

        QuoteLineItem::create([
            'supplier_quote_id' => $quoteB->id,
            'rfq_line_item_id' => $rfqLine->id,
            'offered_unit_price' => 120.00,
            'offered_quantity' => 100,
            'discount_amount' => 0.00,
            'shipping_cost' => 200.00,
            'tariffs_cost' => 50.00,
            'handling_cost' => 0.00,
            'lead_time_days' => 2,
            'technical_score' => 95.0,
            'technical_compliance' => true,
        ]);

        $evaluationEngine = app(EvaluationEngine::class);
        $evaluations = $evaluationEngine->evaluateRfq($rfq, $manager);

        $this->assertCount(2, $evaluations);

        // Assert bids were unsealed
        $quoteA->refresh();
        $this->assertFalse((bool) $quoteA->is_sealed);
        $this->assertNotNull($quoteA->unsealed_at);

        // Verify Landed Cost Calculation:
        // Quote A: 100 * 100 + 500 + 100 - 500 = 10,100 PHP
        $evalA = $evaluations->firstWhere('supplier_quote_id', $quoteA->id);
        $this->assertNotNull($evalA);
        $this->assertEquals(10100.00, (float) $evalA->normalized_landed_cost);

        // Quote B: 120 * 100 + 200 + 50 = 12,250 PHP
        $evalB = $evaluations->firstWhere('supplier_quote_id', $quoteB->id);
        $this->assertNotNull($evalB);
        $this->assertEquals(12250.00, (float) $evalB->normalized_landed_cost);

        // Commercial price score: Quote A is cheaper, so Price Score = 100
        $this->assertEquals(100.00, (float) $evalA->commercial_score);
        // Quote B price score: (10100 / 12250) * 100 = 82.45
        $this->assertEquals(82.45, (float) $evalB->commercial_score);

        // Winning supplier has highest composite score
        $winningEval = $evaluations->first();
        $this->assertSame($quoteA->id, $winningEval->supplier_quote_id);
    }

    // =========================================================================
    // 4. Approval Routing Engine (DOA & Segregation of Duties) Tests
    // =========================================================================

    public function test_approval_routing_engine_enforces_doa_tiers_and_segregation_of_duties(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(2000000.00);

        // Create high-value PR requiring Level 4 DOA approval (> 1,000,000 PHP)
        $pr = PurchaseRequest::create([
            'pr_number' => 'PR-2026-DOA01',
            'requester_id' => $manager->id,
            'cost_center_id' => $costCenter->id,
            'title' => 'MRI Liquid Helium Refill & Superconducting Magnet Service',
            'description' => 'Mandatory annual cryogenic maintenance',
            'status' => RequisitionStatus::PendingApproval,
            'total_estimated_amount' => 1250000.00, // Level 4 tier!
        ]);

        $routingEngine = app(ApprovalRoutingEngine::class);
        $chain = $routingEngine->routePurchaseRequest($pr);

        $this->assertInstanceOf(ApprovalChain::class, $chain);
        $this->assertSame(ApprovalChainType::PurchaseRequest, $chain->chain_type);
        $this->assertCount(4, $chain->steps); // 4 tiers generated!

        // Test Segregation of Duties (SOX 404 / internal control):
        // Requester cannot approve their own PR step!
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Segregation of Duties Violation');
        $routingEngine->approveStep($chain, $manager, 'Self-approval attempt');
    }

    public function test_approval_chain_stamps_digital_signature_and_advances_tiers(): void
    {
        [$costCenter, $budget, $requester] = $this->createCostCenterWithBudget(100000.00);
        $manager = $this->createManager();

        $pr = PurchaseRequest::create([
            'pr_number' => 'PR-2026-DOA02',
            'requester_id' => $requester->id,
            'cost_center_id' => $costCenter->id,
            'title' => 'Surgical Sutures Bulk Purchase',
            'description' => 'Operating theatre emergency stock',
            'status' => RequisitionStatus::PendingApproval,
            'total_estimated_amount' => 45000.00, // Level 1 tier (<= 50k PHP)
        ]);

        $routingEngine = app(ApprovalRoutingEngine::class);
        $chain = $routingEngine->routePurchaseRequest($pr);

        $this->assertCount(1, $chain->steps);
        $firstStep = $chain->steps()->where('step_number', 1)->firstOrFail();
        $this->assertSame(ApprovalStepStatus::Pending, $firstStep->status);

        // Legitimate authorized manager signs off
        $approvedStep = $routingEngine->approveStep($chain, $manager, 'Approved under delegated department budget authority.');

        $this->assertSame(ApprovalStepStatus::Approved, $approvedStep->status);
        $this->assertSame($manager->id, $approvedStep->approver_user_id);
        $this->assertNotNull($approvedStep->decided_at);
        $this->assertNotEmpty($approvedStep->digital_signature_token);
        $this->assertSame(64, strlen($approvedStep->digital_signature_token)); // SHA-256 hash length

        $pr->refresh();
        $this->assertSame(RequisitionStatus::Approved, $pr->status);
    }

    // =========================================================================
    // 5. PO Conversion, cXML Generation & PO Revision Amendment Tests
    // =========================================================================

    public function test_po_conversion_transitions_soft_to_hard_commitment_and_generates_cxml(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(300000.00);
        $item = $this->createItem('Surgical Gloves Powder-Free', 'GLV-PF-01');
        $supplier = $this->createEligibleSupplier('Sterling Medical Supplies');

        // Setup soft commitment on budget
        $budget->update(['soft_encumbered' => 50000.00]);

        $pr = PurchaseRequest::create([
            'pr_number' => 'PR-2026-PO01',
            'requester_id' => $manager->id,
            'cost_center_id' => $costCenter->id,
            'title' => 'Surgical Gloves Procurement',
            'description' => 'Ward replenishments',
            'status' => RequisitionStatus::Approved,
            'total_estimated_amount' => 50000.00,
        ]);

        $rfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-2026-PO01',
            'purchase_request_id' => $pr->id,
            'title' => 'RFQ Surgical Gloves',
            'bidding_type' => RfqBiddingType::Open,
            'status' => RfqStatus::Awarded,
            'submission_deadline' => now()->subDay(),
            'published_at' => now()->subDays(5),
            'created_by_user_id' => $manager->id,
        ]);

        $rfqLine = RfqLineItem::create([
            'sourcing_rfq_id' => $rfq->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'item_description' => 'Surgical Gloves Powder-Free box of 100',
            'target_quantity' => 500,
            'uom' => 'box',
        ]);

        $quote = SupplierQuote::create([
            'sourcing_rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'status' => QuoteStatus::Accepted->value,
            'currency' => 'PHP',
            'exchange_rate' => 1.0,
            'total_bid_amount' => 47500.00,
            'is_sealed' => false,
        ]);

        QuoteLineItem::create([
            'supplier_quote_id' => $quote->id,
            'rfq_line_item_id' => $rfqLine->id,
            'offered_unit_price' => 95.00,
            'offered_quantity' => 500,
            'lead_time_days' => 3,
            'landed_cost' => 47500.00,
            'technical_compliance' => true,
        ]);

        $poService = app(POConversionService::class);
        $po = $poService->convertAwardToPO($rfq, $quote, $manager);

        $this->assertInstanceOf(PurchaseOrder::class, $po);
        $this->assertSame($supplier->id, $po->supplier_id);
        $this->assertSame($pr->id, $po->purchase_request_id);
        $this->assertSame($rfq->id, $po->sourcing_rfq_id);
        $this->assertSame($costCenter->id, $po->cost_center_id);
        $this->assertEquals(47500.00, (float) $po->total_amount);
        $this->assertEquals(47500.00, (float) $po->total_encumbered_amount);

        // Verify multi-line items attached
        $this->assertCount(1, $po->lines);
        $poLine = $po->lines->first();
        $this->assertEquals(500, $poLine->ordered_quantity);
        $this->assertEquals(95.00, (float) $poLine->unit_price);

        // Verify valid cXML payload generation
        $this->assertNotNull($po->cxml_payload);
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $po->cxml_payload);
        $this->assertStringContainsString('<OrderRequest>', $po->cxml_payload);
        $this->assertStringContainsString("<OrderRequestHeader orderID=\"{$po->po_number}\"", $po->cxml_payload);
        $this->assertStringContainsString('<ItemDetail>', $po->cxml_payload);

        // Verify budget encumbrance transition from soft to hard encumbrance
        $budget->refresh();
        $this->assertEquals(47500.00, (float) $budget->hard_encumbered);
    }

    public function test_po_amendment_with_variance_exceeding_5_percent_triggers_reapproval(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(300000.00);
        $admin = $this->createAdmin();
        $item = $this->createItem();
        $supplier = $this->createEligibleSupplier();

        $po = PurchaseOrder::create([
            'po_number' => 'PO-2026-REV01',
            'cost_center_id' => $costCenter->id,
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 1000,
            'unit_cost' => 100.00,
            'total_amount' => 100000.00,
            'total_encumbered_amount' => 100000.00,
            'currency' => 'PHP',
            'exchange_rate' => 1.0,
            'status' => PurchaseOrderStatus::Dispatched->value,
            'version' => 'PO-REV1',
            'revision_number' => 1,
        ]);

        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'ordered_quantity' => 1000,
            'unit_price' => 100.00,
            'line_total' => 100000.00,
            'status' => 'pending',
        ]);

        $poService = app(POConversionService::class);

        // Amend PO: Quantity increased from 1,000 to 1,100 (+10% variance, > 5%)
        $newLines = [
            [
                'id' => $poLine->id,
                'item_id' => $item->id,
                'ordered_quantity' => 1100,
                'unit_price' => 100.00,
                'line_total' => 110000.00,
            ],
        ];

        $revision = $poService->submitPoRevision($po, $newLines, 'Surge in hospital admissions requiring additional stock', $admin);

        $this->assertSame(2, $revision->revision_number);
        $this->assertEquals(10.00, (float) $revision->variance_percentage);
        $this->assertTrue((bool) $revision->requires_doa_reapproval);

        // Status must revert to PendingApproval because variance > 5%
        $po->refresh();
        $this->assertSame(PurchaseOrderStatus::PendingApproval->value, $po->status);
    }

    // =========================================================================
    // 6. Inventory Automation Service Receiving & Stock Ledger Integration
    // =========================================================================

    public function test_multi_line_po_receiving_integrates_with_inventory_automation_service(): void
    {
        $manager = $this->createManager();
        $location = $this->createStorageLocation();
        $supplier = $this->createEligibleSupplier();

        $itemA = $this->createItem('Item Alpha', 'SKU-ALPHA-01');
        $itemB = $this->createItem('Item Beta', 'SKU-BETA-01');

        $initialStockA = $itemA->quantity_on_hand;
        $initialStockB = $itemB->quantity_on_hand;

        $po = PurchaseOrder::create([
            'po_number' => 'PO-2026-REC01',
            'supplier_id' => $supplier->id,
            'item_id' => $itemA->id,
            'quantity' => 150,
            'unit_cost' => 150.00,
            'total_amount' => 25000.00,
            'currency' => 'PHP',
            'exchange_rate' => 1.0,
            'status' => PurchaseOrderStatus::Dispatched->value,
            'version' => 'PO-REV1',
            'revision_number' => 1,
        ]);

        $lineA = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'item_id' => $itemA->id,
            'ordered_quantity' => 50,
            'received_quantity' => 0,
            'unit_price' => 200.00,
            'line_total' => 10000.00,
            'status' => 'pending',
        ]);

        $lineB = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'line_number' => 2,
            'item_id' => $itemB->id,
            'ordered_quantity' => 100,
            'received_quantity' => 0,
            'unit_price' => 150.00,
            'line_total' => 15000.00,
            'status' => 'pending',
        ]);

        // Receive delivery
        $this->actingAs($manager)->post("/inventory/purchases/{$po->id}/receive", [
            'storage_location_id' => $location->id,
            'notes' => 'Shipment received at main warehouse dock',
        ])->assertRedirect('/inventory/purchases');

        $lineA->refresh();
        $lineB->refresh();
        $po->refresh();

        $this->assertEquals(50, $lineA->received_quantity);
        $this->assertSame('received', $lineA->line_status);
        $this->assertEquals(100, $lineB->received_quantity);
        $this->assertSame('received', $lineB->line_status);
        $this->assertSame('received', $po->status);
        $this->assertNotNull($po->received_at);

        // Verify stock ledgers updated via InventoryAutomationService
        $this->assertSame(50, (int) $itemA->fresh()->quantity_on_hand);
        $this->assertSame(100, (int) $itemB->fresh()->quantity_on_hand);

        $this->assertDatabaseHas('item_stock_levels', [
            'item_id' => $itemA->id,
            'storage_location_id' => $location->id,
            'quantity' => 50,
        ]);
        $this->assertDatabaseHas('item_stock_levels', [
            'item_id' => $itemB->id,
            'storage_location_id' => $location->id,
            'quantity' => 100,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $itemA->id,
            'movement_type' => 'stock_in',
            'quantity' => 50,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $itemB->id,
            'movement_type' => 'stock_in',
            'quantity' => 100,
        ]);
    }

    // =========================================================================
    // 7. API Idempotency Middleware Tests
    // =========================================================================

    public function test_api_idempotency_middleware_prevents_duplicate_financial_transactions(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(100000.00);
        $item = $this->createItem();

        Sanctum::actingAs($manager, ['*']);

        $idempotencyKey = 'IDEMP-' . Str::uuid()->toString();

        $payload = [
            'cost_center_id' => $costCenter->id,
            'title' => 'Idempotent Requisition Test',
            'description' => 'Ensuring financial integrity against replay attacks',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 50,
                    'uom' => 'box',
                    'estimated_unit_price' => 100.00, // 5,000 PHP
                ],
            ],
        ];

        // First Request
        $response1 = $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/v1/procurement/requisitions', $payload);
        $response1->assertStatus(201);
        $prId1 = $response1->json('data.id');

        $budget->refresh();
        $this->assertEquals(5000.00, (float) $budget->soft_encumbered);

        // Immediate Replay of Same Request with Identical Idempotency-Key
        $response2 = $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/v1/procurement/requisitions', $payload);
        $response2->assertStatus(201);
        $prId2 = $response2->json('data.id');

        // Assert response is identical cached response
        $this->assertSame($prId1, $prId2);

        // Crucial Check: Ensure budget was NOT double-encumbered!
        $budget->refresh();
        $this->assertEquals(5000.00, (float) $budget->soft_encumbered);

        // Crucial Check: Ensure only 1 PR was created in database!
        $this->assertSame(1, PurchaseRequest::where('title', 'Idempotent Requisition Test')->count());
    }

    public function test_web_ui_requisition_and_purchase_order_intake_forms_are_fully_functional_with_dropdowns_and_numeric_validation(): void
    {
        $manager = $this->createManager();
        $admin = $this->createAdmin();

        $category = ProcurementCategory::create([
            'code' => 'TEST-CAT',
            'name' => 'Testing Consumables',
            'description' => 'Category for UI verification tests',
            'is_active' => true,
        ]);

        $costCenter = CostCenter::create([
            'code' => 'CC-TEST',
            'name' => 'Clinical Diagnostics Test Unit',
            'department' => 'Pathology',
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $budget = CostCenterBudget::create([
            'cost_center_id' => $costCenter->id,
            'fiscal_year' => 2026,
            'allocated_budget' => 500000.00,
            'soft_encumbered' => 0.00,
            'hard_encumbered' => 0.00,
            'spent_amount' => 0.00,
            'currency' => 'PHP',
        ]);

        $supplier = $this->createEligibleSupplier('Accredited Diagnostics Lab Corp');

        $item = $this->createItem('Rapid Diagnostic Antigen Cartridge', 'MED-ANT-001');

        // 1. Verify UI Render of Form Elements, Dropdowns, Numeric Constraints & Autogenerated Previews
        $response = $this->actingAs($manager)->get('/inventory/purchases');
        $response->assertStatus(200);

        // Verify Autogenerated previews
        $response->assertSee('PR-' . now()->format('Ymd') . '-AUTO');
        $response->assertSee('PO-' . now()->format('Ymd') . '-AUTO');

        // Verify Dropdown elements
        $response->assertSee('name="procurement_category_id"', false);
        $response->assertSee('name="procurement_method"', false);
        $response->assertSee('name="cost_center_id"', false);
        $response->assertSee('name="payment_terms"', false);
        $response->assertSee('name="incoterms"', false);

        // Verify Strict Numeric Input Constraints
        $response->assertSee('inputmode="numeric"', false);
        $response->assertSee('inputmode="decimal"', false);

        // 2. Submit Functional Purchase Request via Web Route
        $prPayload = [
            'title' => 'Web Requisition Intake Test',
            'cost_center_id' => $costCenter->id,
            'procurement_category_id' => $category->id,
            'procurement_method' => 'Request for Quotation',
            'priority' => 'high',
            'item_id' => $item->id,
            'quantity' => 100,
            'estimated_unit_price' => 150.00,
            'need_by_date' => now()->addDays(10)->toDateString(),
            'description' => 'Clinical laboratory emergency cartridge replenishment',
        ];

        $prPostResponse = $this->actingAs($manager)
            ->post('/inventory/purchases/enterprise-requests', $prPayload);

        $prPostResponse->assertRedirect('/inventory/purchases');
        $prPostResponse->assertSessionHas('success');

        $createdPr = PurchaseRequest::where('title', 'Web Requisition Intake Test')->first();
        $this->assertNotNull($createdPr);
        $this->assertEquals($category->id, $createdPr->procurement_category_id);
        $this->assertEquals('Request for Quotation', $createdPr->procurement_method);
        $this->assertEquals(15000.00, (float) $createdPr->total_estimated_amount);
        $this->assertEquals(RequisitionStatus::PendingApproval, $createdPr->status);

        // Verify Soft Encumbrance was committed on budget
        $budget->refresh();
        $this->assertEquals(15000.00, (float) $budget->soft_encumbered);

        // 3. Submit Functional Direct Purchase Order via Web Route
        $poPayload = [
            'supplier_id' => $supplier->id,
            'cost_center_id' => $costCenter->id,
            'item_id' => $item->id,
            'quantity' => 50,
            'unit_cost' => 140.00,
            'payment_terms' => 'Net 30',
            'incoterms' => 'DDP',
            'status' => 'dispatched',
            'notes' => 'Deliver direct to Dock 2 under purchase contract',
        ];

        $poPostResponse = $this->actingAs($manager)
            ->post('/inventory/purchases/orders', $poPayload);

        $poPostResponse->assertRedirect('/inventory/purchases');
        $poPostResponse->assertSessionHas('success');

        $createdPo = PurchaseOrder::where('notes', 'Deliver direct to Dock 2 under purchase contract')->first();
        $this->assertNotNull($createdPo);
        $this->assertEquals($supplier->id, $createdPo->supplier_id);
        $this->assertEquals($costCenter->id, $createdPo->cost_center_id);
        $this->assertEquals('Net 30', $createdPo->payment_terms);
        $this->assertEquals('DDP', $createdPo->incoterms);
        $this->assertEquals(7000.00, (float) $createdPo->total_amount);
        $this->assertEquals(7000.00, (float) $createdPo->total_encumbered_amount);
        $this->assertNotNull($createdPo->cxml_payload);
        $this->assertStringContainsString('<OrderRequest', $createdPo->cxml_payload);

        // Verify Hard Encumbrance was committed on budget
        $budget->refresh();
        $this->assertEquals(7000.00, (float) $budget->hard_encumbered);
    }

    // =========================================================================
    // 7. RFQ Evaluation Matrix Workflow, Sealed Bid Governance & Timezone Tests
    // =========================================================================

    public function test_sealed_rfq_evaluation_is_rejected_before_deadline_via_web_and_api(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(100000.00);
        $item = $this->createItem('Surgical Titanium Screws', 'SURG-SCR-01');
        $supplierA = $this->createEligibleSupplier('Apex Surgical');
        $supplierB = $this->createEligibleSupplier('Sterling Med');

        // Sealed RFQ with deadline 5 days in the future (similar to demo RFQ)
        $rfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-FUTURE-DEADLINE-01',
            'title' => 'Sealed Sourcing Event for Titanium Screws',
            'procurement_method' => ProcurementMethod::RequestForQuotation,
            'bidding_type' => RfqBiddingType::Sealed,
            'submission_deadline' => now()->addDays(5),
            'status' => RfqStatus::Published,
            'published_at' => now()->subDay(),
            'created_by_user_id' => $manager->id,
            'weight_price' => 0.50,
            'weight_technical' => 0.30,
            'weight_quality' => 0.10,
            'weight_lead_time' => 0.10,
        ]);

        $rfqLine = RfqLineItem::create([
            'sourcing_rfq_id' => $rfq->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'target_quantity' => 100,
            'uom' => 'piece',
            'max_budget_unit_price' => 500.00,
        ]);

        // Submit sealed quote A
        $quoteA = SupplierQuote::create([
            'sourcing_rfq_id' => $rfq->id,
            'supplier_id' => $supplierA->id,
            'quote_number' => 'QTN-TEST-APEX-01',
            'status' => QuoteStatus::Submitted->value,
            'quoted_price' => 45000.00,
            'total_bid_amount' => 45000.00,
            'is_sealed' => true,
        ]);

        $quoteA->lines()->create([
            'rfq_line_item_id' => $rfqLine->id,
            'offered_unit_price' => 450.00,
            'offered_quantity' => 100,
            'lead_time_days' => 5,
            'technical_score' => 95.0,
            'technical_compliance' => true,
        ]);

        // Assert Model helpers reflect pre-deadline state
        $this->assertFalse($rfq->isDeadlineElapsed());
        $this->assertTrue($rfq->isSealed());
        $this->assertFalse($rfq->canBeEvaluated());

        // 1. Attempt evaluation via Web Route -> Must reject and redirect with error
        $webResponse = $this->actingAs($manager)
            ->post("/inventory/purchases/rfqs/{$rfq->id}/evaluate");

        $webResponse->assertRedirect('/inventory/purchases');
        $webResponse->assertSessionHasErrors('evaluate');

        // 2. Attempt direct API request -> Must return 422 JSON error
        $apiResponse = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/v1/procurement/rfqs/{$rfq->id}/evaluate");

        $apiResponse->assertStatus(422);
        $apiResponse->assertJsonPath('status', 'error');
        $this->assertStringContainsString('Sealed Bid Protocol Violation', $apiResponse->json('message'));

        // 3. Verify sealed integrity remained intact
        $rfq->refresh();
        $this->assertNull($rfq->unsealed_at);
        $this->assertNull($rfq->unsealed_by_user_id);
        $this->assertEquals(RfqStatus::Published, $rfq->status);

        $quoteA->refresh();
        $this->assertTrue((bool) $quoteA->is_sealed);
        $this->assertNull($quoteA->unsealed_at);
        $this->assertEquals(0, SourcingEvaluation::where('sourcing_rfq_id', $rfq->id)->count());

        // 4. Verify UI view renders disabled sealed button and lock message
        $uiResponse = $this->actingAs($manager)->get('/inventory/purchases');
        $uiResponse->assertOk();
        $uiResponse->assertSee('Sealed • Bidding Open');
        $uiResponse->assertSee('Unlocks');
    }

    public function test_sealed_rfq_evaluation_succeeds_after_deadline_elapses(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(100000.00);
        $item = $this->createItem('Orthopedic Plate 3.5mm', 'ORTHO-PLT-01');
        $supplierA = $this->createEligibleSupplier('Titanium Implants Corp');
        $supplierB = $this->createEligibleSupplier('Global Ortho Supply');

        // Sealed RFQ with deadline in past (window elapsed)
        $rfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-ELAPSED-DEADLINE-02',
            'title' => 'Sealed Sourcing Event - Window Closed',
            'procurement_method' => ProcurementMethod::RequestForQuotation,
            'bidding_type' => RfqBiddingType::Sealed,
            'submission_deadline' => now()->subMinutes(15),
            'status' => RfqStatus::Published,
            'published_at' => now()->subDays(3),
            'created_by_user_id' => $manager->id,
            'weight_price' => 0.50,
            'weight_technical' => 0.25,
            'weight_quality' => 0.15,
            'weight_lead_time' => 0.10,
        ]);

        $rfqLine = RfqLineItem::create([
            'sourcing_rfq_id' => $rfq->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'target_quantity' => 20,
            'uom' => 'piece',
            'max_budget_unit_price' => 4000.00,
        ]);

        $quoteA = SupplierQuote::create([
            'sourcing_rfq_id' => $rfq->id,
            'supplier_id' => $supplierA->id,
            'quote_number' => 'QTN-TITANIUM-01',
            'status' => QuoteStatus::Submitted->value,
            'quoted_price' => 70000.00,
            'total_bid_amount' => 70000.00,
            'is_sealed' => true,
        ]);

        $quoteA->lines()->create([
            'rfq_line_item_id' => $rfqLine->id,
            'offered_unit_price' => 3500.00,
            'offered_quantity' => 20,
            'lead_time_days' => 6,
            'technical_score' => 95.0,
            'technical_compliance' => true,
        ]);

        $quoteB = SupplierQuote::create([
            'sourcing_rfq_id' => $rfq->id,
            'supplier_id' => $supplierB->id,
            'quote_number' => 'QTN-GLOBAL-01',
            'status' => QuoteStatus::Submitted->value,
            'quoted_price' => 76000.00,
            'total_bid_amount' => 76000.00,
            'is_sealed' => true,
        ]);

        $quoteB->lines()->create([
            'rfq_line_item_id' => $rfqLine->id,
            'offered_unit_price' => 3800.00,
            'offered_quantity' => 20,
            'lead_time_days' => 4,
            'technical_score' => 90.0,
            'technical_compliance' => true,
        ]);

        // Model helper assertions
        $this->assertTrue($rfq->isDeadlineElapsed());
        $this->assertTrue($rfq->isBiddingClosed());
        $this->assertTrue($rfq->canBeEvaluated());

        // Run Evaluation via Web Route
        $webResponse = $this->actingAs($manager)
            ->post("/inventory/purchases/rfqs/{$rfq->id}/evaluate");

        $webResponse->assertRedirect('/inventory/purchases');
        $webResponse->assertSessionHas('success');

        // Verify unsealing & status transitions
        $rfq->refresh();
        $this->assertEquals(RfqStatus::UnderEvaluation, $rfq->status);
        $this->assertNotNull($rfq->unsealed_at);
        $this->assertEquals($manager->id, $rfq->unsealed_by_user_id);
        $this->assertFalse($rfq->isSealed());

        $quoteA->refresh();
        $this->assertFalse((bool) $quoteA->is_sealed);
        $this->assertNotNull($quoteA->unsealed_at);
        $this->assertEquals(QuoteStatus::UnderReview->value, $quoteA->status);

        $quoteB->refresh();
        $this->assertFalse((bool) $quoteB->is_sealed);
        $this->assertNotNull($quoteB->unsealed_at);

        // Verify comparative evaluations generated
        $evaluations = SourcingEvaluation::where('sourcing_rfq_id', $rfq->id)->get();
        $this->assertCount(2, $evaluations);
        $winner = $evaluations->sortByDesc('composite_score')->first();
        $this->assertEquals($quoteA->id, $winner->supplier_quote_id);

        // Idempotent execution check: running evaluation again must update without duplicating rows
        $this->actingAs($manager)
            ->post("/inventory/purchases/rfqs/{$rfq->id}/evaluate")
            ->assertRedirect('/inventory/purchases');

        $this->assertCount(2, SourcingEvaluation::where('sourcing_rfq_id', $rfq->id)->get());
    }

    public function test_timezone_consistency_in_deadline_comparison(): void
    {
        $this->assertEquals('Asia/Manila', config('app.timezone'));

        // Current time in Manila timezone
        $now = now();
        $this->assertEquals('+08:00', $now->format('P'));

        // Future deadline in Manila
        $futureDeadline = $now->copy()->addMinutes(10);
        $this->assertTrue($now->lessThan($futureDeadline));

        // Past deadline in Manila
        $pastDeadline = $now->copy()->subMinutes(10);
        $this->assertTrue($now->greaterThanOrEqualTo($pastDeadline));

        $rfqFuture = new SourcingRfq(['submission_deadline' => $futureDeadline, 'bidding_type' => RfqBiddingType::Sealed]);
        $this->assertFalse($rfqFuture->isDeadlineElapsed());

        $rfqPast = new SourcingRfq(['submission_deadline' => $pastDeadline, 'bidding_type' => RfqBiddingType::Sealed]);
        $this->assertTrue($rfqPast->isDeadlineElapsed());
    }

    public function test_unauthorized_user_cannot_evaluate_rfq(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(100000.00);
        $regularUser = User::factory()->warehouseStaff()->create();

        $rfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-AUTH-TEST-01',
            'title' => 'Auth Test RFQ',
            'submission_deadline' => now()->subHour(),
            'status' => RfqStatus::Published,
            'created_by_user_id' => $manager->id,
        ]);

        // Web route without manage_procurement permission -> 403
        $this->actingAs($regularUser)
            ->post("/inventory/purchases/rfqs/{$rfq->id}/evaluate")
            ->assertForbidden();

        // API route without manage_procurement permission -> 403
        $this->actingAs($regularUser, 'sanctum')
            ->postJson("/api/v1/procurement/rfqs/{$rfq->id}/evaluate")
            ->assertForbidden();
    }

    public function test_rfq_cannot_be_evaluated_if_draft_cancelled_awarded_or_no_quotes(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(100000.00);
        $evaluationEngine = app(EvaluationEngine::class);

        // 1. Draft RFQ
        $draftRfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-DRAFT-01',
            'title' => 'Draft RFQ',
            'status' => RfqStatus::Draft,
            'submission_deadline' => now()->subDay(),
            'created_by_user_id' => $manager->id,
        ]);

        try {
            $evaluationEngine->evaluateRfq($draftRfq, $manager);
            $this->fail('Expected DomainException for draft RFQ');
        } catch (DomainException $e) {
            $this->assertStringContainsString('draft', $e->getMessage());
        }

        // 2. Cancelled RFQ
        $cancelledRfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-CANCELLED-01',
            'title' => 'Cancelled RFQ',
            'status' => RfqStatus::Cancelled,
            'submission_deadline' => now()->subDay(),
            'created_by_user_id' => $manager->id,
        ]);

        try {
            $evaluationEngine->evaluateRfq($cancelledRfq, $manager);
            $this->fail('Expected DomainException for cancelled RFQ');
        } catch (DomainException $e) {
            $this->assertStringContainsString('cancelled', $e->getMessage());
        }

        // 3. Awarded RFQ
        $awardedRfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-AWARDED-01',
            'title' => 'Awarded RFQ',
            'status' => RfqStatus::Awarded,
            'submission_deadline' => now()->subDay(),
            'created_by_user_id' => $manager->id,
        ]);

        try {
            $evaluationEngine->evaluateRfq($awardedRfq, $manager);
            $this->fail('Expected DomainException for awarded RFQ');
        } catch (DomainException $e) {
            $this->assertStringContainsString('already been awarded', $e->getMessage());
        }

        // 4. RFQ without quotes
        $emptyRfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-EMPTY-01',
            'title' => 'Empty Quotes RFQ',
            'status' => RfqStatus::Published,
            'submission_deadline' => now()->subDay(),
            'created_by_user_id' => $manager->id,
        ]);

        try {
            $evaluationEngine->evaluateRfq($emptyRfq, $manager);
            $this->fail('Expected DomainException for RFQ without quotes');
        } catch (DomainException $e) {
            $this->assertStringContainsString('No supplier quotes have been submitted', $e->getMessage());
        }
    }

    public function test_close_expired_rfqs_artisan_command_transitions_status(): void
    {
        [$costCenter, $budget, $manager] = $this->createCostCenterWithBudget(100000.00);

        $expiredRfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-SWEEP-01',
            'title' => 'Sweep Target RFQ',
            'status' => RfqStatus::Published,
            'submission_deadline' => now()->subHour(),
            'created_by_user_id' => $manager->id,
        ]);

        $activeRfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-SWEEP-02',
            'title' => 'Still Active RFQ',
            'status' => RfqStatus::Published,
            'submission_deadline' => now()->addDays(2),
            'created_by_user_id' => $manager->id,
        ]);

        $this->artisan('procurement:close-expired-rfqs')
            ->expectsOutputToContain('1 RFQ(s) transitioned to bidding closed.')
            ->assertSuccessful();

        $expiredRfq->refresh();
        $activeRfq->refresh();

        $this->assertEquals(RfqStatus::BiddingClosed, $expiredRfq->status);
        $this->assertEquals(RfqStatus::Published, $activeRfq->status);
    }
}

