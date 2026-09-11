<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class DashboardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:'.Permission::ViewInventory->value];
    }

    public function summary(Request $request)
    {
        $totalItems = InventoryItem::count();
        $lowStockItems = InventoryItem::whereIn('status', ['low_stock', 'out_of_stock'])->count();
        $outOfStockItems = InventoryItem::where('status', 'out_of_stock')->count();
        $totalOnHand = InventoryItem::sum('quantity_on_hand');
        $canViewFinancials = $request->user()->can(Permission::ViewProcurementSensitiveData->value);
        $canViewSuppliers = $request->user()->can(Permission::ViewSuppliers->value);
        $totalInventoryValue = $canViewFinancials ? InventoryItem::sum('total_value') : null;
        $totalSuppliers = $canViewSuppliers ? Supplier::count() : null;
        $activeSuppliers = $canViewSuppliers ? Supplier::where('status', 'active')->count() : null;
        $storageLocations = StorageLocation::count();
        $recentMovements = StockMovement::with(['item'])->latest('moved_at')->take(6)->get()->map(function ($movement) {
            return [
                'id' => $movement->id,
                'item' => $movement->item?->name,
                'movement_type' => $movement->movement_type,
                'quantity' => $movement->quantity,
                'moved_at' => optional($movement->moved_at)->toDateTimeString(),
            ];
        });

        return response()->json(array_filter([
            'total_items' => $totalItems,
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
            'total_on_hand' => $totalOnHand,
            'total_inventory_value' => $totalInventoryValue,
            'total_suppliers' => $totalSuppliers,
            'active_suppliers' => $activeSuppliers,
            'storage_locations' => $storageLocations,
            'recent_movements' => $recentMovements,
        ], fn (mixed $value): bool => $value !== null));
    }
}
