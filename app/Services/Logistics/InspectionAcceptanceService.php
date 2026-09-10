<?php

namespace App\Services\Logistics;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteLine;
use App\Models\InspectionAcceptanceReport;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryAutomationService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InspectionAcceptanceService
{
    public function __construct(
        private readonly InventoryAutomationService $automationService,
        private readonly AuditLogger $auditLogger,
        private readonly ChainOfCustodyService $custodyService,
    ) {}

    /**
     * Initiate an Inspection and Acceptance Report (IAR - COA GAM Appendix 50) from a Goods Receipt Note / Delivery Receipt.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromReceipt(GoodsReceiptNote $grn, array $data, User $actor): InspectionAcceptanceReport
    {
        return DB::transaction(function () use ($grn, $data, $actor): InspectionAcceptanceReport {
            $lockedGrn = GoodsReceiptNote::lockForUpdate()->with('purchaseOrder')->findOrFail($grn->id);

            if ($lockedGrn->inspectionAcceptanceReport()->exists()) {
                throw new DomainException("An Inspection and Acceptance Report already exists for Delivery Receipt / GRN #{$lockedGrn->grn_number}.");
            }

            $po = $lockedGrn->purchaseOrder;
            $iarNumber = 'IAR-'.now()->format('Y-m').'-'.Str::upper(Str::random(5));
            $deliveryDate = $lockedGrn->received_at ? Carbon::parse($lockedGrn->received_at)->startOfDay() : now()->startOfDay();

            // Calculate delay vs PO delivery date
            $daysDelayed = 0;
            $liquidatedDamages = 0.00;

            if ($po && $po->delivery_date) {
                $expectedDate = Carbon::parse($po->delivery_date)->startOfDay();
                if ($deliveryDate->greaterThan($expectedDate)) {
                    $daysDelayed = $expectedDate->diffInDays($deliveryDate);
                    $rate = (float) ($po->penalty_clause_rate ?? 0.0010); // 1/10 of 1% default
                    $undeliveredValue = (float) $lockedGrn->lines->sum(fn ($line) => (float) $line->received_quantity * (float) $line->unit_cost);
                    $liquidatedDamages = round($undeliveredValue * $rate * $daysDelayed, 2);
                }
            }

            // COA transmittal deadline: 5 calendar days from delivery
            $coaDeadline = $deliveryDate->copy()->addDays(5);

            $iar = InspectionAcceptanceReport::create([
                'iar_number' => $iarNumber,
                'goods_receipt_note_id' => $lockedGrn->id,
                'purchase_order_id' => $lockedGrn->purchase_order_id,
                'supplier_id' => $lockedGrn->supplier_id,
                'invoice_number' => $lockedGrn->sales_invoice_number ?? ($data['invoice_number'] ?? null),
                'iar_date' => now(),
                'status' => 'pending_inspection',
                'delivery_status' => $data['delivery_status'] ?? ($lockedGrn->delivery_status ?? 'complete'),
                'days_delayed' => $daysDelayed,
                'liquidated_damages_amount' => $liquidatedDamages,
                'coa_transmittal_deadline_at' => $coaDeadline,
                'notes' => $data['notes'] ?? null,
            ]);

            // Log Chain of Custody: Handover from Dock to Technical Inspection
            $this->custodyService->recordTransfer($iar, [
                'event_type' => 'inspection_handover',
                'releasing_user_id' => $actor->id,
                'releasing_party_name' => $actor->name . ' (Receiving Dock Officer)',
                'origin_location' => 'Receiving Quarantine Dock',
                'destination_location' => 'Technical Inspection Holding Area',
                'package_condition' => $lockedGrn->temp_excursion ? 'cold_chain_excursion' : 'good_order',
                'verification_method' => 'credential_auth',
                'notes' => "Initiated IAR {$iarNumber} for Delivery Receipt {$lockedGrn->dr_number}. Handed over for technical evaluation.",
            ], $actor);

            $this->auditLogger->record(
                AuditAction::CreatedInspectionAcceptanceReport,
                actor: $actor,
                target: $iar,
                description: "Created Inspection & Acceptance Report {$iarNumber} for PO #{$po?->po_number}. Liquidated damages: PHP {$liquidatedDamages}.",
                newValues: [
                    'iar_number' => $iarNumber,
                    'grn_id' => $lockedGrn->id,
                    'days_delayed' => $daysDelayed,
                    'liquidated_damages_amount' => $liquidatedDamages,
                ],
            );

            return $iar;
        });
    }

    /**
     * Conduct and sign the Technical Inspection section (COA GAM Appendix 50 Section A).
     *
     * @param  array<string, mixed>  $data
     */
    public function performTechnicalInspection(InspectionAcceptanceReport $iar, array $data, User $inspector): InspectionAcceptanceReport
    {
        return DB::transaction(function () use ($iar, $data, $inspector): InspectionAcceptanceReport {
            $locked = InspectionAcceptanceReport::lockForUpdate()->with(['goodsReceiptNote.lines', 'purchaseOrder'])->findOrFail($iar->id);

            if ($locked->isInspected()) {
                throw new DomainException("IAR #{$locked->iar_number} has already completed technical inspection.");
            }

            // Segregation of Duties: Buyer who created the PO cannot act as technical inspector
            $poBuyerId = $locked->purchaseOrder?->created_by_user_id ?? $locked->purchaseOrder?->purchaseRequest?->requester_id;
            if ($poBuyerId && (int) $poBuyerId === (int) $inspector->id) {
                throw new DomainException('Segregation of duties: The procurement officer who drafted the PO cannot inspect the delivery.');
            }

            $decision = $data['inspection_status'] ?? 'in_order'; // in_order, defective, short_delivery, rejected
            $findings = $data['inspection_findings'] ?? null;
            $grn = $locked->goodsReceiptNote;

            // Cold chain mandatory evaluation
            if ($grn && $grn->is_cold_chain && $grn->temp_excursion) {
                if ($decision === 'in_order') {
                    throw ValidationException::withMessages([
                        'inspection_status' => ['Cannot pass technical inspection: Transit temperature data logger recorded a cold chain excursion breach.'],
                    ]);
                }
            }

            $isPassed = in_array($decision, ['in_order', 'short_delivery'], true);

            $locked->inspection_date = now();
            $locked->inspected_by_id = $inspector->id;
            $locked->inspection_status = $decision;
            $locked->inspection_findings = $findings;
            $locked->status = $isPassed ? 'inspected_passed' : 'inspected_failed';
            $locked->save();

            // Record Chain of Custody Event
            $this->custodyService->recordTransfer($locked, [
                'event_type' => 'inspection_completed',
                'releasing_user_id' => $inspector->id,
                'releasing_party_name' => $inspector->name . ' (Technical Inspection Officer)',
                'origin_location' => 'Technical Inspection Holding Area',
                'destination_location' => $isPassed ? 'Property Custodian Acceptance Office' : 'Quarantine Rejection Depot',
                'package_condition' => $isPassed ? 'good_order' : 'damaged_packaging',
                'verification_method' => 'credential_auth',
                'notes' => "Technical evaluation completed: Result: {$decision}. Findings: {$findings}",
            ], $inspector);

            $this->auditLogger->record(
                $isPassed ? AuditAction::CompletedTechnicalInspection : AuditAction::RejectedIar,
                actor: $inspector,
                target: $locked,
                description: "Signed technical inspection on IAR {$locked->iar_number}. Status: {$decision}.",
                newValues: [
                    'inspection_status' => $decision,
                    'inspection_findings' => $findings,
                    'status' => $locked->status,
                ],
            );

            return $locked;
        });
    }

    /**
     * Conduct and sign the Custodial Acceptance section (COA GAM Appendix 50 Section B).
     * This legally transfers inventory ownership to the hospital and increments stock levels.
     *
     * @param  array<string, mixed>  $data
     */
    public function performCustodialAcceptance(InspectionAcceptanceReport $iar, array $data, User $custodian): InspectionAcceptanceReport
    {
        return DB::transaction(function () use ($iar, $data, $custodian): InspectionAcceptanceReport {
            $locked = InspectionAcceptanceReport::lockForUpdate()->with([
                'goodsReceiptNote.lines.item',
                'purchaseOrder.lines',
            ])->findOrFail($iar->id);

            if ($locked->status !== 'inspected_passed') {
                throw new DomainException("Cannot accept IAR #{$locked->iar_number}: Technical inspection has not been passed (current status: {$locked->status}).");
            }

            // Segregation of Duties: Inspector cannot also sign the acceptance as Property Custodian
            if ($locked->inspected_by_id === $custodian->id) {
                throw new DomainException('Segregation of duties: The technical inspection officer cannot accept their own inspection as property custodian.');
            }

            // Segregation of Duties: Buyer cannot accept delivery into inventory
            $poBuyerId = $locked->purchaseOrder?->created_by_user_id ?? $locked->purchaseOrder?->purchaseRequest?->requester_id;
            if ($poBuyerId && (int) $poBuyerId === (int) $custodian->id) {
                throw new DomainException('Segregation of duties: The purchasing officer cannot accept physical inventory deliveries.');
            }

            $grn = $locked->goodsReceiptNote;
            $quarantineLoc = StorageLocation::firstOrCreate(
                ['code' => 'LOC-QUARANTINE'],
                ['name' => 'Receiving Quarantine Holding Area', 'type' => 'zone', 'status' => 'active']
            );

            $stagingLoc = StorageLocation::firstOrCreate(
                ['code' => 'LOC-STAGING'],
                ['name' => 'Central Receiving Staging Area', 'type' => 'zone', 'status' => 'active']
            );

            // Increment central inventory and post stock movements
            foreach ($grn->lines as $line) {
                $qty = (int) $line->received_quantity;
                if ($qty <= 0) {
                    continue;
                }

                // 1. Decrement quarantined stock
                $this->automationService->adjustQuarantinedStock($line->item_id, $quarantineLoc->id, $line->item_batch_id, -$qty);

                // 2. Increment active receiving staging
                $this->automationService->adjustStockLevel($line->item_id, $stagingLoc->id, $line->item_batch_id, $qty);

                // 3. Mark batch active if present
                if ($line->item_batch_id) {
                    ItemBatch::where('id', $line->item_batch_id)->update(['status' => 'active']);
                }

                // 4. Update PO line received quantity
                if ($line->po_line_id) {
                    $poLine = $line->purchaseOrderLine;
                    if ($poLine) {
                        $poLine->received_quantity = (int) $poLine->received_quantity + $qty;
                        $poLine->save();
                    }
                }

                // 5. Post immutable StockMovement
                StockMovement::create([
                    'item_id' => $line->item_id,
                    'item_batch_id' => $line->item_batch_id,
                    'movement_type' => MovementType::StockIn,
                    'quantity' => $qty,
                    'unit_cost' => $line->unit_cost ?? $line->item->unit_cost,
                    'from_location_id' => $quarantineLoc->id,
                    'to_location_id' => $stagingLoc->id,
                    'reference_type' => InspectionAcceptanceReport::class,
                    'reference_id' => $locked->id,
                    'remarks' => "IAR Acceptance {$locked->iar_number} signed by Property Custodian {$custodian->name}",
                    'moved_at' => now(),
                    'user_id' => $custodian->id,
                ]);

                $line->accepted_quantity = $qty;
                $line->status = 'accepted';
                $line->save();
            }

            // Update PO status if fully fulfilled
            $po = $locked->purchaseOrder;
            if ($po) {
                if ($po->isFullyReceived()) {
                    $po->status = PurchaseOrderStatus::Fulfilled->value;
                    $po->received_at = now();
                    $po->save();
                } else {
                    $po->status = 'partially_received';
                    $po->save();
                }
            }

            $locked->acceptance_date = now();
            $locked->accepted_by_id = $custodian->id;
            $locked->status = 'accepted';
            $locked->save();

            // Mark GRN posted
            $grn->receipt_status = 'posted';
            $grn->save();

            // Record Chain of Custody
            $this->custodyService->recordTransfer($locked, [
                'event_type' => 'acceptance_custody',
                'releasing_user_id' => $locked->inspected_by_id,
                'releasing_party_name' => $locked->inspectedBy?->name ?? 'Inspection Officer',
                'receiving_user_id' => $custodian->id,
                'receiving_party_name' => $custodian->name . ' (Property Custodian)',
                'origin_location' => 'Technical Inspection Holding Area',
                'destination_location' => 'Central Warehouse Inventory Master',
                'package_condition' => 'good_order',
                'verification_method' => 'credential_auth',
                'notes' => "Formal custodial acceptance executed. Stock quantities posted to general inventory ledger. Liquidated damages assessed: PHP {$locked->liquidated_damages_amount}.",
            ], $custodian);

            $this->auditLogger->record(
                AuditAction::ApprovedIarAcceptance,
                actor: $custodian,
                target: $locked,
                description: "Signed custodial acceptance on IAR {$locked->iar_number}. Inventory quantities posted to ledger.",
                newValues: [
                    'status' => 'accepted',
                    'acceptance_date' => $locked->acceptance_date,
                    'liquidated_damages_amount' => $locked->liquidated_damages_amount,
                ],
            );

            return $locked;
        });
    }

    /**
     * Transmit completed IAR and supporting documents to resident COA Auditor within 5 calendar days.
     *
     * @param  array<string, mixed>  $data
     */
    public function transmitToCoa(InspectionAcceptanceReport $iar, array $data, User $officer): InspectionAcceptanceReport
    {
        return DB::transaction(function () use ($iar, $data, $officer): InspectionAcceptanceReport {
            $locked = InspectionAcceptanceReport::lockForUpdate()->findOrFail($iar->id);

            if ($locked->status !== 'accepted') {
                throw new DomainException("Only fully accepted IAR records can be transmitted to the resident COA Auditor.");
            }

            $locked->coa_transmitted_at = now();
            $locked->coa_received_by = $data['coa_received_by'] ?? 'COA Resident Audit Staff';
            $locked->save();

            $this->custodyService->recordTransfer($locked, [
                'event_type' => 'coa_transmittal',
                'releasing_user_id' => $officer->id,
                'releasing_party_name' => $officer->name . ' (Supply Records Officer)',
                'receiving_party_name' => $locked->coa_received_by,
                'origin_location' => 'HIMS Logistics & Document Archive',
                'destination_location' => 'Commission on Audit (COA) Resident Office',
                'package_condition' => 'good_order',
                'verification_method' => 'conforme_signed',
                'notes' => "Transmitted PO, DR, IAR, and Invoice documentation to COA under the 5-day mandatory transmittal rule.",
            ], $officer);

            $this->auditLogger->record(
                AuditAction::TransmittedIarToCoa,
                actor: $officer,
                target: $locked,
                description: "Transmitted IAR {$locked->iar_number} to COA Auditor ({$locked->coa_received_by}).",
                newValues: [
                    'coa_transmitted_at' => $locked->coa_transmitted_at,
                    'coa_received_by' => $locked->coa_received_by,
                ],
            );

            return $locked;
        });
    }
}
