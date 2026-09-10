<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Services\Inventory\TransferService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
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

    public function __construct(private readonly TransferService $transferService) {}

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

        return view('inventory.transfers.index', compact('transfers', 'locations', 'items'));
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
        ]);

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
