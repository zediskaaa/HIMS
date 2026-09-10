<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
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
        $item = InventoryItem::create($data);

        return (new InventoryItemResource($item))->response()->setStatusCode(201);
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventory_item)
    {
        $inventory_item->update($request->validated());

        return new InventoryItemResource($inventory_item);
    }

    public function destroy(InventoryItem $inventory_item)
    {
        $inventory_item->delete();

        return response()->json(null, 204);
    }
}
