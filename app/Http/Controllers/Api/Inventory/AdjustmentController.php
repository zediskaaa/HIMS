<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryAdjustment;
use App\Services\Inventory\AdjustmentApprovalService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AdjustmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            new Middleware('can:' . Permission::AdjustStock->value, only: ['store']),
            new Middleware('can:' . Permission::ApproveAdjustment->value, only: ['authorizeAdjustment']),
            new Middleware('can:' . Permission::ViewInventory->value, only: ['index', 'show']),
        ];
    }

    public function __construct(private readonly AdjustmentApprovalService $adjustmentService) {}

    public function index(): JsonResponse
    {
        $adjustments = InventoryAdjustment::with(['item', 'location', 'batch', 'requestedBy', 'approvedBy', 'secondApprovedBy'])
            ->latest()
            ->paginate(25);

        return response()->json($adjustments);
    }

    public function show(InventoryAdjustment $inventoryAdjustment): JsonResponse
    {
        $inventoryAdjustment->load(['item', 'location', 'batch', 'requestedBy', 'approvedBy', 'secondApprovedBy']);

        return response()->json($inventoryAdjustment);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'storage_location_id' => ['required', 'exists:storage_locations,id'],
            'item_batch_id' => ['nullable', 'exists:item_batches,id'],
            'adjustment_type' => ['required', 'in:increase,decrease,correction,damage,loss,breakage,expiry,disposal'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason_code' => ['required', 'in:count_variance,damage,breakage,loss,expiry,data_correction,other'],
            'explanation' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $adjustment = $this->adjustmentService->requestAdjustment($validated, $request->user());

        return response()->json([
            'message' => "Inventory adjustment request {$adjustment->adjustment_number} submitted for approval.",
            'data' => $adjustment->load(['item', 'location']),
        ], 201);
    }

    public function authorizeAdjustment(Request $request, int $adjustmentId): JsonResponse
    {
        $adjustment = InventoryAdjustment::findOrFail($adjustmentId);

        try {
            $updated = $this->adjustmentService->approveAndPost($adjustment, $request->user());

            return response()->json([
                'message' => $updated->status === 'posted'
                    ? "Adjustment {$updated->adjustment_number} approved and posted to inventory ledger."
                    : "Adjustment {$updated->adjustment_number} approved. Awaiting second-tier Plant Controller authorization.",
                'data' => $updated->load(['item', 'location']),
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
