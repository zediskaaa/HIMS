<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AlertStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\PurchaseOrder;
use App\Models\StockAlert;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class InventoryController extends Controller implements HasMiddleware
{
    /**
     * The read-only screens, each gated on what it actually shows.
     *
     * index() and live() are deliberately left on plain `auth`: /dashboard is
     * where login redirects, so a 403 there would put a signed-in user in a
     * loop with no way out. It shows counts and alerts, nothing a member of
     * staff should not see, and the panels inside it are individually gated.
     *
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewInventory->value, only: ['stock', 'alerts']),
            new Middleware('can:'.Permission::ViewReports->value, only: ['logistics']),
            new Middleware('can:'.Permission::ManageSuppliers->value, only: ['suppliers']),
            new Middleware('can:'.Permission::ManageProcurement->value, only: ['purchases']),
        ];
    }

    public function index(?Request $request = null): View
    {
        $request ??= request();

        $canViewSuppliers = $request->user()->can(Permission::ViewSuppliers->value);
        $canViewProcurementFinancials = $request->user()->can(Permission::ViewProcurementSensitiveData->value);

        $totalSuppliers = $canViewSuppliers ? Supplier::count() : null;
        $activeSuppliers = $canViewSuppliers ? Supplier::where('status', 'active')->count() : null;
        $inactiveSuppliers = $canViewSuppliers ? Supplier::where('status', 'inactive')->count() : null;
        $totalItems = InventoryItem::count();
        $storageLocations = StorageLocation::count();
        $recentMovements = StockMovement::with(['item', 'fromLocation', 'toLocation'])
            ->latest('moved_at')
            ->latest('id')
            ->take(6)
            ->get();

        $pendingPurchaseOrders = $canViewProcurementFinancials
            ? PurchaseOrder::with(['supplier', 'item'])
                ->whereIn('status', ['draft', 'pending', 'submitted', 'approved'])
                ->latest('requested_at')
                ->take(5)
                ->get()
            : collect();

        $pendingPoCount = $canViewProcurementFinancials
            ? PurchaseOrder::whereIn('status', ['draft', 'pending', 'submitted', 'approved'])->count()
            : null;

        // Batches inside their item's expiry alert window, or already expired.
        $expiringBatches = ItemBatch::with('item')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays(90))
            ->where('status', 'active')
            ->orderBy('expiry_date')
            ->take(5)
            ->get();

        return view('dashboard', array_merge($this->liveSnapshot($request), compact(
            'totalSuppliers',
            'activeSuppliers',
            'inactiveSuppliers',
            'totalItems',
            'storageLocations',
            'recentMovements',
            'pendingPurchaseOrders',
            'pendingPoCount',
            'expiringBatches'
        )));
    }

    /**
     * The 30s poll behind the dashboard's live alert panel.
     *
     * Returns the alert table as rendered HTML rather than JSON rows so the
     * markup stays defined in exactly one Blade partial, plus the counters
     * that sit in the stat tiles above it.
     */
    public function live(Request $request): JsonResponse
    {
        $snapshot = $this->liveSnapshot($request);

        return response()->json(array_filter([
            'alertsHtml' => view('inventory.partials.alerts-table', $snapshot)->render(),
            'openAlertCount' => $snapshot['openAlertCount'],
            'lowStockItems' => $snapshot['lowStockItems'],
            'outOfStockItems' => $snapshot['outOfStockItems'],
            'totalOnHand' => $snapshot['totalOnHand'],
            'totalInventoryValue' => $snapshot['totalInventoryValue'],
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * Everything on the dashboard that moves when stock moves.
     *
     * Shared by the full page render and the poll so the two can never
     * disagree about what "current" means.
     *
     * @return array<string, mixed>
     */
    private function liveSnapshot(?Request $request = null): array
    {
        $request ??= request();

        // Alerts still describing a live condition, worst severity first.
        $activeAlerts = StockAlert::with(['item', 'batch', 'location'])
            ->where('status', '!=', AlertStatus::Resolved)
            ->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")
            ->latest('created_at')
            ->take(6)
            ->get();

        return [
            'activeAlerts' => $activeAlerts,
            'openAlertCount' => StockAlert::where('status', AlertStatus::Open)->count(),
            'lowStockItems' => InventoryItem::whereIn('status', ['low_stock', 'out_of_stock'])->count(),
            'outOfStockItems' => InventoryItem::where('status', 'out_of_stock')->count(),
            'totalOnHand' => (int) InventoryItem::sum('quantity_on_hand'),
            'totalInventoryValue' => $request->user()->can(Permission::ViewProcurementSensitiveData->value)
                ? InventoryItem::get()->sum(
                    fn ($item) => (float) $item->quantity_on_hand * (float) $item->unit_cost
                )
                : null,
        ];
    }

    public function suppliers(): View
    {
        return view('inventory.suppliers.index');
    }

    public function purchases(): View
    {
        return view('inventory.purchases.index');
    }

    public function stock(): View
    {
        return view('inventory.stock.index');
    }

    public function alerts(): View
    {
        return view('inventory.alerts.index');
    }

    public function logistics(): View
    {
        return view('inventory.logistics.index');
    }
}
