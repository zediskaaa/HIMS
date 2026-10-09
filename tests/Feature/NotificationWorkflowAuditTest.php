<?php

namespace Tests\Feature;

use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RfqStatus;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierDocumentStatus;
use App\Enums\SupplierStatus;
use App\Enums\UserRole;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use App\Models\InventoryItem;
use App\Models\LogisticsDocument;
use App\Models\MaterialRequisition;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\RfqSupplierInvitation;
use App\Models\SourcingRfq;
use App\Models\Supplier;
use App\Models\SupplierDiscrepancy;
use App\Models\SupplierDocument;
use App\Models\SupplierQuote;
use App\Models\User;
use App\Services\HimsNotificationService;
use App\Services\HimsNotificationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationWorkflowAuditTest extends TestCase
{
    use RefreshDatabase;

    private HimsNotificationWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = app(HimsNotificationWorkflowService::class);
    }

    private function createSupplier(string $name = 'Alpha Pharmaceuticals'): Supplier
    {
        return Supplier::create([
            'name' => $name,
            'business_structure' => 'corporation',
            'address' => '123 Medical Ave, Manila',
            'email' => strtolower(str_replace(' ', '', $name)).'@example.test',
            'status' => SupplierStatus::Active,
            'accreditation_status' => SupplierAccreditationStatus::Approved,
        ]);
    }

    private function createSupplierUser(Supplier $supplier, UserRole $role = UserRole::VendorAdministrator): User
    {
        return User::factory()->create([
            'role' => $role,
            'supplier_id' => $supplier->id,
            'department' => 'External Supplier',
        ]);
    }

    public function test_supplier_company_profile_submission_and_revision_requests(): void
    {
        $supplierA = $this->createSupplier('Alpha Pharma');
        $supplierB = $this->createSupplier('Beta Medical');

        $hospitalManager = User::factory()->inventoryManager()->create();
        $supplierAAdmin = $this->createSupplierUser($supplierA, UserRole::VendorAdministrator);
        $supplierBAdmin = $this->createSupplierUser($supplierB, UserRole::VendorAdministrator);

        // 1. Supplier A submits profile updates
        $this->workflow->supplierProfileSubmitted($supplierA, $supplierAAdmin);

        $hospitalNotification = $hospitalManager->notifications()->first();
        $this->assertNotNull($hospitalNotification);
        $this->assertSame(NotificationDestination::SupplierManagement->value, $hospitalNotification->data['destination']);
        $this->assertSame($supplierA->id, $hospitalNotification->data['route_parameters']['supplier']);
        $this->assertSame(0, $supplierBAdmin->notifications()->count());

        // 2. Hospital requests changes to profile
        $this->workflow->supplierProfileChangesRequested($supplierA, $hospitalManager, 'Please upload 2026 Mayor permit.');

        $supplierNotification = $supplierAAdmin->notifications()->first();
        $this->assertNotNull($supplierNotification);
        $this->assertSame(NotificationDestination::SupplierCompanyProfile->value, $supplierNotification->data['destination']);
        $this->assertStringContainsString('Mayor permit', $supplierNotification->data['message']);
        $this->assertSame(0, $supplierBAdmin->fresh()->notifications()->count());
        $this->assertSame(1, $hospitalManager->fresh()->notifications()->count()); // Hospital reviewer not notified of own action
    }

    public function test_supplier_accreditation_decisions_notify_supplier_administrators(): void
    {
        $supplier = $this->createSupplier();
        $hospitalManager = User::factory()->inventoryManager()->create();
        $supplierAdmin = $this->createSupplierUser($supplier, UserRole::VendorAdministrator);
        $supplierOps = $this->createSupplierUser($supplier, UserRole::VendorOperations);

        // Approval
        $this->workflow->supplierAccreditationDecided($supplier, 'approved', $hospitalManager);

        $this->assertSame(1, $supplierAdmin->notifications()->count());
        $this->assertSame(0, $supplierOps->notifications()->count()); // Ops does not have SupplierManageProfile

        $notification = $supplierAdmin->notifications()->first();
        $this->assertSame(NotificationDestination::SupplierCompanyProfile->value, $notification->data['destination']);
        $this->assertSame('Accreditation approved', $notification->data['title']);

        // Rejection
        $this->workflow->supplierAccreditationDecided($supplier, 'rejected', $hospitalManager, 'Missing tax clearance');

        $this->assertSame(2, $supplierAdmin->fresh()->notifications()->count());
        $rejectionNotice = $supplierAdmin->fresh()->notifications()->where('data->title', 'Accreditation rejected')->first();
        $this->assertNotNull($rejectionNotice);
        $this->assertSame('Accreditation rejected', $rejectionNotice->data['title']);
        $this->assertStringContainsString('Missing tax clearance', $rejectionNotice->data['message']);
    }

    public function test_compliance_document_lifecycle_notifications(): void
    {
        $supplier = $this->createSupplier();
        $hospitalManager = User::factory()->inventoryManager()->create();
        $supplierAdmin = $this->createSupplierUser($supplier, UserRole::VendorAdministrator);

        $document = $supplier->documents()->create([
            'document_type' => 'FDA License to Operate',
            'disk' => 'local',
            'path' => 'supplier-docs/fda.pdf',
            'original_name' => 'fda.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'uploaded_by' => $supplierAdmin->id,
            'verification_status' => SupplierDocumentStatus::Pending,
        ]);

        // Upload notification to hospital
        $this->workflow->supplierComplianceUploaded($supplier, $document, $supplierAdmin);

        $this->assertSame(1, $hospitalManager->notifications()->count());
        $hospitalNotice = $hospitalManager->notifications()->first();
        $this->assertSame(NotificationDestination::SupplierManagement->value, $hospitalNotice->data['destination']);
        $this->assertStringContainsString('FDA License to Operate', $hospitalNotice->data['message']);

        // Verification notification back to supplier
        $this->workflow->supplierDocumentVerified($supplier, $document, $hospitalManager, true);

        $this->assertSame(1, $supplierAdmin->notifications()->count());
        $supplierNotice = $supplierAdmin->notifications()->first();
        $this->assertSame(NotificationDestination::SupplierCompliance->value, $supplierNotice->data['destination']);
        $this->assertSame('Compliance document verified', $supplierNotice->data['title']);
    }

    public function test_rfq_publication_and_bid_submission_workflow(): void
    {
        $supplierA = $this->createSupplier('Supplier Alpha');
        $supplierB = $this->createSupplier('Supplier Beta');
        $hospitalEvaluator = User::factory()->inventoryManager()->create();

        $supplierAUser = $this->createSupplierUser($supplierA, UserRole::VendorAdministrator);
        $supplierBUser = $this->createSupplierUser($supplierB, UserRole::VendorAdministrator);

        $rfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-2026-TEST-001',
            'title' => 'Emergency Antibiotics Supply',
            'created_by_user_id' => $hospitalEvaluator->id,
            'status' => RfqStatus::Published,
            'submission_deadline' => now()->addDays(7),
            'currency' => 'PHP',
        ]);

        $invitationA = RfqSupplierInvitation::create([
            'sourcing_rfq_id' => $rfq->id,
            'supplier_id' => $supplierA->id,
            'portal_token' => \Illuminate\Support\Str::random(32),
            'invited_at' => now(),
        ]);

        // Publish RFQ: Only invited Supplier A gets notified
        $this->workflow->rfqPublished($rfq, [$supplierA]);

        $this->assertSame(1, $supplierAUser->notifications()->count());
        $this->assertSame(0, $supplierBUser->notifications()->count());

        $rfqNotice = $supplierAUser->notifications()->first();
        $this->assertSame(NotificationDestination::SupplierRfqs->value, $rfqNotice->data['destination']);
        $this->assertSame($supplierA->id, $rfqNotice->data['route_parameters']['supplier']);
        $this->assertSame($invitationA->id, $rfqNotice->data['route_parameters']['invitation']);

        // Supplier A submits bid quote
        $quote = SupplierQuote::create([
            'sourcing_rfq_id' => $rfq->id,
            'supplier_id' => $supplierA->id,
            'quote_number' => 'QTE-ALPHA-001',
            'total_amount' => 50000,
            'submitted_at' => now(),
        ]);

        $this->workflow->rfqBidSubmitted($rfq, $quote, $supplierA);

        $this->assertSame(1, $hospitalEvaluator->notifications()->count());
        $evalNotice = $hospitalEvaluator->notifications()->first();
        $this->assertSame(NotificationDestination::Procurement->value, $evalNotice->data['destination']);
        $this->assertSame('sourcing', $evalNotice->data['route_parameters']['tab']);
    }

    public function test_rfq_award_conversion_notifies_winner_and_losing_bidders(): void
    {
        $supplierA = $this->createSupplier('Winning Supplier');
        $supplierB = $this->createSupplier('Losing Supplier');
        $admin = User::factory()->administrator()->create();

        $supplierAUser = $this->createSupplierUser($supplierA, UserRole::VendorAdministrator);
        $supplierBUser = $this->createSupplierUser($supplierB, UserRole::VendorAdministrator);

        $rfq = SourcingRfq::create([
            'rfq_number' => 'RFQ-AWARD-001',
            'title' => 'Surgical Equipment Bidding',
            'created_by_user_id' => $admin->id,
            'status' => RfqStatus::BiddingClosed,
            'submission_deadline' => now()->subDay(),
            'currency' => 'PHP',
        ]);

        $winningQuote = SupplierQuote::create([
            'sourcing_rfq_id' => $rfq->id,
            'supplier_id' => $supplierA->id,
            'quote_number' => 'QTE-WIN-001',
            'total_amount' => 75000,
            'is_awarded' => true,
        ]);

        $losingQuote = SupplierQuote::create([
            'sourcing_rfq_id' => $rfq->id,
            'supplier_id' => $supplierB->id,
            'quote_number' => 'QTE-LOSE-001',
            'total_amount' => 82000,
            'is_awarded' => false,
        ]);

        $this->workflow->rfqAwardDecided($rfq, $winningQuote, $admin);

        // Winner gets award notification
        $this->assertSame(1, $supplierAUser->notifications()->count());
        $winNotice = $supplierAUser->notifications()->first();
        $this->assertSame(NotificationDestination::SupplierRfqs->value, $winNotice->data['destination']);
        $this->assertSame('RFQ award decision', $winNotice->data['title']);

        // Loser gets bidding concluded notification
        $this->assertSame(1, $supplierBUser->notifications()->count());
        $lossNotice = $supplierBUser->notifications()->first();
        $this->assertSame(NotificationDestination::SupplierRfqs->value, $lossNotice->data['destination']);
        $this->assertSame('Bidding concluded', $lossNotice->data['title']);
    }

    public function test_purchase_order_lifecycle_notifications_and_destination_navigation(): void
    {
        $supplier = $this->createSupplier();
        $poIssuer = User::factory()->inventoryManager()->create(['department' => 'Procurement']);
        $warehouseReceiver = User::factory()->warehouseStaff()->create();

        $supplierOps = $this->createSupplierUser($supplier, UserRole::VendorOperations);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-AUDIT-2026-001',
            'supplier_id' => $supplier->id,
            'created_by' => $poIssuer->id,
            'quantity' => 100,
            'unit_cost' => 50,
            'total_amount' => 5000,
            'status' => PurchaseOrderStatus::Approved,
            'requested_at' => now(),
        ]);

        // 1. PO Issued
        $this->workflow->purchaseOrderIssued($po);

        $this->assertSame(1, $supplierOps->notifications()->count());
        $poNotice = $supplierOps->notifications()->first();
        $this->assertSame(NotificationDestination::SupplierOrder->value, $poNotice->data['destination']);
        $this->assertSame($po->id, $poNotice->data['route_parameters']['purchase_order']);

        // Supplier opens notification link and redirects to supplier.orders.show
        $this->actingAs($supplierOps)
            ->get(route('notifications.open', $poNotice->id))
            ->assertRedirect(route('supplier.orders.show', $po));

        $this->assertNotNull($poNotice->fresh()->read_at);

        // 2. PO Acknowledged by supplier
        $this->workflow->purchaseOrderAcknowledged($po, 'accepted');

        $this->assertSame(1, $poIssuer->notifications()->count());
        $ackNotice = $poIssuer->notifications()->first();
        $this->assertSame(NotificationDestination::Procurement->value, $ackNotice->data['destination']);
        $this->assertSame('Purchase order accepted by supplier', $ackNotice->data['title']);

        // 3. Shipment dispatched (ASN)
        $this->workflow->purchaseOrderShipmentDispatched($po, 'ASN-2026-001', $supplierOps);

        $this->assertSame(1, $warehouseReceiver->notifications()->count());
        $shipNotice = $warehouseReceiver->notifications()->first();
        $this->assertSame(NotificationDestination::GoodsReceipt->value, $shipNotice->data['destination']);
        $this->assertSame('Advance shipping notice (ASN) received', $shipNotice->data['title']);

        // 4. Discrepancy reported on delivery
        $item = InventoryItem::create([
            'name' => 'Vials',
            'sku' => 'MED-VIAL-001',
            'unit' => 'box',
            'status' => 'active',
        ]);
        $poLine = PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'item_id' => $item->id,
            'quantity' => 100,
            'unit_cost' => 50,
            'total_amount' => 5000,
        ]);
        $grn = GoodsReceiptNote::create([
            'grn_number' => 'GRN-AUDIT-001',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'received_by_id' => $warehouseReceiver->id,
            'receipt_status' => 'under_inspection',
            'received_at' => now(),
        ]);
        $grnLine = GoodsReceiptNoteLine::create([
            'goods_receipt_note_id' => $grn->id,
            'po_line_id' => $poLine->id,
            'item_id' => $item->id,
            'ordered_quantity' => 100,
            'shipped_quantity' => 100,
            'received_quantity' => 95,
            'accepted_quantity' => 95,
            'rejected_quantity' => 5,
            'quarantined_quantity' => 0,
            'unit_cost' => 50,
            'status' => 'accepted',
            'item_condition' => 'good',
            'discrepancy_type' => 'damage',
        ]);
        $discrepancy = SupplierDiscrepancy::create([
            'supplier_id' => $supplier->id,
            'grn_line_item_id' => $grnLine->id,
            'status' => 'open',
        ]);

        $this->workflow->discrepancyReported($discrepancy);
        $this->assertSame(2, $supplierOps->fresh()->notifications()->count());

        // Supplier responds to discrepancy
        $this->workflow->discrepancyResponded($discrepancy, $supplierOps);
        $this->assertSame(2, $warehouseReceiver->fresh()->notifications()->count());
    }

    public function test_material_requisition_decisions_notify_requester_and_allow_navigation(): void
    {
        $requester = User::factory()->pharmacyStaff()->create(['department' => 'Ward 3']);
        $approver = User::factory()->inventoryManager()->create();

        $requisition = MaterialRequisition::create([
            'requisition_number' => 'REQ-2026-0001',
            'requesting_user_id' => $requester->id,
            'department' => 'Ward 3',
            'status' => 'pending_approval',
            'urgency' => 'high',
            'justification' => 'Post-op patient care',
            'required_date' => now()->addDays(2),
        ]);

        // Approver approves requisition
        $this->workflow->materialRequisitionDecided($requisition, 'approved', $approver);

        $this->assertSame(1, $requester->notifications()->count());
        $reqNotice = $requester->notifications()->first();
        $this->assertSame(NotificationDestination::MaterialRequisition->value, $reqNotice->data['destination']);
        $this->assertSame('Requisition approved', $reqNotice->data['title']);

        // Requester clicks notification: they DO NOT have ApproveRequisition, but can still view requisition!
        $this->assertFalse($requester->hasPermission(Permission::ApproveRequisition));
        $this->assertTrue($requester->hasPermission(Permission::CreateRequisition));

        $this->actingAs($requester)
            ->get(route('notifications.open', $reqNotice->id))
            ->assertRedirect(route('inventory.requisitions.show', $requisition));

        $this->assertNotNull($reqNotice->fresh()->read_at);
    }

    public function test_logistics_document_workflow_notifications(): void
    {
        $uploader = User::factory()->warehouseStaff()->create();
        $verifier = User::factory()->inventoryManager()->create();

        $logisticsDoc = LogisticsDocument::create([
            'tracking_number' => 'LOG-DOC-2026-001',
            'document_type' => 'delivery_receipt',
            'title' => 'DR-998877 Delivery Receipt',
            'status' => 'pending_verification',
            'file_path' => 'logistics/dr-998877.pdf',
            'file_name' => 'dr-998877.pdf',
            'original_name' => 'dr-998877.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 2048,
            'disk' => 'local',
            'sha256_checksum' => hash('sha256', 'mock-content'),
            'uploaded_by_id' => $uploader->id,
        ]);

        // Uploader uploads logistics document -> verifier notified
        $this->workflow->logisticsDocumentUploaded($logisticsDoc, $uploader);

        $this->assertSame(1, $verifier->notifications()->count());
        $docNotice = $verifier->notifications()->first();
        $this->assertSame(NotificationDestination::LogisticsDocuments->value, $docNotice->data['destination']);
        $this->assertSame(0, $uploader->notifications()->count()); // Uploader not notified of own action

        // Verifier verifies document -> uploader notified
        $this->workflow->logisticsDocumentVerified($logisticsDoc, $verifier, 'verified');

        $this->assertSame(1, $uploader->notifications()->count());
        $verifyNotice = $uploader->notifications()->first();
        $this->assertSame(NotificationDestination::LogisticsDocuments->value, $verifyNotice->data['destination']);
        $this->assertSame('Logistics document verified', $verifyNotice->data['title']);
    }

    public function test_supplier_user_notification_feed_access_and_cross_tenant_isolation(): void
    {
        $supplierA = $this->createSupplier('Supplier One');
        $supplierB = $this->createSupplier('Supplier Two');

        $userA = $this->createSupplierUser($supplierA, UserRole::VendorAdministrator);
        $userB = $this->createSupplierUser($supplierB, UserRole::VendorAdministrator);

        $notifications = app(HimsNotificationService::class);

        $notifications->sendToUser(
            $userA,
            'supplier-a-notice',
            'Action Required for Supplier A',
            'Please update your profile.',
            NotificationPriority::Info,
            NotificationDestination::SupplierCompanyProfile,
            ['supplier' => $supplierA->id],
        );

        $notifications->sendToUser(
            $userB,
            'supplier-b-notice',
            'Action Required for Supplier B',
            'Your account is in good standing.',
            NotificationPriority::Info,
            NotificationDestination::SupplierCompanyProfile,
            ['supplier' => $supplierB->id],
        );

        $noticeA = $userA->notifications()->firstOrFail();
        $noticeB = $userB->notifications()->firstOrFail();

        // User A gets JSON feed
        $feed = $this->actingAs($userA)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertSee('Action Required for Supplier A')
            ->assertDontSee('Action Required for Supplier B');

        // User A cannot read User B's notification
        $this->actingAs($userA)->patch(route('notifications.read', $noticeB->id))->assertNotFound();
        $this->assertNull($noticeB->fresh()->read_at);

        // User A marks own notification as read
        $this->actingAs($userA)->patch(route('notifications.read', $noticeA->id))->assertRedirect();
        $this->assertNotNull($noticeA->fresh()->read_at);

        // Unread count is now 0
        $this->actingAs($userA)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }
}
