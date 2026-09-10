<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class InventoryItemController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:'.Permission::ViewInventory->value, only: ['index', 'show']),
            new Middleware('can:'.Permission::ManageItems->value, only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $items = InventoryItem::with('supplier')->paginate($perPage);

        return InventoryItemResource::collection($items);
    }

    public function show(InventoryItem $inventory_item)
    {
        return new InventoryItemResource($inventory_item);
    }

    public function store(StoreInventoryItemRequest $request)
    {
        $data = $request->validated();
        $data += ['is_batch_tracked' => false, 'is_serial_tracked' => false, 'is_expiry_tracked' => false];
        $item = DB::transaction(function () use ($data, $request): InventoryItem {
            $item = InventoryItem::create($data);
            $this->audit->log(
                AuditAction::CreatedInventoryItem,
                $request->user(),
                'Created an inventory item.',
                $item,
                $item->sku,
                newValues: Arr::only($item->getAttributes(), ['sku', 'name', 'unit', 'unit_cost', 'reorder_level', 'status', 'supplier_id']),
            );

            return $item;
        });

        return (new InventoryItemResource($item))->response()->setStatusCode(201);
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventory_item)
    {
        DB::transaction(function () use ($inventory_item, $request): void {
            $fields = ['sku', 'name', 'unit', 'unit_cost', 'reorder_level', 'status', 'supplier_id'];
            $old = Arr::only($inventory_item->getAttributes(), $fields);
            $inventory_item->update($request->validated());
            $new = Arr::only($inventory_item->getAttributes(), $fields);

            $this->audit->log(
                AuditAction::UpdatedInventoryItem,
                $request->user(),
                'Updated an inventory item.',
                $inventory_item,
                $inventory_item->sku,
                $old,
                $new,
            );
        });

        return new InventoryItemResource($inventory_item);
    }

    public function destroy(Request $request, InventoryItem $inventory_item)
    {
        DB::transaction(function () use ($request, $inventory_item): void {
            $snapshot = Arr::only($inventory_item->getAttributes(), ['sku', 'name', 'unit', 'status']);
            $reference = $inventory_item->sku;
            $inventory_item->delete();
            $this->audit->log(
                AuditAction::DeletedInventoryItem,
                $request->user(),
                'Deleted an inventory item.',
                $inventory_item,
                $reference,
                oldValues: $snapshot,
            );
        });

        return response()->json(null, 204);
    }
}
