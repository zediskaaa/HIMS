<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockMovementRequest;
use App\Http\Requests\UpdateStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Services\InventoryAutomationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StockMovementController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:'.Permission::ViewInventory->value, only: ['index', 'show']),
            new Middleware('can:'.Permission::RecordMovements->value, only: ['store', 'update', 'destroy']),
        ];
    }

    public function __construct(private readonly InventoryAutomationService $automationService) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);

        // Unordered pagination put the oldest movements on page 1, so a newly
        // recorded movement appeared to vanish. Newest first, id breaking
        // moved_at ties from multi-batch movements written in one transaction.
        $items = StockMovement::with(['item', 'batch', 'fromLocation', 'toLocation'])
            ->latest('moved_at')
            ->latest('id')
            ->paginate($perPage);

        return StockMovementResource::collection($items);
    }

    public function show(StockMovement $stock_movement)
    {
        return new StockMovementResource($stock_movement->load(['item', 'fromLocation', 'toLocation']));
    }

    public function store(StoreStockMovementRequest $request)
    {
        // This used to call StockMovement::create() directly, which recorded the
        // movement but left item_stock_levels and the cached quantity_on_hand
        // untouched — the API could log a stock out that never moved any stock.
        // The service owns balances, so it has to be the one writing here too.
        $movements = $this->automationService->recordMovement($request->validated(), $request->user()?->id);

        return StockMovementResource::collection($movements->load(['item', 'batch', 'fromLocation', 'toLocation']))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateStockMovementRequest $request, StockMovement $stock_movement)
    {
        $stock_movement->update($request->validated());

        return new StockMovementResource($stock_movement);
    }

    public function destroy(StockMovement $stock_movement)
    {
        $stock_movement->delete();

        return response()->json(null, 204);
    }
}
