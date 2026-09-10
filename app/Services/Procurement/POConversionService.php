<?php

namespace App\Services\Procurement;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderRevision;
use App\Models\PurchaseRequest;
use App\Models\SourcingRfq;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class POConversionService
{
    public function __construct(
        private readonly BudgetEncumbranceService $budgetService
    ) {}

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
            $poNumber = 'PO-'.now()->format('Ymd').'-'.str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_id' => $awardedQuote->supplier_id,
                'purchase_request_id' => $rfq->purchase_request_id,
                'sourcing_rfq_id' => $rfq->id,
                'cost_center_id' => $rfq->purchaseRequest?->cost_center_id,
                'item_id' => $rfq->lines()->first()?->item_id ?? 1,
                'quantity' => (int) ($rfq->lines()->sum('target_quantity') ?: 1),
                'unit_cost' => (float) ($awardedQuote->lines()->first()?->offered_unit_price ?? $totalAmount),
                'total_amount' => $totalAmount,
                'currency' => $awardedQuote->currency ?? 'PHP',
                'exchange_rate' => $awardedQuote->exchange_rate ?? 1.0,
                'total_encumbered_amount' => $totalAmount,
                'payment_terms' => $awardedQuote->payment_terms ?? 'Net 30',
                'incoterms' => $awardedQuote->incoterms ?? 'DDP',
                'version' => 'PO-REV1',
                'revision_number' => 1,
                'status' => PurchaseOrderStatus::Approved->value,
                'requested_at' => now(),
                'dispatched_at' => now(),
            ]);

            // Create PO Line Items from quote lines or RFQ lines
            $lineNumber = 1;
            if ($awardedQuote->lines()->exists()) {
                foreach ($awardedQuote->lines as $qLine) {
                    $po->lines()->create([
                        'pr_line_id' => $qLine->rfqLineItem?->pr_line_id,
                        'quote_line_id' => $qLine->id,
                        'item_id' => $qLine->rfqLineItem?->item_id ?? $po->item_id,
                        'line_number' => $lineNumber++,
                        'ordered_quantity' => (int) $qLine->offered_quantity,
                        'received_quantity' => 0,
                        'invoiced_quantity' => 0,
                        'unit_price' => (float) $qLine->offered_unit_price,
                        'total_line_amount' => (float) $qLine->landed_cost,
                        'line_status' => 'open',
                    ]);
                }
            } else {
                // Fallback single line
                $po->lines()->create([
                    'item_id' => $po->item_id,
                    'line_number' => 1,
                    'ordered_quantity' => $po->quantity,
                    'received_quantity' => 0,
                    'invoiced_quantity' => 0,
                    'unit_price' => $po->unit_cost,
                    'total_line_amount' => $po->total_amount,
                    'line_status' => 'open',
                ]);
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
    }

    /**
     * Direct conversion for catalog-contracted items (skips RFQ canvassing).
     */
    public function convertCatalogPRToPO(PurchaseRequest $pr, User $buyer): PurchaseOrder
    {
        return DB::transaction(function () use ($pr, $buyer) {
            $firstLine = $pr->lines()->first();
            $supplier = $firstLine?->contract?->supplier
                ?? Supplier::procurementEligible()->first();

            if (! $supplier) {
                throw new DomainException('No eligible supplier found for catalog purchase order conversion.');
            }

            $poNumber = 'PO-'.now()->format('Ymd').'-'.str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_id' => $supplier->id,
                'purchase_request_id' => $pr->id,
                'cost_center_id' => $pr->cost_center_id,
                'item_id' => $firstLine?->item_id ?? 1,
                'quantity' => (int) ($pr->lines()->sum('quantity') ?: 1),
                'unit_cost' => (float) ($firstLine?->estimated_unit_price ?: 1),
                'total_amount' => (float) $pr->total_estimated_amount,
                'currency' => $pr->currency ?? 'PHP',
                'total_encumbered_amount' => (float) $pr->total_estimated_amount,
                'version' => 'PO-REV1',
                'revision_number' => 1,
                'status' => PurchaseOrderStatus::Approved->value,
                'requested_at' => now(),
                'dispatched_at' => now(),
            ]);

            $lineNumber = 1;
            foreach ($pr->lines as $prLine) {
                $po->lines()->create([
                    'pr_line_id' => $prLine->id,
                    'item_id' => $prLine->item_id,
                    'line_number' => $lineNumber++,
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
                        <Name xml:lang="en">Hospital Central Receiving Dock</Name>
                        <PostalAddress>
                            <DeliverTo>Central Medical Warehouse</DeliverTo>
                            <Street>Hospital Boulevard, Medical District</Street>
                            <City>Manila</City>
                            <Country isoCountryCode="PH">Philippines</Country>
                        </PostalAddress>
                    </Address>
                </ShipTo>
                <PaymentTerm payInNumberOfDays="30"/>
            </OrderRequestHeader>
            {$linesXml}
        </OrderRequest>
    </Request>
</cXML>
XML;
    }
}
