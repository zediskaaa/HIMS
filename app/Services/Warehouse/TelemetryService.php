<?php

namespace App\Services\Warehouse;

use App\Enums\AuditAction;
use App\Models\IoTTelemetryLog;
use App\Models\ItemStockLevel;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TelemetryService
{
    public const DELTA_H_OVER_R = 10000.0; // Delta H (83.144 kJ/mol) / R (8.3144 J/mol*K)

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Ingest a single telemetry reading from an IoT sensor gateway.
     *
     * @param array{
     *     sensor_id: string,
     *     storage_location_id: int,
     *     temperature_celsius: float|int,
     *     relative_humidity_pct?: float|int|null,
     *     recorded_at?: string|Carbon|null
     * } $data
     */
    public function ingest(array $data, ?User $actor = null): IoTTelemetryLog
    {
        return DB::transaction(function () use ($data, $actor): IoTTelemetryLog {
            $location = StorageLocation::lockForUpdate()->findOrFail($data['storage_location_id']);
            $temp = (float) $data['temperature_celsius'];
            $humidity = isset($data['relative_humidity_pct']) ? (float) $data['relative_humidity_pct'] : null;
            $recordedAt = ! empty($data['recorded_at']) ? Carbon::parse($data['recorded_at']) : now();

            [$status, $event] = $this->evaluateTemperatureBounds($location, $temp);

            $log = IoTTelemetryLog::create([
                'sensor_id' => $data['sensor_id'],
                'storage_location_id' => $location->id,
                'temperature_celsius' => $temp,
                'relative_humidity_pct' => $humidity,
                'excursion_status' => $status,
                'resulting_event' => $event,
                'recorded_at' => $recordedAt,
            ]);

            if ($status === 'excursion') {
                $this->handleExcursionBreach($location, $temp, $log, $actor);
            }

            return $log;
        });
    }

    /**
     * Compute Mean Kinetic Temperature (MKT) using the Haynes equation over a time window.
     *
     * @param iterable<float|int>|null $temperatures
     */
    public function calculateMkt(StorageLocation $location, ?iterable $temperatures = null, int $hours = 24): ?float
    {
        if ($temperatures === null) {
            $since = now()->subHours($hours);
            $temperatures = $location->telemetryLogs()
                ->where('recorded_at', '>=', $since)
                ->pluck('temperature_celsius')
                ->map(fn ($val) => (float) $val)
                ->all();
        }

        $list = is_array($temperatures) ? $temperatures : iterator_to_array($temperatures);
        $count = count($list);
        if ($count === 0) {
            return null;
        }

        $sumExponents = 0.0;
        foreach ($list as $tempC) {
            $kelvin = $tempC + 273.15;
            if ($kelvin <= 0) {
                continue;
            }
            $sumExponents += exp(-self::DELTA_H_OVER_R / $kelvin);
        }

        if ($sumExponents <= 0) {
            return null;
        }

        $averageExponent = $sumExponents / $count;
        $mktKelvin = self::DELTA_H_OVER_R / (-log($averageExponent));
        $mktCelsius = $mktKelvin - 273.15;

        return round($mktCelsius, 2);
    }

    /**
     * Release an active excursion hold following formal stability review.
     */
    public function releaseExcursionHold(StorageLocation $location, string $justification, User $pharmacist): StorageLocation
    {
        return DB::transaction(function () use ($location, $justification, $pharmacist): StorageLocation {
            $locked = StorageLocation::lockForUpdate()->findOrFail($location->id);
            if (! $locked->excursion_hold) {
                throw new DomainException("Location {$locked->code} is not currently in an excursion hold.");
            }

            $locked->excursion_hold = false;
            $locked->save();

            // Re-activate stock levels in this location
            ItemStockLevel::query()
                ->where('storage_location_id', $locked->id)
                ->update(['quarantined_quantity' => 0]);

            $this->auditLogger->record(
                AuditAction::ReleasedQuarantineStock,
                actor: $pharmacist,
                target: $locked,
                description: "Released temperature excursion hold on {$locked->code}. Justification: {$justification}",
                newValues: ['excursion_hold' => false, 'justification' => $justification],
            );

            return $locked;
        });
    }

    /**
     * @return array{0: string, 1: string} [status, eventDescription]
     */
    private function evaluateTemperatureBounds(StorageLocation $location, float $temp): array
    {
        $class = strtolower($location->temperature_classification ?? 'ambient');

        return match ($class) {
            'refrigerated', 'cold_chain' => match (true) {
                $temp < 1.0 => ['excursion', 'Freezing excursion breach below 1.0°C'],
                $temp > 8.5 => ['excursion', 'Critical heat excursion breach above 8.5°C'],
                $temp > 6.5 => ['warning', 'Elevated temperature warning (6.5°C - 8.5°C)'],
                default => ['normal', 'Steady-state cold chain compliance (2.0°C - 8.0°C)'],
            },
            'frozen' => match (true) {
                $temp > -15.0 => ['excursion', 'Freezer warming breach above -15.0°C'],
                $temp > -18.0 => ['warning', 'Freezer warming warning (-18.0°C to -15.0°C)'],
                default => ['normal', 'Steady-state frozen compliance (below -20.0°C)'],
            },
            'ultra_cold' => match (true) {
                $temp > -60.0 => ['excursion', 'Ultra-cold warming breach above -60.0°C'],
                $temp > -70.0 => ['warning', 'Ultra-cold warning (-70.0°C to -60.0°C)'],
                default => ['normal', 'Steady-state ultra-cold compliance (-80.0°C)'],
            },
            default => match (true) { // ambient_controlled (15°C - 25°C)
                $temp > 30.0 || $temp < 10.0 => ['excursion', 'Ambient controlled temperature excursion breach'],
                $temp > 25.0 => ['warning', 'Ambient temperature warning above 25.0°C'],
                default => ['normal', 'Steady-state ambient compliance (15.0°C - 25.0°C)'],
            },
        };
    }

    private function handleExcursionBreach(StorageLocation $location, float $temp, IoTTelemetryLog $log, ?User $actor): void
    {
        if (! $location->excursion_hold) {
            $location->excursion_hold = true;
            $location->save();

            // Automatically place stock in quarantine hold
            ItemStockLevel::query()
                ->where('storage_location_id', $location->id)
                ->where('quantity', '>', 0)
                ->each(function (ItemStockLevel $level) {
                    $level->quarantined_quantity = $level->quantity;
                    $level->save();
                });

            $this->auditLogger->record(
                AuditAction::UpdatedStorageLocationStatus,
                actor: $actor,
                target: $location,
                description: "Automated excursion lock engaged for {$location->code} at {$temp}°C (Sensor: {$log->sensor_id}). All batches locked to EXCURSION_HOLD.",
                newValues: ['excursion_hold' => true, 'temperature_celsius' => $temp],
            );
        }
    }
}
