<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Services\Inventory\TransferService;
use App\Services\InventoryAutomationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockTransferController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:' . Permission::TransferStock->value, only: ['store', 'receive']),
            new Middleware('can:' . Permission::ViewInventory->value, only: ['index', 'show']),
        ];
    }

    public function __construct(
        private readonly TransferService $transferService,
        private readonly InventoryAutomationService $automationService
    ) {}

    public function index(): View
    {
        $transfers = StockTransfer::with(['sourceLocation', 'destinationLocation', 'dispatchedBy', 'receivedBy', 'lines.item'])
            ->latest()
            ->paginate(15);

        $locations = StorageLocation::where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('zone')
                    ->orWhere('zone', '!=', 'In-Transit');
            })
            ->orderBy('name')
            ->get();

        $items = InventoryItem::where('quantity_on_hand', '>', 0)->orderBy('name')->get();

        $stockLevels = ItemStockLevel::query()
            ->where('quantity', '>', 0)
            ->select('item_id', 'storage_location_id', DB::raw('SUM(quantity) as available_qty'))
            ->groupBy('item_id', 'storage_location_id')
            ->get();

        $locationStockMap = [];
        foreach ($stockLevels as $sl) {
            $locationStockMap[$sl->storage_location_id][$sl->item_id] = (int) $sl->available_qty;
        }

        return view('inventory.transfers.index', compact('transfers', 'locations', 'items', 'locationStockMap'));
    }

    public function show(StockTransfer $stockTransfer): View
    {
        $stockTransfer->load(['sourceLocation', 'destinationLocation', 'inTransitLocation', 'dispatchedBy', 'receivedBy', 'lines.item', 'lines.batch']);

        return view('inventory.transfers.show', compact('stockTransfer'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_location_id' => ['required', 'exists:storage_locations,id'],
            'destination_location_id' => ['required', 'exists:storage_locations,id', 'different:source_location_id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'exists:inventory_items,id'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.item_batch_id' => ['nullable', 'exists:item_batches,id'],
        ], [
            'destination_location_id.different' => 'Ang Origin at Destination location ay hindi maaaring magkatulad.',
        ]);

        $sourceLocation = StorageLocation::find($validated['source_location_id']);
        $insufficientErrors = [];
        foreach ($validated['lines'] as $index => $line) {
            $available = $this->automationService->availableAt((int) $line['item_id'], (int) $validated['source_location_id'], $line['item_batch_id'] ?? null);
            $qty = (int) $line['quantity'];
            if ($qty > $available) {
                $item = InventoryItem::find($line['item_id']);
                $itemName = $item ? $item->name : "Item #{$line['item_id']}";
                $locName = $sourceLocation ? $sourceLocation->name : 'Origin Location';
                $insufficientErrors["lines.{$index}.quantity"] = "Kulang ang stock para sa {$itemName} sa {$locName}. Mayroon lamang {$available} units na available, ngunit {$qty} units ang inilagay mo.";
            }
        }

        if (!empty($insufficientErrors)) {
            return redirect()->back()
                ->withErrors($insufficientErrors)
                ->withInput();
        }

        try {
            $transfer = $this->transferService->dispatchTransfer($validated, $request->user());

            return redirect()->route('inventory.transfers.index')
                ->with('success', "Transfer {$transfer->transfer_number} dispatched to In-Transit buffer.");
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['transfer' => $e->getMessage()])->withInput();
        }
    }

    public function receive(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['nullable', 'array'],
            'lines.*.line_id' => ['required', 'exists:stock_transfer_lines,id'],
            'lines.*.received_quantity' => ['required', 'integer', 'min:0'],
            'lines.*.damaged_quantity' => ['nullable', 'integer', 'min:0'],
            'lines.*.lost_quantity' => ['nullable', 'integer', 'min:0'],
            'discrepancy_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $received = $this->transferService->receiveTransfer($stockTransfer, $validated, $request->user());

            $msg = $received->status === 'discrepancy'
                ? "Transfer {$received->transfer_number} received with transit discrepancies recorded."
                : "Transfer {$received->transfer_number} received in full.";

            return redirect()->route('inventory.transfers.show', $stockTransfer)->with('success', $msg);
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['receive' => $e->getMessage()]);
        }
    }
}
