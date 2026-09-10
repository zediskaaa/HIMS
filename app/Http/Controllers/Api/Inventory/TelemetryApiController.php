<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\StorageLocation;
use App\Services\Warehouse\TelemetryService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class TelemetryApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:'.Permission::ManageTelemetryExcursions->value];
    }

    public function __construct(
        private readonly TelemetryService $telemetry,
    ) {}

    public function ingest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sensor_id' => ['required', 'string', 'max:60'],
            'storage_location_id' => ['required', 'exists:storage_locations,id'],
            'temperature_celsius' => ['required', 'numeric', 'between:-100,60'],
            'relative_humidity_pct' => ['nullable', 'numeric', 'between:0,100'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        try {
            $log = $this->telemetry->ingest($validated, $request->user());
            $location = StorageLocation::find($validated['storage_location_id']);
            $mkt = $this->telemetry->calculateMkt($location, null, 24);

            return response()->json([
                'status' => 'success',
                'message' => 'Telemetry reading ingested successfully.',
                'data' => [
                    'id' => $log->id,
                    'sensor_id' => $log->sensor_id,
                    'location_code' => $location?->code,
                    'temperature_celsius' => $log->temperature_celsius,
                    'relative_humidity_pct' => $log->relative_humidity_pct,
                    'excursion_status' => $log->excursion_status,
                    'excursion_hold' => (bool) $location?->excursion_hold,
                    'rolling_mkt_24h' => $mkt,
                    'recorded_at' => $log->recorded_at?->toIso8601String(),
                ],
            ], 201);
        } catch (DomainException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
