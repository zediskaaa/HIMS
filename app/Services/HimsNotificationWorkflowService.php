<?php

namespace App\Services;

use App\Enums\ApprovalChainType;
use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use App\Enums\Permission;
use App\Models\ApprovalChain;
use App\Models\LogisticsDocument;
use App\Models\MaterialRequisition;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SourcingRfq;
use App\Models\Supplier;
use App\Models\SupplierDiscrepancy;
use App\Models\SupplierDocument;
use App\Models\SupplierInvitation;
use App\Models\SupplierProduct;
use App\Models\SupplierQuote;
use App\Models\User;

class HimsNotificationWorkflowService
{
    public function __construct(
        private readonly HimsNotificationService $notifications,
    ) {}

    public function supplierProfileSubmitted(Supplier $supplier, User $actor): void
    {
        $this->notifications->sendToPermission(
            Permission::ManageSuppliers,
            "supplier:{$supplier->id}:profile-submitted:".now()->toDateString(),
            'Supplier company profile submitted',
            "{$supplier->name} submitted company profile updates for review.",
            NotificationPriority::Info,
            NotificationDestination::SupplierManagement,
            ['supplier' => $supplier->id],
            except: $actor,
        );
    }

    public function supplierProfileChangesRequested(Supplier $supplier, User $actor, string $notes): void
    {
        $this->notifications->sendToSupplierPermission(
            $supplier,
            Permission::SupplierManageProfile,
            "supplier:{$supplier->id}:profile-changes-requested:".now()->timestamp,
            'Profile revision requested',
            "Hospital requested changes to company profile: {$notes}",
            NotificationPriority::Warning,
            NotificationDestination::SupplierCompanyProfile,
            ['supplier' => $supplier->id],
            except: $actor,
        );
    }

    public function supplierAccreditationSubmitted(Supplier $supplier, User $actor): void
    {
        $this->notifications->sendToPermission(
            Permission::ManageSuppliers,
            "supplier:{$supplier->id}:accreditation-submitted:".now()->toDateString(),
            'Supplier accreditation review requested',
            "{$supplier->name} submitted company profile for accreditation review.",
            NotificationPriority::Info,
            NotificationDestination::SupplierManagement,
            ['supplier' => $supplier->id],
            except: $actor,
        );
    }

    public function supplierAccreditationDecided(Supplier $supplier, string $decision, User $actor, ?string $notes = null): void
    {
        $isApproved = $decision === 'approved';
        $title = $isApproved ? 'Accreditation approved' : 'Accreditation rejected';
        $message = $isApproved
            ? "Your supplier accreditation for {$supplier->name} has been approved."
            : "Your supplier accreditation for {$supplier->name} was rejected. Reason: {$notes}";
        $priority = $isApproved ? NotificationPriority::Info : NotificationPriority::Critical;

        $this->notifications->sendToSupplierPermission(
            $supplier,
            Permission::SupplierManageProfile,
            "supplier:{$supplier->id}:accreditation-{$decision}:".now()->timestamp,
            $title,
            $message,
            $priority,
            NotificationDestination::SupplierCompanyProfile,
            ['supplier' => $supplier->id],
            except: $actor,
        );
    }

    public function supplierStatusChanged(Supplier $supplier, string $newStatus, User $actor, ?string $reason = null): void
    {
        $isSuspended = $newStatus === 'suspended';
        $title = $isSuspended ? 'Supplier account suspended' : 'Supplier account reactivated';
        $message = $isSuspended
            ? "Your supplier account has been suspended. Reason: {$reason}"
            : 'Your supplier account has been reactivated.';
        $priority = $isSuspended ? NotificationPriority::Critical : NotificationPriority::Info;

        $this->notifications->sendToSupplierPermission(
            $supplier,
            Permission::SupplierManageProfile,
            "supplier:{$supplier->id}:status-{$newStatus}:".now()->timestamp,
            $title,
            $message,
            $priority,
            NotificationDestination::SupplierCompanyProfile,
            ['supplier' => $supplier->id],
            except: $actor,
        );
    }

    public function supplierDocumentVerified(
        Supplier $supplier,
        SupplierDocument $document,
        User $actor,
        bool $verified,
        ?string $notes = null
    ): void {
        $title = $verified ? 'Compliance document verified' : 'Compliance document rejected';
        $message = $verified
            ? "Document '{$document->document_type}' has been verified."
            : "Document '{$document->document_type}' was rejected. Notes: {$notes}";
        $priority = $verified ? NotificationPriority::Info : NotificationPriority::Warning;

        $this->notifications->sendToSupplierPermission(
            $supplier,
            Permission::SupplierManageProfile,
            "supplier-doc:{$document->id}:verified-".($verified ? '1' : '0').":".now()->timestamp,
            $title,
            $message,
            $priority,
            NotificationDestination::SupplierCompliance,
            ['supplier' => $supplier->id],
            except: $actor,
        );
    }

    public function supplierComplianceUploaded(Supplier $supplier, SupplierDocument $document, User $actor): void
    {
        $this->notifications->sendToPermission(
            Permission::ManageSuppliers,
            "supplier-doc:{$document->id}:uploaded:".now()->toDateString(),
            'Compliance document uploaded',
            "{$supplier->name} uploaded compliance document: {$document->document_type}.",
            NotificationPriority::Info,
            NotificationDestination::SupplierManagement,
            ['supplier' => $supplier->id],
            except: $actor,
        );
    }

    public function supplierInvitationAccepted(SupplierInvitation $invitation, User $user): void
    {
        $supplierName = $invitation->supplier?->name ?? 'Supplier';

        $this->notifications->sendToPermission(
            Permission::ManageSuppliers,
            "invitation:{$invitation->id}:accepted",
            'Supplier invitation accepted',
            "{$user->name} from {$supplierName} accepted their invitation and activated their account.",
            NotificationPriority::Info,
            NotificationDestination::SupplierManagement,
            ['supplier' => $invitation->supplier_id],
        );
    }

    public function rfqPublished(SourcingRfq $rfq, ?iterable $suppliers = null): void
    {
        $suppliers ??= $rfq->invitations->map->supplier->filter();

        foreach ($suppliers as $supplier) {
            if (! $supplier instanceof Supplier) {
                continue;
            }

            $invitation = $rfq->invitations->firstWhere('supplier_id', $supplier->id);

            $this->notifications->sendToSupplierPermission(
                $supplier,
                Permission::SupplierSubmitBids,
                "rfq:{$rfq->id}:invitation:{$supplier->id}",
                'New bidding opportunity',
                "You are invited to bid on RFQ {$rfq->rfq_number} ({$rfq->title}). Deadline: {$rfq->submission_deadline?->format('M d, Y')}.",
                NotificationPriority::Info,
                NotificationDestination::SupplierRfqs,
                [
                    'supplier' => $supplier->id,
                    'invitation' => $invitation?->id,
                ],
            );
        }
    }

    public function rfqBidSubmitted(SourcingRfq $rfq, SupplierQuote $quote, Supplier $supplier): void
    {
        $this->notifications->sendToPermission(
            Permission::EvaluateBids,
            "rfq:{$rfq->id}:quote:{$quote->id}:submitted",
            'Supplier quotation submitted',
            "{$supplier->name} submitted quote {$quote->quote_number} for RFQ {$rfq->rfq_number}.",
            NotificationPriority::Info,
            NotificationDestination::Procurement,
            [
                'tab' => 'sourcing',
                'approval_search' => $rfq->rfq_number,
            ],
        );
    }

    public function rfqAwardDecided(SourcingRfq $rfq, SupplierQuote $awardedQuote, User $actor): void
    {
        $awardedSupplier = $awardedQuote->supplier;
        if ($awardedSupplier) {
            $this->notifications->sendToSupplierPermission(
                $awardedSupplier,
                Permission::SupplierSubmitBids,
                "rfq:{$rfq->id}:award:{$awardedSupplier->id}",
                'RFQ award decision',
                "Congratulations! Quote {$awardedQuote->quote_number} for RFQ {$rfq->rfq_number} ({$rfq->title}) has been awarded.",
                NotificationPriority::Info,
                NotificationDestination::SupplierRfqs,
                ['supplier' => $awardedSupplier->id],
            );
        }

        // Notify other invited or bidding suppliers that bidding concluded
        $otherQuotes = $rfq->quotes()->where('id', '!=', $awardedQuote->id)->with('supplier')->get();
        foreach ($otherQuotes as $quote) {
            $otherSupplier = $quote->supplier;
            if (! $otherSupplier || $otherSupplier->id === $awardedSupplier?->id) {
                continue;
            }

            $this->notifications->sendToSupplierPermission(
                $otherSupplier,
                Permission::SupplierSubmitBids,
                "rfq:{$rfq->id}:concluded:{$otherSupplier->id}",
                'Bidding concluded',
                "Bidding has concluded for RFQ {$rfq->rfq_number} ({$rfq->title}). Thank you for participating.",
                NotificationPriority::Info,
                NotificationDestination::SupplierRfqs,
                ['supplier' => $otherSupplier->id],
            );
        }
    }

    public function purchaseOrderIssued(PurchaseOrder $po, bool $isRevision = false): void
    {
        $supplier = $po->supplier;
        if (! $supplier) {
            return;
        }

        $title = $isRevision ? 'Purchase order revision issued' : 'Purchase order issued';
        $message = "Purchase Order {$po->po_number} is ready for supplier acknowledgement.";

        $this->notifications->sendToSupplierPermission(
            $supplier,
            Permission::SupplierFulfillOrders,
            "po:{$po->id}:issued-v{$po->revision_number}",
            $title,
            $message,
            NotificationPriority::Info,
            NotificationDestination::SupplierOrder,
            [
                'purchase_order' => $po->id,
                'supplier' => $supplier->id,
            ],
        );
    }

    public function purchaseOrderAcknowledged(PurchaseOrder $po, string $action, ?string $reason = null): void
    {
        $isAccepted = $action === 'accepted';
        $supplierName = $po->supplier?->name ?? 'Supplier';
        $title = $isAccepted ? 'Purchase order accepted by supplier' : 'Purchase order rejected by supplier';
        $message = $isAccepted
            ? "{$supplierName} accepted Purchase Order {$po->po_number}."
            : "{$supplierName} rejected Purchase Order {$po->po_number}. Reason: {$reason}";
        $priority = $isAccepted ? NotificationPriority::Info : NotificationPriority::Warning;

        if ($po->createdBy) {
            $this->notifications->sendToUser(
                $po->createdBy,
                "po:{$po->id}:ack-{$action}:".now()->timestamp,
                $title,
                $message,
                $priority,
                NotificationDestination::Procurement,
                ['purchase_order' => $po->id],
            );
        }

        $this->notifications->sendToPermission(
            Permission::IssuePurchaseOrder,
            "po:{$po->id}:ack-{$action}:".now()->timestamp,
            $title,
            $message,
            $priority,
            NotificationDestination::Procurement,
            ['purchase_order' => $po->id],
            except: $po->createdBy,
        );
    }

    public function purchaseOrderShipmentDispatched(PurchaseOrder $po, string $trackingNumber): void
    {
        $supplierName = $po->supplier?->name ?? 'Supplier';

        $this->notifications->sendToPermission(
            Permission::ReceivePurchaseOrder,
            "po:{$po->id}:asn:{$trackingNumber}",
            'Advance shipping notice (ASN) received',
            "{$supplierName} dispatched shipment {$trackingNumber} for PO {$po->po_number}.",
            NotificationPriority::Info,
            NotificationDestination::GoodsReceipt,
            ['purchase_order' => $po->id],
        );
    }

    public function discrepancyReported(SupplierDiscrepancy $discrepancy): void
    {
        $supplier = $discrepancy->supplier;
        if (! $supplier) {
            return;
        }

        $po = $discrepancy->receiptLine?->goodsReceiptNote?->purchaseOrder;
        $poId = $po?->id ?? (int) ($discrepancy->receiptLine?->goodsReceiptNote?->purchase_order_id ?? 0);
        $poNumber = $po?->po_number ?? ($poId > 0 ? "PO #{$poId}" : 'delivery');

        $this->notifications->sendToSupplierPermission(
            $supplier,
            Permission::SupplierFulfillOrders,
            "discrepancy:{$discrepancy->id}:reported",
            'Delivery discrepancy reported',
            "A delivery discrepancy was reported on {$poNumber}.",
            NotificationPriority::Warning,
            NotificationDestination::SupplierOrder,
            [
                'purchase_order' => $poId,
                'supplier' => $supplier->id,
            ],
        );
    }

    public function discrepancyResponded(SupplierDiscrepancy $discrepancy, ?User $actor = null): void
    {
        $supplierName = $discrepancy->supplier?->name ?? 'Supplier';
        $po = $discrepancy->receiptLine?->goodsReceiptNote?->purchaseOrder;
        $poId = $po?->id ?? (int) ($discrepancy->receiptLine?->goodsReceiptNote?->purchase_order_id ?? 0);
        $poNumber = $po?->po_number ?? ($poId > 0 ? "PO #{$poId}" : 'delivery');

        $this->notifications->sendToPermission(
            Permission::ReceivePurchaseOrder,
            "discrepancy:{$discrepancy->id}:responded:".now()->timestamp,
            'Supplier responded to discrepancy',
            "{$supplierName} responded to delivery discrepancy on {$poNumber}.",
            NotificationPriority::Info,
            NotificationDestination::GoodsReceipt,
            [
                'purchase_order' => $poId,
                'grn' => $discrepancy->receiptLine?->goods_receipt_note_id,
            ],
            except: $actor,
        );
    }

    public function invoiceSubmitted(PurchaseOrder $po, string $invoiceNumber): void
    {
        $supplierName = $po->supplier?->name ?? 'Supplier';

        $this->notifications->sendToPermission(
            Permission::ViewProcurement,
            "po:{$po->id}:invoice:{$invoiceNumber}",
            'Supplier invoice submitted',
            "{$supplierName} submitted invoice {$invoiceNumber} for PO {$po->po_number}.",
            NotificationPriority::Info,
            NotificationDestination::Procurement,
            ['purchase_order' => $po->id],
        );
    }

    public function catalogProductSubmitted(Supplier $supplier, SupplierProduct $product): void
    {
        $this->notifications->sendToPermission(
            Permission::ManageSuppliers,
            "product:{$product->id}:submitted:".now()->toDateString(),
            'Supplier catalog product updated',
            "{$supplier->name} submitted catalog product '{$product->product_name}' for approval.",
            NotificationPriority::Info,
            NotificationDestination::SupplierManagement,
            ['supplier' => $supplier->id],
        );
    }

    public function logisticsDocumentUploaded(LogisticsDocument $document, User $uploader): void
    {
        $this->notifications->sendToPermission(
            Permission::VerifyLogisticsDocuments,
            "logistics-doc:{$document->id}:uploaded",
            'Logistics document awaiting verification',
            "Document '{$document->title}' ({$document->tracking_number}) was uploaded and is awaiting verification.",
            NotificationPriority::Info,
            NotificationDestination::LogisticsDocuments,
            except: $uploader,
        );
    }

    public function logisticsDocumentVerified(
        LogisticsDocument $document,
        User $verifier,
        string $decision,
        ?string $notes = null
    ): void {
        $uploader = $document->uploadedBy ?? $document->uploader;
        if (! $uploader) {
            return;
        }

        $isVerified = $decision === 'verified';
        $title = $isVerified ? 'Logistics document verified' : 'Logistics document rejected';
        $message = $isVerified
            ? "Your document '{$document->title}' ({$document->tracking_number}) has been verified."
            : "Your document '{$document->title}' ({$document->tracking_number}) was rejected. Notes: {$notes}";
        $priority = $isVerified ? NotificationPriority::Info : NotificationPriority::Warning;

        $this->notifications->sendToUser(
            $uploader,
            "logistics-doc:{$document->id}:decided-{$decision}",
            $title,
            $message,
            $priority,
            NotificationDestination::LogisticsDocuments,
        );
    }

    public function materialRequisitionDecided(
        MaterialRequisition $requisition,
        string $decision,
        User $actor,
        ?string $reason = null
    ): void {
        $requester = $requisition->requestingUser;
        if (! $requester) {
            return;
        }

        $title = match ($decision) {
            'approved' => 'Requisition approved',
            'rejected' => 'Requisition rejected',
            'issued' => 'Requisition items issued',
            default => 'Requisition update',
        };

        $message = match ($decision) {
            'approved' => "Store Requisition {$requisition->requisition_number} has been approved and stock is reserved.",
            'rejected' => "Store Requisition {$requisition->requisition_number} was rejected by {$actor->name}. Reason: {$reason}",
            'issued' => "Items for Store Requisition {$requisition->requisition_number} have been issued and are ready for pickup.",
            default => "Store Requisition {$requisition->requisition_number} updated to {$decision}.",
        };

        $priority = $decision === 'rejected' ? NotificationPriority::Warning : NotificationPriority::Info;

        $this->notifications->sendToUser(
            $requester,
            "material-requisition:{$requisition->id}:{$decision}",
            $title,
            $message,
            $priority,
            NotificationDestination::MaterialRequisition,
            ['requisition' => $requisition->id],
        );
    }

    public function approvalChainDecided(
        ApprovalChain $chain,
        string $decision,
        User $actor,
        ?string $reason = null
    ): void {
        $isApproved = $decision === 'approved';
        $title = sprintf('%s approval %s', $chain->chain_type->label(), $isApproved ? 'completed' : 'rejected');
        $message = $isApproved
            ? sprintf('%s #%d has been fully approved.', $chain->chain_type->label(), $chain->target_id)
            : sprintf('%s #%d was rejected by %s. Reason: %s', $chain->chain_type->label(), $chain->target_id, $actor->name, $reason);
        $priority = $isApproved ? NotificationPriority::Info : NotificationPriority::Warning;

        $recipient = null;
        if ($chain->chain_type === ApprovalChainType::PurchaseRequest) {
            $pr = PurchaseRequest::find($chain->target_id);
            $recipient = $pr?->requester;
        } elseif ($chain->chain_type === ApprovalChainType::PurchaseOrder) {
            $po = PurchaseOrder::find($chain->target_id);
            $recipient = $po?->createdBy ?? $po?->purchaseRequest?->requester;
        }

        $routeParameters = match ($chain->chain_type) {
            ApprovalChainType::PurchaseOrder => ['purchase_order' => $chain->target_id],
            default => [
                'tab' => 'doa_approvals',
                'approval_search' => (string) $chain->id,
            ],
        };

        if ($recipient) {
            $this->notifications->sendToUser(
                $recipient,
                "approval-chain:{$chain->id}:{$decision}",
                $title,
                $message,
                $priority,
                NotificationDestination::Procurement,
                $routeParameters,
            );
        }
    }
}
