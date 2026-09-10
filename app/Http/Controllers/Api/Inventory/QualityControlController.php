<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ItemBatch;
use App\Models\QualityInspection;
use App\Models\StorageLocation;
use App\Services\Inventory\QualityControlService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class QualityControlController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            new Middleware('can:' . Permission::InspectStock->value),
        ];
    }

    public function __construct(private readonly QualityControlService $qcService) {}

    public function releaseByBatch(Request $request, int $batchId): JsonResponse
    {
        $validated = $request->validate([
            'accepted_quantity' => ['required', 'integer', 'min:1'],
            'target_location_id' => ['required', 'exists:storage_locations,id'],
            'findings' => ['nullable', 'string', 'max:500'],
        ]);

        $batch = ItemBatch::findOrFail($batchId);
        $inspection = QualityInspection::where('item_batch_id', $batch->id)
            ->where('inspection_status', 'pending_sample')
            ->first();

        if (!$inspection) {
            $inspection = QualityInspection::where('item_id', $batch->item_id)
                ->where('inspection_status', 'pending_sample')
                ->firstOrFail();
        }

        try {
            $released = $this->qcService->releaseLot(
                $inspection,
                (int) $validated['accepted_quantity'],
                (int) $validated['target_location_id'],
                $request->user(),
                $validated['findings'] ?? null
            );

            return response()->json([
                'message' => 'Stock released from quarantine to unrestricted inventory.',
                'data' => $released->load(['item', 'batch']),
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function releaseInspection(Request $request, QualityInspection $inspection): JsonResponse
    {
        $validated = $request->validate([
            'accepted_quantity' => ['required', 'integer', 'min:1'],
            'target_location_id' => ['required', 'exists:storage_locations,id'],
            'findings' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $released = $this->qcService->releaseLot(
                $inspection,
                (int) $validated['accepted_quantity'],
                (int) $validated['target_location_id'],
                $request->user(),
                $validated['findings'] ?? null
            );

            return response()->json([
                'message' => 'Inspection approved and stock released to unrestricted inventory.',
                'data' => $released->load(['item', 'batch']),
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function rejectInspection(Request $request, QualityInspection $inspection): JsonResponse
    {
        $validated = $request->validate([
            'rejected_quantity' => ['required', 'integer', 'min:1'],
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $rejected = $this->qcService->rejectLot(
                $inspection,
                (int) $validated['rejected_quantity'],
                $validated['rejection_reason'],
                $request->user()
            );

            return response()->json([
                'message' => 'Stock rejected from quarantine and placed in blocked inventory.',
                'data' => $rejected->load(['item', 'batch']),
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
