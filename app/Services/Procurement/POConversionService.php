<?php

namespace App\Services\Procurement;

use App\Enums\ApprovalStepStatus;
use App\Enums\AuditAction;
use App\Enums\PurchaseOrderStatus;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderRevision;
use App\Models\PurchaseRequest;
use App\Models\SourcingRfq;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierQuote;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class POConversionService
{
    public function __construct(
        private readonly BudgetEncumbranceService $budgetService,
        private readonly ProcurementAuditService $auditService,
        private readonly ApprovalRoutingEngine $approvalEngine,
    ) {}

    /**
     * Issue a direct catalog purchase order using server-owned commercial terms.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDirectPurchaseOrder(array $data, User $buyer): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $buyer): PurchaseOrder {
            $supplier = Supplier::procurementEligible()->find($data['supplier_id']);
            $item = InventoryItem::query()
                ->whereNotIn('status', ['inactive', 'archived'])
                ->find($data['item_id']);

            if (! $supplier || ! $item) {
                throw new DomainException('The selected item or supplier is no longer eligible for procurement.');
            }

            $terms = $this->catalogTerms($supplier, $item);
            $quantity = (int) $data['quantity'];

            if ($quantity < $terms['minimum_order_quantity']) {
                throw new DomainException("The minimum order for this supplier and item is {$terms['minimum_order_quantity']} units.");
            }

            $unitCost = $terms['unit_cost'];
            $totalAmount = round($quantity * $unitCost, 2);
            $purchaseUnit = $data['purchase_unit'] ?? $terms['unit'] ?? $item->unit;
            if (blank($purchaseUnit)) {
                throw new DomainException("No purchase unit is recorded for {$item->name}.");
            }
            $conversionFactor = (float) ($data['conversion_factor'] ?? $item->conversionFactorFor($purchaseUnit));
            if ($conversionFactor <= 0) {
                $conversionFactor = 1.0;
            }

            $po = PurchaseOrder::create([
                'po_number' => $this->nextPurchaseOrderNumber(),
                'supplier_id' => $supplier->id,
                'cost_center_id' => $data['cost_center_id'],
                'item_id' => $item->id,
                'quantity' => $quantity,
                'purchase_unit' => $purchaseUnit,
                'conversion_factor' => $conversionFactor,
                'unit_cost' => $unitCost,
                'total_amount' => $totalAmount,
                'currency' => $terms['currency'],
                'exchange_rate' => 1.0,
                'total_encumbered_amount' => $totalAmount,
                'payment_terms' => $data['payment_terms'] ?? $supplier->payment_terms,
                'incoterms' => $data['incoterms'] ?? null,
                'version' => 'PO-REV1',
                'revision_number' => 1,
                'status' => PurchaseOrderStatus::PendingApproval->value,
                'notes' => $data['notes'] ?? null,
                'delivery_date' => $data['delivery_date']
                    ?? ($terms['lead_time_days'] !== null
                        ? now()->addDays($terms['lead_time_days'])->toDateString()
                        : null),
                'created_by_user_id' => $buyer->id,
                'requested_at' => now(),
            ]);

            $po->lines()->create([
                'item_id' => $item->id,
                'line_number' => 1,
                'purchase_unit' => $purchaseUnit,
                'conversion_factor' => $conversionFactor,
                'ordered_quantity' => $quantity,
                'received_quantity' => 0,
                'invoiced_quantity' => 0,
                'unit_price' => $unitCost,
                'total_line_amount' => $totalAmount,
                'line_status' => 'open',
            ]);

            $po->load(['supplier', 'lines.item']);
            $po->cxml_payload = $this->generateCxmlPayload($po);
            $po->save();

            $this->budgetService->convertSoftToHardEncumbrance($po);
            $this->approvalEngine->routePurchaseOrder($po, $buyer);
            $this->auditService->record(
                $buyer,
                'PurchaseOrder',
                $po->id,
                'issued_purchase_order',
                null,
                [
                    'po_number' => $po->po_number,
                    'supplier_id' => $supplier->id,
                    'item_id' => $item->id,
                    'quantity' => $quantity,
                    'amount' => $totalAmount,
                    'price_source' => $terms['price_source'],
                    'status' => $po->status,
                ],
            );

            return $po->fresh(['supplier', 'item', 'lines.item']);
        });
    }

    public function cancelPendingPurchaseOrder(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor): PurchaseOrder {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);

            if ($po->created_by_user_id !== $actor->id) {
                throw new AuthorizationException('You may only cancel purchase orders that you created.');
            }

            if (! in_array($po->statusEnum(), [
                PurchaseOrderStatus::Draft,
                PurchaseOrderStatus::Submitted,
                PurchaseOrderStatus::PendingApproval,
            ], true)) {
                throw new DomainException("Purchase Order {$po->po_number} can no longer be cancelled because approval or fulfillment has started.");
            }

            $oldStatus = $po->status;
            $po->status = PurchaseOrderStatus::Cancelled->value;
            $po->save();

            $chain = $po->approvalChain;
            if ($chain?->status === 'pending') {
                $chain->steps()
                    ->where('status', ApprovalStepStatus::Pending->value)
                    ->update([
                        'status' => ApprovalStepStatus::Skipped->value,
                        'decision_notes' => 'Purchase order cancelled by its creator.',
                        'decided_at' => now(),
                    ]);
                $chain->update(['status' => 'cancelled']);
            }

            $this->budgetService->releaseHardEncumbrance($po);
            $this->auditService->record(
                $actor,
                'PurchaseOrder',
                $po->id,
                AuditAction::CancelledPurchaseOrder->value,
                ['status' => $oldStatus],
                ['status' => PurchaseOrderStatus::Cancelled->value],
            );

            return $po->fresh();
        });
    }

    public function approvePurchaseOrder(
        PurchaseOrder $purchaseOrder,
        User $approver,
        ?string $revisedDeliveryDate = null,
        ?string $deliveryDateChangeReason = null,
    ): PurchaseOrder {
        return DB::transaction(function () use ($purchaseOrder, $approver, $revisedDeliveryDate, $deliveryDateChangeReason): PurchaseOrder {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);

            if ($po->created_by_user_id === $approver->id && ! $approver->isSuperAdministrator()) {
                throw new DomainException("Segregation of Duties Violation: Issuer cannot approve their own Purchase Order #{$po->po_number}.");
            }

            if (! in_array($po->statusEnum(), [
                PurchaseOrderStatus::Draft,
                PurchaseOrderStatus::Submitted,
                PurchaseOrderStatus::PendingApproval,
            ], true)) {
                throw new DomainException("Purchase Order {$po->po_number} is no longer awaiting approval.");
            }

            $decisionNotes = $approver->isSuperAdministrator()
                ? 'Executive approval authorized by Super Administrator.'
                : 'Approved by Inventory Manager.';

            if ($po->delivery_date?->lt(today())) {
                if (blank($revisedDeliveryDate) || blank($deliveryDateChangeReason)) {
                    throw new DomainException('A new future delivery date and justification are required before this purchase order can be approved.');
                }

                $newDeliveryDate = Carbon::parse($revisedDeliveryDate)->startOfDay();
                if ($newDeliveryDate->lte(today())) {
                    throw new DomainException('The revised delivery date must be after today.');
                }

                $oldDeliveryDate = $po->delivery_date->toDateString();
                $po->delivery_date = $newDeliveryDate;
                $po->save();

                $this->auditService->record(
                    $approver,
                    'PurchaseOrder',
                    $po->id,
                    AuditAction::AmendedPurchaseOrder->value,
                    ['delivery_date' => $oldDeliveryDate],
                    [
                        'delivery_date' => $newDeliveryDate->toDateString(),
                        'reason' => $deliveryDateChangeReason,
                    ],
                );

                $decisionNotes .= " Delivery date revised from {$oldDeliveryDate} to {$newDeliveryDate->toDateString()}: {$deliveryDateChangeReason}";
            }

            $chain = $po->approvalChain;
            if ($chain && $chain->status === 'pending') {
                if ($approver->isSuperAdministrator()) {
                    while ($chain->currentPendingStep()) {
                        $this->approvalEngine->approveStep($chain, $approver, $decisionNotes);
                        $chain->refresh();
                    }
                } else {
                    $this->approvalEngine->approveStep($chain, $approver, $decisionNotes);
                }
            } else {
                $oldStatus = $po->status;
                $po->status = PurchaseOrderStatus::Approved->value;
                $po->save();

                $this->auditService->record(
                    $approver,
                    'PurchaseOrder',
                    $po->id,
                    AuditAction::ApprovedPurchaseOrder->value,
                    ['status' => $oldStatus],
                    ['status' => PurchaseOrderStatus::Approved->value],
                );
            }

            return $po->fresh();
        });
    }

    /**
     * Resolve supplier-specific terms where available, otherwise use the item
     * catalog cost maintained by HIMS. Browser-submitted pricing is never used.
     *
     * @return array{unit_cost: float, unit: ?string, currency: string, minimum_order_quantity: int, lead_time_days: ?int, price_source: string}
     */
    public function catalogTerms(
        Supplier $supplier,
        InventoryItem $item,
        ?SupplierProduct $supplierProduct = null,
    ): array {
        $supplierProduct ??= SupplierProduct::query()
            ->with('prices.contract')
            ->where('supplier_id', $supplier->id)
            ->where('item_id', $item->id)
            ->where('is_active', true)
            ->first();

        if ($supplierProduct && ! $supplierProduct->relationLoaded('prices')) {
            $supplierProduct->load('prices.contract');
        }

        $currentPrice = $supplierProduct?->prices
            ->filter(fn ($price): bool => $price->isCurrent())
            ->sortByDesc('effective_from')
            ->first();
        $unitCost = (float) ($currentPrice?->unit_price ?? $item->unit_cost ?? 0);

        if ($unitCost <= 0) {
            throw new DomainException("No active supplier or catalog price is available for {$item->name}.");
        }

        return [
            'unit_cost' => $unitCost,
            'unit' => $supplierProduct?->unit ?? $item->unit,
            'currency' => (string) ($currentPrice?->currency ?? config('inventory.default_currency')),
            'minimum_order_quantity' => max(
                1,
                (int) ($supplierProduct?->minimum_order_quantity ?? 1),
                (int) ($currentPrice?->minimum_order_quantity ?? 1),
            ),
            'lead_time_days' => ($supplierProduct?->lead_time_days
                ?? $supplier->standard_lead_time_days
                ?? $item->lead_time_days) !== null
                    ? max(0, (int) ($supplierProduct?->lead_time_days
                        ?? $supplier->standard_lead_time_days
                        ?? $item->lead_time_days))
                    : null,
            'price_source' => $currentPrice ? 'supplier_catalog' : 'item_catalog',
        ];
    }

    /**
     * Convert an approved Sourcing RFQ award into an encumbered Purchase Order.
     */
    public function convertAwardToPO(
        SourcingRfq $rfq,
        SupplierQuote $awardedQuote,
        User $buyer
    ): PurchaseOrder {
        if ($rfq->status->value !== 'under_evaluation' && $rfq->status->value !== 'awarded') {
            throw new DomainException("Cannot generate PO: Sourcing RFQ #{$rfq->rfq_number} is in '{$rfq->status->value}' status.");
        }

        if (! $awardedQuote->supplier->isProcurementEligible()) {
            throw new DomainException("Compliance Block: Supplier '{$awardedQuote->supplier->name}' is currently not eligible for procurement awards.");
        }

        return DB::transaction(function () use ($rfq, $awardedQuote, $buyer) {
            $totalAmount = $awardedQuote->totalLandedCost();
            $rfqLines = $rfq->lines()->with('item')->get();
            $firstRfqLine = $rfqLines->first();
            $firstQuoteLine = $awardedQuote->lines()->first();

            if (! $firstRfqLine?->item_id || $rfqLines->sum('target_quantity') <= 0 || ! $firstQuoteLine) {
                throw new DomainException('The awarded RFQ must contain recorded item, quantity, and quote-line data before PO conversion.');
            }

            $poNumber = $this->nextPurchaseOrderNumber();

            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_id' => $awardedQuote->supplier_id,
                'purchase_request_id' => $rfq->purchase_request_id,
                'sourcing_rfq_id' => $rfq->id,
                'cost_center_id' => $rfq->purchaseRequest?->cost_center_id,
                'item_id' => $firstRfqLine->item_id,
                'quantity' => (int) $rfqLines->sum('target_quantity'),
                'unit_cost' => (float) $firstQuoteLine->offered_unit_price,
                'total_amount' => $totalAmount,
                'currency' => $awardedQuote->currency ?? config('inventory.default_currency'),
                'exchange_rate' => $awardedQuote->exchange_rate ?? 1.0,
                'total_encumbered_amount' => $totalAmount,
                'payment_terms' => $awardedQuote->payment_terms,
                'incoterms' => $awardedQuote->incoterms,
                'version' => 'PO-REV1',
                'revision_number' => 1,
                'status' => PurchaseOrderStatus::Approved->value,
                'requested_at' => now(),
                'dispatched_at' => now(),
                'created_by_user_id' => $buyer->id,
            ]);

            // Create PO Line Items from quote lines or RFQ lines
            $lineNumber = 1;
            if ($awardedQuote->lines()->exists()) {
                foreach ($awardedQuote->lines as $qLine) {
                    $itemObj = $qLine->rfqLineItem?->item ?? $po->item;
                    $pUnit = $qLine->rfqLineItem?->uom ?: $itemObj?->unit;
                    if (blank($pUnit) || ! $itemObj) {
                        throw new DomainException('Each awarded quote line must reference an item with a recorded unit of measure.');
                    }
                    $cFactor = $itemObj?->conversionFactorFor($pUnit) ?? 1.0;

                    $po->lines()->create([
                        'pr_line_id' => $qLine->rfqLineItem?->pr_line_id,
                        'quote_line_id' => $qLine->id,
                        'item_id' => $itemObj?->id ?? $po->item_id,
                        'line_number' => $lineNumber++,
                        'purchase_unit' => $pUnit,
                        'conversion_factor' => $cFactor,
                        'ordered_quantity' => (int) $qLine->offered_quantity,
                        'received_quantity' => 0,
                        'invoiced_quantity' => 0,
                        'unit_price' => (float) $qLine->offered_unit_price,
                        'total_line_amount' => (float) $qLine->landed_cost,
                        'line_status' => 'open',
                    ]);
                }
            }

            // Generate cXML OrderRequest dispatch payload
            $po->cxml_payload = $this->generateCxmlPayload($po);
            $po->save();

            // Mark quote as awarded and RFQ as awarded
            $awardedQuote->is_awarded = true;
            $awardedQuote->status = 'accepted';
            $awardedQuote->save();

            $rfq->status = 'awarded';
            $rfq->save();

            if ($rfq->purchaseRequest) {
                $rfq->purchaseRequest->status = 'po_converted';
                $rfq->purchaseRequest->save();
            }

            // Convert budget soft commitment into hard encumbrance
            $this->budgetService->convertSoftToHardEncumbrance($po);

            return $po;
        });

        app(\App\Services\HimsNotificationWorkflowService::class)->rfqAwardDecided($rfq, $awardedQuote, $buyer);

        return $po;
    }

    /**
     * Direct conversion for catalog-contracted items (skips RFQ canvassing).
     */
    public function convertCatalogPRToPO(PurchaseRequest $pr, User $buyer): PurchaseOrder
    {
        return DB::transaction(function () use ($pr, $buyer) {
            $firstLine = $pr->lines()->first();
            $supplier = $firstLine?->contract?->supplier;

            if (! $supplier) {
                throw new DomainException('The catalog requisition line must reference an eligible contracted supplier.');
            }

            if (! $firstLine?->item_id || $pr->lines()->sum('quantity') <= 0 || $firstLine->estimated_unit_price === null) {
                throw new DomainException('The requisition must contain recorded item, quantity, and pricing data before PO conversion.');
            }

            $poNumber = $this->nextPurchaseOrderNumber();

            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_id' => $supplier->id,
                'purchase_request_id' => $pr->id,
                'cost_center_id' => $pr->cost_center_id,
                'item_id' => $firstLine->item_id,
                'quantity' => (int) $pr->lines()->sum('quantity'),
                'unit_cost' => (float) $firstLine->estimated_unit_price,
                'total_amount' => (float) $pr->total_estimated_amount,
                'currency' => $pr->currency ?? config('inventory.default_currency'),
                'total_encumbered_amount' => (float) $pr->total_estimated_amount,
                'version' => 'PO-REV1',
                'revision_number' => 1,
                'status' => PurchaseOrderStatus::Approved->value,
                'requested_at' => now(),
                'dispatched_at' => now(),
                'created_by_user_id' => $buyer->id,
            ]);

            $lineNumber = 1;
            foreach ($pr->lines as $prLine) {
                $pUnit = $prLine->uom ?: $prLine->item?->unit;
                if (blank($pUnit)) {
                    throw new DomainException('Each requisition line must have a recorded unit of measure before PO conversion.');
                }
                $cFactor = $prLine->item?->conversionFactorFor($pUnit) ?? 1.0;

                $po->lines()->create([
                    'pr_line_id' => $prLine->id,
                    'item_id' => $prLine->item_id,
                    'line_number' => $lineNumber++,
                    'purchase_unit' => $pUnit,
                    'conversion_factor' => $cFactor,
                    'ordered_quantity' => $prLine->quantity,
                    'received_quantity' => 0,
                    'invoiced_quantity' => 0,
                    'unit_price' => $prLine->estimated_unit_price,
                    'total_line_amount' => $prLine->estimated_total_price,
                    'line_status' => 'open',
                ]);
            }

            $po->cxml_payload = $this->generateCxmlPayload($po);
            $po->save();

            $pr->status = 'po_converted';
            $pr->save();

            $this->budgetService->convertSoftToHardEncumbrance($po);

            return $po;
        });
    }

    /**
     * Submit a Purchase Order Revision / Change Order.
     * If variance exceeds 5%, marks revision as requiring DOA re-approval.
     */
    public function submitPoRevision(
        PurchaseOrder $po,
        array $newLinesData,
        string $justification,
        User $author
    ): PurchaseOrderRevision {
        return DB::transaction(function () use ($po, $newLinesData, $justification, $author) {
            $originalSnapshot = [
                'total_amount' => (float) $po->total_amount,
                'version' => $po->version,
                'lines' => $po->lines()->get()->toArray(),
            ];

            // Calculate proposed total
            $proposedTotal = 0.0;
            foreach ($newLinesData as $lineData) {
                $qty = (int) ($lineData['ordered_quantity'] ?? 1);
                $price = (float) ($lineData['unit_price'] ?? 0);
                $proposedTotal += round($qty * $price, 2);
            }

            $delta = round($proposedTotal - (float) $po->total_amount, 2);
            $variancePct = $po->total_amount > 0 ? abs(round(($delta / $po->total_amount) * 100, 2)) : 0;
            $requiresDoa = $variancePct > 5.0;

            $nextRevNumber = $po->revision_number + 1;
            $changeOrderCode = "CO-{$po->po_number}-REV{$nextRevNumber}";

            $revision = PurchaseOrderRevision::create([
                'purchase_order_id' => $po->id,
                'revision_number' => $nextRevNumber,
                'change_order_code' => $changeOrderCode,
                'justification' => $justification,
                'delta_amount' => $delta,
                'variance_percentage' => $variancePct,
                'original_snapshot' => $originalSnapshot,
                'proposed_snapshot' => $newLinesData,
                'requires_doa_reapproval' => $requiresDoa,
                'status' => $requiresDoa ? 'pending' : 'approved',
                'created_by' => $author->id,
                'approved_by' => $requiresDoa ? null : $author->id,
                'approved_at' => $requiresDoa ? null : now(),
            ]);

            if (! $requiresDoa) {
                // Apply immediately
                $this->applyPoRevision($po, $revision, $author);
            } else {
                $po->status = PurchaseOrderStatus::PendingApproval->value;
                $po->save();
            }

            return $revision;
        });
    }

    /**
     * Apply approved revision to the purchase order.
     */
    public function applyPoRevision(PurchaseOrder $po, PurchaseOrderRevision $revision, User $approver): void
    {
        DB::transaction(function () use ($po, $revision, $approver) {
            $newLines = $revision->proposed_snapshot;
            $newTotal = 0.0;

            $po->lines()->delete();

            $lineNum = 1;
            foreach ($newLines as $data) {
                $qty = (int) ($data['ordered_quantity'] ?? 1);
                $price = (float) ($data['unit_price'] ?? 0);
                $lineTotal = round($qty * $price, 2);
                $newTotal += $lineTotal;

                $po->lines()->create([
                    'item_id' => $data['item_id'] ?? $po->item_id,
                    'line_number' => $lineNum++,
                    'ordered_quantity' => $qty,
                    'received_quantity' => 0,
                    'invoiced_quantity' => 0,
                    'unit_price' => $price,
                    'total_line_amount' => $lineTotal,
                    'line_status' => 'open',
                ]);
            }

            $po->version = "PO-REV{$revision->revision_number}";
            $po->revision_number = $revision->revision_number;
            $po->total_amount = $newTotal;
            $po->total_encumbered_amount = $newTotal;
            $po->status = PurchaseOrderStatus::Approved->value;
            $po->cxml_payload = $this->generateCxmlPayload($po);
            $po->save();

            $revision->status = 'approved';
            $revision->approved_by = $approver->id;
            $revision->approved_at = now();
            $revision->save();
        });
    }

    /**
     * Generate standard cXML OrderRequest document payload.
     */
    public function generateCxmlPayload(PurchaseOrder $po): string
    {
        $timestamp = now()->toIso8601String();
        $payloadId = "HIMS-PO-{$po->id}-{$po->version}@hospital.local";
        $shipToName = htmlspecialchars((string) ($po->entity_name ?: config('privacy.hospital_name')), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $shipToAddress = htmlspecialchars((string) config('privacy.hospital_address'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        preg_match('/\d+/', (string) $po->payment_terms, $paymentTermMatch);
        $paymentTermXml = isset($paymentTermMatch[0])
            ? '<PaymentTerm payInNumberOfDays="'.(int) $paymentTermMatch[0].'"/>'
            : '';

        $linesXml = '';
        foreach ($po->lines as $line) {
            $linesXml .= <<<XML
        <ItemOut quantity="{$line->ordered_quantity}" lineNumber="{$line->line_number}">
            <ItemID>
                <SupplierPartID>{$line->item->sku}</SupplierPartID>
            </ItemID>
            <ItemDetail>
                <UnitPrice>
                    <Money currency="{$po->currency}">{$line->unit_price}</Money>
                </UnitPrice>
                <Description xml:lang="en">{$line->item->name}</Description>
                <UnitOfMeasure>{$line->item->unit}</UnitOfMeasure>
            </ItemDetail>
        </ItemOut>
XML;
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.014/cXML.dtd">
<cXML payloadID="{$payloadId}" timestamp="{$timestamp}">
    <Header>
        <From>
            <Credential domain="NetworkID">
                <Identity>HIMS-HOSPITAL</Identity>
            </Credential>
        </From>
        <To>
            <Credential domain="SupplierID">
                <Identity>{$po->supplier->identity_key}</Identity>
            </Credential>
        </To>
        <Sender>
            <Credential domain="System">
                <Identity>HIMS-PROCUREMENT</Identity>
            </Credential>
            <UserAgent>HIMS S2P Core Engine 1.0</UserAgent>
        </Sender>
    </Header>
    <Request>
        <OrderRequest>
            <OrderRequestHeader orderID="{$po->po_number}" orderDate="{$timestamp}" type="new">
                <Total>
                    <Money currency="{$po->currency}">{$po->total_amount}</Money>
                </Total>
                <ShipTo>
                    <Address>
                        <Name xml:lang="en">{$shipToName}</Name>
                        <PostalAddress>
                            <Street>{$shipToAddress}</Street>
                        </PostalAddress>
                    </Address>
                </ShipTo>
                {$paymentTermXml}
            </OrderRequestHeader>
            {$linesXml}
        </OrderRequest>
    </Request>
</cXML>
XML;
    }

    private function nextPurchaseOrderNumber(): string
    {
        do {
            $number = 'PO-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
        } while (PurchaseOrder::where('po_number', $number)->exists());

        return $number;
    }
}
