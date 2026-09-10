<?php

namespace App\Services\Inventory;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryAutomationService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService
{
    public function __construct(
        private readonly InventoryAutomationService $automationService,
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Dispatch stock from source location into a virtual In-Transit location.
     *
     * @param  array<string, mixed>  $data
     */
    public function dispatchTransfer(array $data, User $dispatcher): StockTransfer
    {
        return DB::transaction(function () use ($data, $dispatcher) {
            $sourceLocation = StorageLocation::findOrFail($data['source_location_id']);
            $destLocation = StorageLocation::findOrFail($data['destination_location_id']);

            if ($sourceLocation->id === $destLocation->id) {
                throw ValidationException::withMessages([
                    'destination_location_id' => ['Source and destination locations cannot be identical.']
                ]);
            }

            // Virtual In-Transit location
            $inTransitLocation = StorageLocation::firstOrCreate(
                ['code' => 'LOC-IN-TRANSIT'],
                [
                    'name' => 'Internal In-Transit Virtual Buffer',
                    'type' => 'zone',
                    'zone' => 'In-Transit',
                    'status' => 'active',
                    'description' => 'Virtual buffer for items departed from source warehouse pending destination receipt.'
                ]
            );

            $transferNumber = 'TR-' . now()->format('Ymd') . '-' . str_pad((string) (StockTransfer::count() + 1), 4, '0', STR_PAD_LEFT);

            $transfer = StockTransfer::create([
                'transfer_number' => $transferNumber,
                'source_location_id' => $sourceLocation->id,
                'destination_location_id' => $destLocation->id,
                'in_transit_location_id' => $inTransitLocation->id,
                'status' => 'in_transit',
                'dispatched_by_id' => $dispatcher->id,
                'dispatched_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $lines = $data['lines'] ?? [];
            if (empty($lines)) {
                throw ValidationException::withMessages([
                    'lines' => ['Transfer must contain at least one line item.']
                ]);
            }

            foreach ($lines as $lineData) {
                $item = InventoryItem::lockForUpdate()->findOrFail($lineData['item_id']);
                $qty = (int) $lineData['quantity'];
                $batchId = $lineData['item_batch_id'] ?? null;

                $available = $this->automationService->availableAt($item->id, $sourceLocation->id, $batchId);
                if ($available < $qty) {
                    throw ValidationException::withMessages([
                        'lines' => ["Insufficient stock for {$item->name} at {$sourceLocation->name}. Available: {$available}, Dispatched: {$qty}."]
                    ]);
                }

                // 1. Decrement source location
                $this->automationService->adjustStockLevel($item->id, $sourceLocation->id, $batchId, -$qty);

                // 2. Increment in-transit stock
                $this->automationService->adjustInTransitStock($item->id, $inTransitLocation->id, $batchId, $qty);

                // 3. Record dispatch movement in ledger
                StockMovement::create([
                    'item_id' => $item->id,
                    'item_batch_id' => $batchId,
                    'movement_type' => MovementType::TransferDispatch,
                    'quantity' => $qty,
                    'unit_cost' => $item->unit_cost,
                    'from_location_id' => $sourceLocation->id,
                    'to_location_id' => $inTransitLocation->id,
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                    'remarks' => "Transfer Dispatch {$transfer->transfer_number} from {$sourceLocation->name} to In-Transit",
                    'moved_at' => now(),
                    'user_id' => $dispatcher->id,
                ]);

                StockTransferLine::create([
                    'stock_transfer_id' => $transfer->id,
                    'item_id' => $item->id,
                    'item_batch_id' => $batchId,
                    'dispatched_quantity' => $qty,
                    'received_quantity' => 0,
                    'damaged_quantity' => 0,
                    'lost_quantity' => 0,
                    'line_status' => 'in_transit',
                    'notes' => $lineData['notes'] ?? null,
                ]);

                $this->automationService->syncItemTotals($item);
            }

            $this->auditLogger->record(
                AuditAction::DispatchedStockTransfer,
                actor: $dispatcher,
                target: $transfer,
                description: "Dispatched Stock Transfer {$transfer->transfer_number} from {$sourceLocation->name} to {$destLocation->name}",
                newValues: [
                    'transfer_number' => $transfer->transfer_number,
                    'source' => $sourceLocation->name,
                    'destination' => $destLocation->name,
                ]
            );

            return $transfer;
        });
    }

    /**
     * Receive transfer at destination warehouse and reconcile discrepancies (loss/damage).
     *
     * @param  array<string, mixed>  $receiveData
     */
    public function receiveTransfer(StockTransfer $transfer, array $receiveData, User $receiver): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $receiveData, $receiver) {
            $tr = StockTransfer::lockForUpdate()->with(['lines.item', 'inTransitLocation', 'destinationLocation'])->findOrFail($transfer->id);

            if ($tr->status !== 'in_transit') {
                throw new DomainException("Cannot receive transfer in status {$tr->status}.");
            }

            $hasDiscrepancy = false;
            $linesInput = $receiveData['lines'] ?? [];

            foreach ($tr->lines as $line) {
                $lineInput = collect($linesInput)->firstWhere('line_id', $line->id) ?? [];
                $receivedQty = isset($lineInput['received_quantity']) ? (int) $lineInput['received_quantity'] : $line->dispatched_quantity;
                $damagedQty = (int) ($lineInput['damaged_quantity'] ?? 0);
                $lostQty = (int) ($lineInput['lost_quantity'] ?? 0);

                if (($receivedQty + $damagedQty + $lostQty) !== $line->dispatched_quantity) {
                    $lostQty = max(0, $line->dispatched_quantity - ($receivedQty + $damagedQty));
                }

                if ($damagedQty > 0 || $lostQty > 0) {
                    $hasDiscrepancy = true;
                }

                $item = InventoryItem::lockForUpdate()->findOrFail($line->item_id);
                $inTransitLocId = $tr->in_transit_location_id;
                $destLocId = $tr->destination_location_id;

                // 1. Decrement In-Transit balance
                $this->automationService->adjustInTransitStock($item->id, $inTransitLocId, $line->item_batch_id, -$line->dispatched_quantity);

                // 2. Increment Destination balance for accepted quantity
                if ($receivedQty > 0) {
                    $this->automationService->adjustStockLevel($item->id, $destLocId, $line->item_batch_id, $receivedQty);

                    StockMovement::create([
                        'item_id' => $item->id,
                        'item_batch_id' => $line->item_batch_id,
                        'movement_type' => MovementType::TransferReceipt,
                        'quantity' => $receivedQty,
                        'unit_cost' => $item->unit_cost,
                        'from_location_id' => $inTransitLocId,
                        'to_location_id' => $destLocId,
                        'reference_type' => StockTransfer::class,
                        'reference_id' => $tr->id,
                        'remarks' => "Transfer Receipt {$tr->transfer_number} into {$tr->destinationLocation->name}",
                        'moved_at' => now(),
                        'user_id' => $receiver->id,
                    ]);
                }

                // 3. Write off transit damage/loss if any
                if ($damagedQty > 0 || $lostQty > 0) {
                    $varianceDelta = $damagedQty + $lostQty;
                    StockMovement::create([
                        'item_id' => $item->id,
                        'item_batch_id' => $line->item_batch_id,
                        'movement_type' => MovementType::Disposal,
                        'quantity' => $varianceDelta,
                        'unit_cost' => $item->unit_cost,
                        'from_location_id' => $inTransitLocId,
                        'to_location_id' => null,
                        'reference_type' => StockTransfer::class,
                        'reference_id' => $tr->id,
                        'remarks' => "Transit Discrepancy Write-off: {$damagedQty} damaged, {$lostQty} lost during transport",
                        'moved_at' => now(),
                        'user_id' => $receiver->id,
                    ]);
                }

                $line->received_quantity = $receivedQty;
                $line->damaged_quantity = $damagedQty;
                $line->lost_quantity = $lostQty;
                $line->line_status = ($damagedQty > 0 || $lostQty > 0) ? 'discrepancy' : 'received';
                $line->save();

                $this->automationService->syncItemTotals($item);
            }

            $tr->status = $hasDiscrepancy ? 'discrepancy' : 'received';
            $tr->received_by_id = $receiver->id;
            $tr->received_at = now();
            $tr->discrepancy_reason = $receiveData['discrepancy_reason'] ?? null;
            $tr->save();

            $this->auditLogger->record(
                AuditAction::ReceivedStockTransfer,
                actor: $receiver,
                target: $tr,
                description: "Received Stock Transfer {$tr->transfer_number} at {$tr->destinationLocation->name} (" . ($hasDiscrepancy ? 'Discrepancies noted' : 'Complete') . ")",
                newValues: [
                    'transfer_number' => $tr->transfer_number,
                    'status' => $tr->status,
                ]
            );

            return $tr;
        });
    }
}
