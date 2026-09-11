<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Services\InventoryReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class DashboardController extends Controller implements HasMiddleware
{
    public function __construct(private readonly InventoryReportService $reports) {}

    public static function middleware(): array
    {
        return ['can:'.Permission::ViewInventory->value];
    }

    public function summary(Request $request)
    {
        $stockStatus = $this->reports->stockStatus();
        $summary = $this->reports->summary($stockStatus);
        $canViewFinancials = $request->user()->can(Permission::ViewProcurementSensitiveData->value);
        $canViewSuppliers = $request->user()->can(Permission::ViewSuppliers->value);
        $totalInventoryValue = $canViewFinancials ? $summary['stock_value'] : null;
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
            'total_items' => $summary['items'],
            'low_stock_items' => $summary['needs_attention'],
            'out_of_stock_items' => $stockStatus['out_of_stock']['items'],
            'total_on_hand' => $summary['units_on_hand'],
            'reserved_units' => $summary['reserved_units'],
            'total_inventory_value' => $totalInventoryValue,
            'total_suppliers' => $totalSuppliers,
            'active_suppliers' => $activeSuppliers,
            'storage_locations' => $storageLocations,
            'recent_movements' => $recentMovements,
        ], fn (mixed $value): bool => $value !== null));
    }
}
