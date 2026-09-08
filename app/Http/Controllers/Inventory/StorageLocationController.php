<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\StorageLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class StorageLocationController extends Controller implements HasMiddleware
{
    /**
     * Warehouse staff need to see the zones they move stock between; defining
     * them is part of running the storeroom.
     *
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewInventory->value, only: ['index']),
            new Middleware('can:'.Permission::ManageLocations->value, only: ['store']),
        ];
    }

    public function index(): View
    {
        $locations = StorageLocation::latest()->get();

        return view('inventory.storage_locations.index', compact('locations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:storage_locations'],
            'description' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        StorageLocation::create($validated);

        return redirect()->route('inventory.storage-locations')->with('success', 'Storage location created successfully.');
    }
}
