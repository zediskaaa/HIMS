<?php

namespace App\Services\Logistics;

use App\Enums\AuditAction;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShipmentTrackingService
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected ChainOfCustodyService $custodyService
    ) {}

    /**
     * Generate unique Shipment Number: SHP-YYYY-XXXXX
     */
    public function generateShipmentNumber(): string
    {
        $year = date('Y');
        $prefix = "SHP-{$year}-";

        $latest = Shipment::where('shipment_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('shipment_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest, $matches)) {
            $nextSeq = ((int) $matches[1]) + 1;
        }

        return sprintf('%s%05d', $prefix, $nextSeq);
    }

    /**
     * Validate GS1-128 Serial Shipping Container Code (SSCC-18).
     * Must be 18 digits with valid Modulo 10 check digit.
     */
    public function validateSscc(string $sscc): bool
    {
        $cleaned = trim($sscc);
        if (! preg_match('/^\d{18}$/', $cleaned)) {
            return false;
        }

        $digits = str_split($cleaned);
        $checkDigit = (int) array_pop($digits);

        $sum = 0;
        foreach (array_reverse($digits) as $posFromRight => $digit) {
            // Position from right to left (1-indexed odd/even multiplier: 3, 1, 3, 1...)
            $multiplier = (($posFromRight % 2) === 0) ? 3 : 1;
            $sum += ((int) $digit) * $multiplier;
        }

        $calculatedCheck = (10 - ($sum % 10)) % 10;

        return $calculatedCheck === $checkDigit;
    }

    /**
     * Register a new inbound shipment.
     */
    public function registerInboundShipment(array $data, User $actor): Shipment
    {
        if (! empty($data['sscc']) && ! $this->validateSscc($data['sscc'])) {
            throw new InvalidArgumentException("The provided SSCC [{$data['sscc']}] is not a valid 18-digit GS1 SSCC with check digit.");
        }

        return DB::transaction(function () use ($data, $actor) {
            $shipmentNumber = $this->generateShipmentNumber();

            $po = null;
            if (! empty($data['purchase_order_id'])) {
                $po = PurchaseOrder::find($data['purchase_order_id']);
            }

            $shipment = Shipment::create([
                'shipment_number' => $shipmentNumber,
                'purchase_order_id' => $po?->id,
                'supplier_id' => $po?->supplier_id ?? $data['supplier_id'] ?? null,
                'carrier_name' => $data['carrier_name'] ?? 'In-house / Vendor Fleet',
                'tracking_number' => $data['tracking_number'] ?? null,
                'waybill_number' => $data['waybill_number'] ?? null,
                'vehicle_plate_number' => $data['vehicle_plate_number'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'driver_contact' => $data['driver_contact'] ?? null,
                'sscc' => $data['sscc'] ?? null,
                'origin_address' => $data['origin_address'] ?? null,
                'destination_facility' => $data['destination_facility'] ?? 'HIMS Central Receiving Dock',
                'dispatch_date' => $data['dispatch_date'] ?? now()->toDateString(),
                'estimated_delivery_date' => $data['estimated_delivery_date'] ?? ($po?->delivery_date?->toDateString() ?? now()->toDateString()),
                'status' => 'dispatched',
                'is_cold_chain' => (bool) ($data['is_cold_chain'] ?? false),
                'temp_logger_serial' => $data['temp_logger_serial'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Create initial chain of custody record
            $this->custodyService->recordTransfer(
                trackable: $shipment,
                data: [
                    'event_type' => 'dock_arrival',
                    'releasing_party_name' => $shipment->driver_name ?? $shipment->carrier_name,
                    'receiving_user_id' => $actor->id,
                    'receiving_party_name' => 'In-Transit Courier',
                    'origin_location' => $shipment->origin_address ?? 'Origin Hub',
                    'destination_location' => $shipment->destination_facility,
                    'package_condition' => 'good_order',
                    'verification_method' => 'credential_auth',
                    'notes' => "Shipment {$shipmentNumber} dispatched via {$shipment->carrier_name}. SSCC: ".($shipment->sscc ?? 'N/A'),
                ],
                actor: $actor
            );

            $this->auditLogger->record(
                action: AuditAction::ShipmentDispatched,
                actor: $actor,
                target: $shipment,
                description: "Inbound shipment {$shipmentNumber} registered for PO ".($po?->po_number ?? 'Direct')." via {$shipment->carrier_name}."
            );

            return $shipment;
        });
    }

    /**
     * Record shipment arrival at hospital receiving dock and verify cold chain telemetry.
     */
    public function recordDockArrival(Shipment $shipment, array $dockData, User $receiver): Shipment
    {
        if ($shipment->isDelivered()) {
            throw new InvalidArgumentException("Shipment {$shipment->shipment_number} has already been received at dock.");
        }

        return DB::transaction(function () use ($shipment, $dockData, $receiver) {
            $isColdChain = $shipment->is_cold_chain;
            $tempMin = isset($dockData['temp_min']) ? (float) $dockData['temp_min'] : $shipment->temp_min;
            $tempMax = isset($dockData['temp_max']) ? (float) $dockData['temp_max'] : $shipment->temp_max;
            $tempExcursion = false;

            // Hospital cold-chain standard: 2.0°C to 8.0°C (WHO / FDA AO 2013-0027)
            if ($isColdChain && $tempMin !== null && $tempMax !== null) {
                if ($tempMin < 2.0 || $tempMax > 8.0) {
                    $tempExcursion = true;
                }
            }

            $shipment->update([
                'status' => 'arrived_at_dock',
                'actual_delivery_date' => $dockData['actual_delivery_date'] ?? now()->toDateString(),
                'temp_min' => $tempMin,
                'temp_max' => $tempMax,
                'temp_logger_serial' => $dockData['temp_logger_serial'] ?? $shipment->temp_logger_serial,
                'temp_excursion' => $tempExcursion,
                'notes' => trim(($shipment->notes ?? '')."\nArrival Notes: ".($dockData['notes'] ?? 'Arrived at dock.')),
            ]);

            // Chain of custody transfer to hospital receiving officer
            $this->custodyService->recordTransfer(
                trackable: $shipment,
                data: [
                    'event_type' => 'dock_arrival',
                    'releasing_party_name' => $shipment->driver_name ?? $shipment->carrier_name,
                    'receiving_user_id' => $receiver->id,
                    'receiving_party_name' => $receiver->name . ' (Receiving Officer)',
                    'origin_location' => $shipment->origin_address ?? 'In-Transit Vehicle',
                    'destination_location' => 'Central Receiving Dock',
                    'package_condition' => $tempExcursion ? 'cold_chain_excursion' : 'good_order',
                    'verification_method' => 'credential_auth',
                    'notes' => "Shipment arrived. Cold Chain: ".($isColdChain ? 'YES' : 'NO').
                             ($isColdChain ? " (Min: {$tempMin}°C, Max: {$tempMax}°C, Excursion: ".($tempExcursion ? 'DETECTED-QUARANTINE' : 'PASS').')' : ''),
                ],
                actor: $receiver
            );

            $this->auditLogger->record(
                action: AuditAction::ShipmentArrivedDock,
                actor: $receiver,
                target: $shipment,
                description: "Shipment {$shipment->shipment_number} arrived at receiving dock. Cold Chain Excursion: ".($tempExcursion ? 'YES' : 'NO')
            );

            return $shipment;
        });
    }

    /**
     * Update transit status (e.g. in_transit, custom hold).
     */
    public function updateStatus(Shipment $shipment, string $status, string $location, ?string $remarks, User $actor): Shipment
    {
        $oldStatus = $shipment->status;
        $shipment->update(['status' => $status]);

        $this->custodyService->recordTransfer(
            trackable: $shipment,
            data: [
                'event_type' => 'dock_receiving',
                'releasing_party_name' => $shipment->carrier_name,
                'receiving_party_name' => $shipment->carrier_name,
                'origin_location' => $location,
                'destination_location' => $location,
                'package_condition' => 'good_order',
                'verification_method' => 'credential_auth',
                'notes' => "Status changed from {$oldStatus} to {$status}. Remarks: {$remarks}",
            ],
            actor: $actor
        );

        $this->auditLogger->record(
            action: AuditAction::ShipmentStatusUpdated,
            actor: $actor,
            target: $shipment,
            description: "Shipment {$shipment->shipment_number} status updated to {$status}."
        );

        return $shipment;
    }
}
