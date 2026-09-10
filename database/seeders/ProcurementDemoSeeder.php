<?php

namespace Database\Seeders;

use App\Enums\ApprovalChainType;
use App\Enums\ApprovalStepStatus;
use App\Enums\ProcurementMethod;
use App\Enums\PurchaseOrderStatus;
use App\Enums\QuoteStatus;
use App\Enums\RequisitionStatus;
use App\Enums\RfqBiddingType;
use App\Enums\RfqStatus;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\ApprovalChain;
use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use App\Models\InventoryItem;
use App\Models\ProcurementCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderRevision;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\QuoteLineItem;
use App\Models\RfqLineItem;
use App\Models\RfqSupplierInvitation;
use App\Models\SourcingEvaluation;
use App\Models\SourcingRfq;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use App\Services\Procurement\POConversionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProcurementDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', UserRole::Administrator)->first() ?? User::factory()->administrator()->create();
        $manager = User::where('role', UserRole::InventoryManager)->first() ?? User::factory()->inventoryManager()->create();

        // 1. Seed Procurement Categories
        $categoriesData = [
            ['code' => 'PHARM', 'name' => 'Pharmaceuticals & Clinical Drugs', 'description' => 'Essential intravenous, oral, and specialty medications for hospital inpatient and outpatient dispensaries.'],
            ['code' => 'SURG', 'name' => 'Surgical Supplies & Consumables', 'description' => 'Sterile titanium plates, sutures, scalpel blades, and surgical disposables.'],
            ['code' => 'MEDEQ', 'name' => 'Medical Diagnostic & Therapeutic Devices', 'description' => 'Patient monitors, infusion pumps, ultrasound probes, and biomedical hardware.'],
            ['code' => 'LAB', 'name' => 'Laboratory Pathology & Reagents', 'description' => 'Clinical chemistry, hematology, and blood bank diagnostic testing kits.'],
            ['code' => 'HYGIENE', 'name' => 'Hospital Hygiene & Infection Control', 'description' => 'Personal protective equipment, sterile gloves, N95 masks, and medical disinfectants.'],
        ];

        $categories = [];
        foreach ($categoriesData as $cat) {
            $categories[$cat['code']] = ProcurementCategory::updateOrCreate(
                ['code' => $cat['code']],
                ['name' => $cat['name'], 'description' => $cat['description'], 'is_active' => true]
            );
        }

        // 2. Seed Cost Centers & Budgets (FY 2026)
        $costCentersData = [
            ['code' => 'CC-PHARM', 'name' => 'Department of Pharmacy', 'department' => 'Pharmacy', 'manager_id' => $manager->id, 'allocated' => 2500000.00, 'soft' => 120000.00, 'hard' => 450000.00, 'spent' => 680000.00],
            ['code' => 'CC-OR', 'name' => 'Operating Theatres Complex', 'department' => 'Surgery', 'manager_id' => $admin->id, 'allocated' => 4000000.00, 'soft' => 250000.00, 'hard' => 950000.00, 'spent' => 1100000.00],
            ['code' => 'CC-ICU', 'name' => 'Intensive Care Unit', 'department' => 'Nursing', 'manager_id' => $admin->id, 'allocated' => 1800000.00, 'soft' => 80000.00, 'hard' => 320000.00, 'spent' => 410000.00],
            ['code' => 'CC-ER', 'name' => 'Emergency & Trauma Department', 'department' => 'Emergency', 'manager_id' => $manager->id, 'allocated' => 2200000.00, 'soft' => 95000.00, 'hard' => 510000.00, 'spent' => 720000.00],
            ['code' => 'CC-LAB', 'name' => 'Diagnostic Pathology Laboratory', 'department' => 'Laboratory', 'manager_id' => $admin->id, 'allocated' => 1500000.00, 'soft' => 60000.00, 'hard' => 280000.00, 'spent' => 390000.00],
        ];

        $costCenters = [];
        foreach ($costCentersData as $ccData) {
            $cc = CostCenter::updateOrCreate(
                ['code' => $ccData['code']],
                [
                    'name' => $ccData['name'],
                    'department' => $ccData['department'],
                    'manager_id' => $ccData['manager_id'],
                    'is_active' => true,
                ]
            );
            $costCenters[$ccData['code']] = $cc;

            CostCenterBudget::updateOrCreate(
                ['cost_center_id' => $cc->id, 'fiscal_year' => 2026],
                [
                    'allocated_budget' => $ccData['allocated'],
                    'soft_encumbered' => $ccData['soft'],
                    'hard_encumbered' => $ccData['hard'],
                    'spent_amount' => $ccData['spent'],
                    'currency' => 'PHP',
                ]
            );
        }

        // 3. Ensure Verified Suppliers Exist
        $suppliers = [
            'Apex Medical Supplies Corp' => Supplier::updateOrCreate(
                ['name' => 'Apex Medical Supplies Corp'],
                [
                    'business_structure' => 'corporation',
                    'address' => '120 San Marcelino St, Ermita, Manila',
                    'email' => 'sales@apexmed.example.ph',
                    'phone' => '02-8521-4401',
                    'status' => SupplierStatus::Active,
                    'accreditation_status' => SupplierAccreditationStatus::Approved,
                ]
            ),
            'Sterling Healthcare Diagnostics Inc' => Supplier::updateOrCreate(
                ['name' => 'Sterling Healthcare Diagnostics Inc'],
                [
                    'business_structure' => 'corporation',
                    'address' => '45 Chino Roces Ave, Makati City',
                    'email' => 'tenders@sterlinghealth.example.ph',
                    'phone' => '02-8812-9930',
                    'status' => SupplierStatus::Active,
                    'accreditation_status' => SupplierAccreditationStatus::Approved,
                ]
            ),
            'BioCare Hospital Solutions Ltd' => Supplier::updateOrCreate(
                ['name' => 'BioCare Hospital Solutions Ltd'],
                [
                    'business_structure' => 'corporation',
                    'address' => '88 E. Rodriguez Jr. Ave, Quezon City',
                    'email' => 'institutional@biocare.example.ph',
                    'phone' => '02-8633-1188',
                    'status' => SupplierStatus::Active,
                    'accreditation_status' => SupplierAccreditationStatus::Approved,
                ]
            ),
        ];

        // 4. Ensure Inventory Items Exist
        $item1 = InventoryItem::firstOrCreate(
            ['sku' => 'MED-VENT-01'],
            [
                'name' => 'Adult Ventilator Breathing Circuit Dual-Heated',
                'unit' => 'box',
                'unit_cost' => 1250.00,
                'quantity_on_hand' => 45,
                'reorder_level' => 20,
                'status' => 'active',
            ]
        );

        $item2 = InventoryItem::firstOrCreate(
            ['sku' => 'MED-GAUZE-ST'],
            [
                'name' => 'Sterile Gauze Sponge 4x4 12-Ply (Pack of 100)',
                'unit' => 'pack',
                'unit_cost' => 185.00,
                'quantity_on_hand' => 220,
                'reorder_level' => 50,
                'status' => 'active',
            ]
        );

        $item3 = InventoryItem::firstOrCreate(
            ['sku' => 'MED-TITAN-PL'],
            [
                'name' => 'Titanium Locking Reconstruction Plate 3.5mm',
                'unit' => 'piece',
                'unit_cost' => 4500.00,
                'quantity_on_hand' => 30,
                'reorder_level' => 10,
                'status' => 'active',
            ]
        );

        $item4 = InventoryItem::firstOrCreate(
            ['sku' => 'MED-INF-SET'],
            [
                'name' => 'Precision IV Infusion Set with Micro-Drip',
                'unit' => 'box',
                'unit_cost' => 320.00,
                'quantity_on_hand' => 180,
                'reorder_level' => 40,
                'status' => 'active',
            ]
        );

        // 5. Seed Realistic Purchase Requests
        $pr1 = PurchaseRequest::updateOrCreate(
            ['pr_number' => 'PR-20260910-1001'],
            [
                'title' => 'ICU Mechanical Ventilator Breathing Circuits & Filter Packs',
                'description' => 'Urgent replenishment for ICU critical care beds and ventilator circuits.',
                'requester_id' => $manager->id,
                'cost_center_id' => $costCenters['CC-ICU']->id,
                'procurement_category_id' => $categories['MEDEQ']->id,
                'total_estimated_amount' => 62500.00,
                'currency' => 'PHP',
                'priority' => 'urgent',
                'status' => RequisitionStatus::PendingApproval,
                'is_emergency' => false,
                'submitted_at' => now()->subHours(4),
            ]
        );

        PurchaseRequestLine::updateOrCreate(
            ['purchase_request_id' => $pr1->id, 'line_number' => 1],
            [
                'item_id' => $item1->id,
                'gl_account_code' => 'GL-MED-1001',
                'item_description' => $item1->name,
                'quantity' => 50,
                'uom' => 'box',
                'estimated_unit_price' => 1250.00,
                'estimated_total_price' => 62500.00,
                'need_by_date' => now()->addDays(7)->toDateString(),
            ]
        );

        // DOA Chain for PR1
        $chain1 = ApprovalChain::updateOrCreate(
            ['chain_type' => ApprovalChainType::PurchaseRequest, 'target_id' => $pr1->id],
            [
                'total_commitment_amount' => 62500.00,
                'status' => 'pending',
            ]
        );

        $chain1->steps()->updateOrCreate(
            ['step_number' => 1],
            [
                'required_role' => UserRole::InventoryManager->value,
                'status' => ApprovalStepStatus::Pending,
                'threshold_min' => 0.0,
                'threshold_max' => 50000.0,
            ]
        );

        $chain1->steps()->updateOrCreate(
            ['step_number' => 2],
            [
                'required_role' => UserRole::Administrator->value,
                'status' => ApprovalStepStatus::Pending,
                'threshold_min' => 50000.01,
                'threshold_max' => 250000.0,
            ]
        );

        // PR 2: Approved Requisition ready for RFQ Packaging
        $pr2 = PurchaseRequest::updateOrCreate(
            ['pr_number' => 'PR-20260908-1002'],
            [
                'title' => 'Emergency Ward Sterile Consumables & Infusion Kits',
                'description' => 'Monthly trauma ward consumable replenishment.',
                'requester_id' => $manager->id,
                'cost_center_id' => $costCenters['CC-ER']->id,
                'procurement_category_id' => $categories['HYGIENE']->id,
                'total_estimated_amount' => 84250.00,
                'currency' => 'PHP',
                'priority' => 'high',
                'status' => RequisitionStatus::Approved,
                'is_emergency' => false,
                'submitted_at' => now()->subDays(2),
                'approved_at' => now()->subDay(),
            ]
        );

        PurchaseRequestLine::updateOrCreate(
            ['purchase_request_id' => $pr2->id, 'line_number' => 1],
            [
                'item_id' => $item2->id,
                'gl_account_code' => 'GL-MED-1002',
                'item_description' => $item2->name,
                'quantity' => 150,
                'uom' => 'pack',
                'estimated_unit_price' => 185.00,
                'estimated_total_price' => 27750.00,
                'need_by_date' => now()->addDays(10)->toDateString(),
            ]
        );

        PurchaseRequestLine::updateOrCreate(
            ['purchase_request_id' => $pr2->id, 'line_number' => 2],
            [
                'item_id' => $item4->id,
                'gl_account_code' => 'GL-MED-1004',
                'item_description' => $item4->name,
                'quantity' => 150,
                'uom' => 'box',
                'estimated_unit_price' => 320.00,
                'estimated_total_price' => 56500.00,
                'need_by_date' => now()->addDays(10)->toDateString(),
            ]
        );

        // PR 3 & RFQ Sourcing Event
        $pr3 = PurchaseRequest::updateOrCreate(
            ['pr_number' => 'PR-20260905-1003'],
            [
                'title' => 'Operating Theatre Titanium Orthopedic Reconstruction Plates',
                'description' => 'Orthopedic surgery department trauma implants.',
                'requester_id' => $admin->id,
                'cost_center_id' => $costCenters['CC-OR']->id,
                'procurement_category_id' => $categories['SURG']->id,
                'total_estimated_amount' => 225000.00,
                'currency' => 'PHP',
                'priority' => 'medium',
                'status' => RequisitionStatus::Approved,
                'submitted_at' => now()->subDays(5),
                'approved_at' => now()->subDays(4),
            ]
        );

        $pr3Line = PurchaseRequestLine::updateOrCreate(
            ['purchase_request_id' => $pr3->id, 'line_number' => 1],
            [
                'item_id' => $item3->id,
                'gl_account_code' => 'GL-SURG-1003',
                'item_description' => $item3->name,
                'quantity' => 50,
                'uom' => 'piece',
                'estimated_unit_price' => 4500.00,
                'estimated_total_price' => 225000.00,
                'need_by_date' => now()->addDays(14)->toDateString(),
            ]
        );

        // Sourcing RFQ
        $rfq = SourcingRfq::updateOrCreate(
            ['rfq_number' => 'RFQ-20260906-001'],
            [
                'title' => 'Sealed RFQ: Titanium Orthopedic Reconstruction Plates (50 Units)',
                'description' => 'Competitive sealed tender for Grade 5 Titanium locking reconstruction plates 3.5mm.',
                'purchase_request_id' => $pr3->id,
                'created_by_user_id' => $manager->id,
                'procurement_method' => ProcurementMethod::RequestForQuotation,
                'bidding_type' => RfqBiddingType::Sealed,
                'submission_deadline' => now()->addDays(5),
                'status' => RfqStatus::Published,
                'published_at' => now()->subDays(3),
                'terms_conditions' => 'Standard Hospital SCM Payment Terms: Net 30 days upon DDP hospital delivery inspection.',
                'weight_price' => 0.50,
                'weight_technical' => 0.25,
                'weight_quality' => 0.15,
                'weight_lead_time' => 0.10,
            ]
        );

        $rfqLine = RfqLineItem::updateOrCreate(
            ['sourcing_rfq_id' => $rfq->id, 'line_number' => 1],
            [
                'pr_line_id' => $pr3Line->id,
                'item_id' => $item3->id,
                'item_description' => $item3->name,
                'target_quantity' => 50,
                'uom' => 'piece',
                'max_budget_unit_price' => 4500.00,
            ]
        );

        // Invitations
        foreach ($suppliers as $supplier) {
            RfqSupplierInvitation::updateOrCreate(
                ['sourcing_rfq_id' => $rfq->id, 'supplier_id' => $supplier->id],
                [
                    'portal_token' => Str::random(40),
                    'status' => 'invited',
                    'invited_at' => now()->subDays(3),
                ]
            );
        }

        // Quote from Apex Medical
        $quoteApex = SupplierQuote::updateOrCreate(
            ['quote_number' => 'QTN-APEX-20260907'],
            [
                'sourcing_rfq_id' => $rfq->id,
                'supplier_id' => $suppliers['Apex Medical Supplies Corp']->id,
                'quoted_price' => 4200.00,
                'total_bid_amount' => 210000.00,
                'currency' => 'PHP',
                'exchange_rate' => 1.0,
                'incoterms' => 'DDP',
                'payment_terms' => 'Net 30',
                'validity_end_date' => now()->addDays(30),
                'status' => QuoteStatus::Submitted->value,
                'is_sealed' => true,
            ]
        );

        QuoteLineItem::updateOrCreate(
            ['supplier_quote_id' => $quoteApex->id, 'rfq_line_item_id' => $rfqLine->id],
            [
                'offered_unit_price' => 4200.00,
                'offered_quantity' => 50,
                'shipping_cost' => 1500.00,
                'discount_amount' => 5000.00,
                'lead_time_days' => 4,
                'technical_score' => 92.00,
                'technical_compliance' => true,
                'landed_cost' => 206500.00,
            ]
        );

        // Quote from Sterling Healthcare
        $quoteSterling = SupplierQuote::updateOrCreate(
            ['quote_number' => 'QTN-STR-20260907'],
            [
                'sourcing_rfq_id' => $rfq->id,
                'supplier_id' => $suppliers['Sterling Healthcare Diagnostics Inc']->id,
                'quoted_price' => 4350.00,
                'total_bid_amount' => 217500.00,
                'currency' => 'PHP',
                'exchange_rate' => 1.0,
                'incoterms' => 'DDP',
                'payment_terms' => 'Net 30',
                'validity_end_date' => now()->addDays(30),
                'status' => QuoteStatus::Submitted->value,
                'is_sealed' => true,
            ]
        );

        QuoteLineItem::updateOrCreate(
            ['supplier_quote_id' => $quoteSterling->id, 'rfq_line_item_id' => $rfqLine->id],
            [
                'offered_unit_price' => 4350.00,
                'offered_quantity' => 50,
                'shipping_cost' => 500.00,
                'discount_amount' => 2500.00,
                'lead_time_days' => 2,
                'technical_score' => 96.00,
                'technical_compliance' => true,
                'landed_cost' => 215500.00,
            ]
        );

        // 6. Seed Realistic Multi-Line Purchase Orders
        $po1 = PurchaseOrder::updateOrCreate(
            ['po_number' => 'PO-20260908-0001'],
            [
                'supplier_id' => $suppliers['Sterling Healthcare Diagnostics Inc']->id,
                'cost_center_id' => $costCenters['CC-PHARM']->id,
                'item_id' => $item4->id,
                'quantity' => 200,
                'unit_cost' => 310.00,
                'total_amount' => 62000.00,
                'total_encumbered_amount' => 62000.00,
                'currency' => 'PHP',
                'exchange_rate' => 1.0,
                'payment_terms' => 'Net 30',
                'incoterms' => 'DDP',
                'version' => 'PO-REV1',
                'revision_number' => 1,
                'status' => PurchaseOrderStatus::Dispatched->value,
                'requested_at' => now()->subDays(2),
                'dispatched_at' => now()->subDays(2),
                'notes' => 'Dispatched under contract PO-2026-PHARM. Scheduled dock delivery.',
            ]
        );

        PurchaseOrderLine::updateOrCreate(
            ['purchase_order_id' => $po1->id, 'line_number' => 1],
            [
                'item_id' => $item4->id,
                'ordered_quantity' => 200,
                'received_quantity' => 0,
                'unit_price' => 310.00,
                'total_line_amount' => 62000.00,
                'line_status' => 'open',
            ]
        );

        $poService = app(POConversionService::class);
        $po1->cxml_payload = $poService->generateCxmlPayload($po1);
        $po1->save();

        // PO 2: Completed / Received Order
        $po2 = PurchaseOrder::updateOrCreate(
            ['po_number' => 'PO-20260901-0002'],
            [
                'supplier_id' => $suppliers['Apex Medical Supplies Corp']->id,
                'cost_center_id' => $costCenters['CC-ER']->id,
                'item_id' => $item2->id,
                'quantity' => 300,
                'unit_cost' => 175.00,
                'total_amount' => 52500.00,
                'total_encumbered_amount' => 52500.00,
                'currency' => 'PHP',
                'exchange_rate' => 1.0,
                'payment_terms' => 'Net 30',
                'incoterms' => 'DDP',
                'version' => 'PO-REV1',
                'revision_number' => 1,
                'status' => 'received',
                'requested_at' => now()->subDays(9),
                'dispatched_at' => now()->subDays(8),
                'received_at' => now()->subDays(3),
                'notes' => 'Received and inspected at warehouse dock by central supply.',
            ]
        );

        PurchaseOrderLine::updateOrCreate(
            ['purchase_order_id' => $po2->id, 'line_number' => 1],
            [
                'item_id' => $item2->id,
                'ordered_quantity' => 300,
                'received_quantity' => 300,
                'unit_price' => 175.00,
                'total_line_amount' => 52500.00,
                'line_status' => 'received',
            ]
        );
        $po2->cxml_payload = $poService->generateCxmlPayload($po2);
        $po2->save();
    }
}
