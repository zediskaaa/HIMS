<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\MovementType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryAdjustment;
use App\Models\InventoryItem;
use App\Models\StorageLocation;
use App\Services\Inventory\AdjustmentApprovalService;
use App\Services\InventoryAutomationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class StockAdjustmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:' . Permission::AdjustStock->value, only: ['index', 'store']),
            new Middleware('can:' . Permission::ApproveAdjustment->value, only: ['approve']),
        ];
    }

    public function __construct(
        private readonly InventoryAutomationService $automationService,
        private readonly AdjustmentApprovalService $adjustmentService
    ) {}

    public function index(): View
    {
        $items = InventoryItem::where('status', '!=', 'discontinued')->orderBy('name')->get();
        $locations = StorageLocation::where('status', 'active')->orderBy('name')->get();
        $adjustments = InventoryAdjustment::with(['item', 'location', 'requestedBy', 'approvedBy', 'secondApprovedBy'])
            ->latest()
            ->paginate(15);

        return view('inventory.adjustments.index', compact('items', 'locations', 'adjustments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'adjustment_type' => ['required', 'in:increase,decrease,correction,damage,loss,breakage,expiry,disposal'],
            'quantity' => ['required', 'integer', 'min:0'],
            'location_id' => ['required', 'exists:storage_locations,id'],
            'reason_code' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
            'explanation' => ['nullable', 'string', 'max:500'],
        ]);

        $quantity = (int) $validated['quantity'];
        $locationId = (int) $validated['location_id'];

        $delta = match ($validated['adjustment_type']) {
            'increase' => $quantity,
            'decrease' => -$quantity,
            'correction' => $quantity - $this->automationService->availableAt((int) $validated['item_id'], $locationId),
            default => -$quantity,
        };

        if ($delta === 0) {
            return redirect()->route('inventory.adjustments')
                ->with('info', 'No adjustment applied — the recorded count already matches.');
        }

        $explanation = $validated['explanation'] ?? $validated['reason'] ?? 'Standard inventory count reconciliation';
        $reasonCode = $validated['reason_code'] ?? ($validated['adjustment_type'] === 'correction' ? 'count_variance' : 'data_correction');

        $item = InventoryItem::findOrFail($validated['item_id']);
        $unitCost = (float) ($item->unit_cost ?? 0);
        $totalVarianceValue = abs($delta * $unitCost);
        $threshold = 25000.00; // ₱25,000 threshold for dual approval

        if ($totalVarianceValue > $threshold) {
            // High-value adjustment: Enforce dual-tier authorization workflow
            $adj = $this->adjustmentService->requestAdjustment([
                'item_id' => $validated['item_id'],
                'storage_location_id' => $locationId,
                'adjustment_type' => $validated['adjustment_type'],
                'quantity' => $quantity,
                'reason_code' => $reasonCode,
                'explanation' => $explanation,
            ], $request->user());

            return redirect()->route('inventory.adjustments')
                ->with('success', "Adjustment request {$adj->adjustment_number} (₱" . number_format($totalVarianceValue, 2) . ") exceeds threshold and was submitted for dual authorization.");
        }

        // Routine adjustment: executed directly by authorized inventory manager
        try {
            $this->automationService->recordMovement([
                'item_id' => $validated['item_id'],
                'movement_type' => MovementType::Adjustment,
                'quantity' => $delta,
                'to_location_id' => $locationId,
                'remarks' => $validated['reason'] ?? $explanation,
            ], $request->user()->id);

            // Record in InventoryAdjustment ledger
            $adjNumber = 'ADJ-' . now()->format('Ymd') . '-' . str_pad((string) (InventoryAdjustment::count() + 1), 4, '0', STR_PAD_LEFT);
            $currentQty = $this->automationService->availableAt((int) $validated['item_id'], $locationId);

            InventoryAdjustment::create([
                'adjustment_number' => $adjNumber,
                'item_id' => $validated['item_id'],
                'storage_location_id' => $locationId,
                'current_quantity' => $currentQty - $delta,
                'adjustment_quantity' => $delta,
                'resulting_quantity' => $currentQty,
                'unit_cost' => $unitCost,
                'total_variance_value' => round($delta * $unitCost, 2),
                'adjustment_type' => $validated['adjustment_type'],
                'reason_code' => $reasonCode,
                'explanation' => $validated['reason'] ?? $explanation,
                'status' => 'posted',
                'requested_by_id' => $request->user()->id,
                'approved_by_id' => $request->user()->id,
                'posted_at' => now(),
            ]);

            return redirect()->route('inventory.adjustments')->with('success', 'Stock adjustment applied successfully.');
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['quantity' => $e->getMessage()])->withInput();
        }
    }

    public function approve(Request $request, InventoryAdjustment $inventoryAdjustment): RedirectResponse
    {
        try {
            $updated = $this->adjustmentService->approveAndPost($inventoryAdjustment, $request->user());

            $msg = $updated->status === 'posted'
                ? "Adjustment {$updated->adjustment_number} approved and posted to inventory ledger."
                : "Adjustment {$updated->adjustment_number} approved. Awaiting second-tier Plant Controller authorization.";

            return redirect()->route('inventory.adjustments')->with('success', $msg);
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['approve' => $e->getMessage()]);
        }
    }
}
