<?php

namespace App\Services\Warehouse;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Models\CostCenter;
use App\Models\InventoryItem;
use App\Models\InventorySerial;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\StorageLocation;
use App\Models\SurgicalConsignmentBillOnly;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryAutomationService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConsignmentService
{
    public function __construct(
        private readonly InventoryAutomationService $inventory,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Record usage of a high-value surgical consignment implant in the Operating Room.
     *
     * @param array{
     *     inventory_item_id: int,
     *     serial_number?: ?string,
     *     item_batch_id?: ?int,
     *     storage_location_id: int,
     *     patient_encounter_id: string,
     *     operating_suite: string,
     *     surgeon_name: string,
     *     implanted_quantity?: int,
     *     notes?: ?string
     * } $data
     */
    public function recordImplantUsage(array $data, User $actor): SurgicalConsignmentBillOnly
    {
        return DB::transaction(function () use ($data, $actor): SurgicalConsignmentBillOnly {
            $item = InventoryItem::lockForUpdate()->findOrFail($data['inventory_item_id']);
            $location = StorageLocation::findOrFail($data['storage_location_id']);
            $qty = $data['implanted_quantity'] ?? 1;

            if ($qty <= 0) {
                throw new DomainException('Implanted quantity must be at least 1.');
            }

            $serialModel = null;
            if (! empty($data['serial_number'])) {
                $serialModel = InventorySerial::query()
                    ->where('item_id', $item->id)
                    ->where('serial_number', $data['serial_number'])
                    ->first();

                if ($serialModel) {
                    $serialModel->status = 'implanted';
                    $serialModel->storage_location_id = null;
                    $serialModel->save();
                }
            }

            // Decrement consignment balance at location
            $this->inventory->recordMovement([
                'item_id' => $item->id,
                'item_batch_id' => $data['item_batch_id'] ?? null,
                'movement_type' => MovementType::Issuance,
                'quantity' => $qty,
                'from_location_id' => $location->id,
                'remarks' => "Surgical Consignment Implant for Patient Encounter {$data['patient_encounter_id']} (OR: {$data['operating_suite']})",
            ], $actor->id);

            // Generate automated Bill-Only Purchase Request to Procurement
            $prNumber = 'PR-BILLONLY-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
            $unitCost = (float) ($item->unit_cost ?? 0);
            $totalCost = $unitCost * $qty;

            $costCenter = CostCenter::firstOrCreate(
                ['code' => 'CC-OT-01'],
                [
                    'name' => 'Operating Theater',
                    'department' => 'Surgical Services',
                    'is_active' => true,
                ]
            );

            $pr = PurchaseRequest::create([
                'pr_number' => $prNumber,
                'title' => "Bill-Only Consignment Replenishment: {$item->name}",
                'description' => "Bill-Only consignment replenishment for {$item->name} implanted into Patient {$data['patient_encounter_id']} in {$data['operating_suite']} by Dr. {$data['surgeon_name']}",
                'requester_id' => $actor->id,
                'cost_center_id' => $costCenter->id,
                'procurement_method' => 'direct_contracting',
                'total_estimated_amount' => $totalCost,
                'currency' => 'PHP',
                'priority' => 'routine',
                'status' => \App\Enums\RequisitionStatus::PendingApproval,
            ]);

            PurchaseRequestLine::create([
                'purchase_request_id' => $pr->id,
                'item_id' => $item->id,
                'line_number' => 1,
                'item_description' => "Consignment consumption in {$data['operating_suite']} by Dr. {$data['surgeon_name']}",
                'quantity' => $qty,
                'uom' => $item->unit ?? 'unit',
                'estimated_unit_price' => $unitCost,
                'estimated_total_price' => $totalCost,
            ]);

            $reqNumber = 'CONS-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
            $record = SurgicalConsignmentBillOnly::create([
                'request_number' => $reqNumber,
                'inventory_item_id' => $item->id,
                'inventory_serial_id' => $serialModel?->id,
                'item_batch_id' => $data['item_batch_id'] ?? null,
                'storage_location_id' => $location->id,
                'patient_encounter_id' => $data['patient_encounter_id'],
                'operating_suite' => $data['operating_suite'],
                'surgeon_name' => $data['surgeon_name'],
                'implanted_quantity' => $qty,
                'status' => 'pending_po',
                'purchase_request_id' => $pr->id,
                'recorded_by_id' => $actor->id,
                'implanted_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLogger->record(
                AuditAction::RecordedSurgicalConsignmentUsage,
                actor: $actor,
                target: $record,
                description: "Recorded surgical implant consumption {$reqNumber} for patient {$data['patient_encounter_id']} with Bill-Only PR {$prNumber}",
                newValues: [
                    'request_number' => $reqNumber,
                    'pr_number' => $prNumber,
                    'patient_encounter_id' => $data['patient_encounter_id'],
                ],
            );

            return $record;
        });
    }
}
