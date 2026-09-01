<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class InventoryItemController extends Controller implements HasMiddleware
{
    /**
     * Reading the item list and editing the item master are different jobs.
     * Every department needs to look up an item; only the inventory manager
     * may create or change one.
     *
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewInventory->value, only: ['index']),
            new Middleware('can:'.Permission::ManageItems->value, only: ['store']),
        ];
    }

    public function index(): View
    {
        $items = InventoryItem::with('supplier')->latest()->get();

        return view('inventory.items.index', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:inventory_items'],
            'category' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'quantity_on_hand' => ['nullable', 'integer'],
            'reorder_level' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'warehouse_name' => ['nullable', 'string', 'max:255'],
        ]);

        InventoryItem::create($validated);

        return redirect()->route('inventory.items')->with('success', 'Inventory item created successfully.');
    }
}
