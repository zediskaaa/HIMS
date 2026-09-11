<?php

namespace App\Services\Warehouse;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\WarehouseTaskStatus;
use App\Enums\WarehouseTaskType;
use App\Models\InventoryItem;
use App\Models\InventorySerial;
use App\Models\MaterialRequisition;
use App\Models\MaterialRequisitionLine;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\WarehouseException;
use App\Models\WarehouseScanEvent;
use App\Models\WarehouseTask;
use App\Models\WarehouseTaskEvent;
use App\Services\AuditLogger;
use App\Services\InventoryAutomationService;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WarehouseTaskService
{
    public function __construct(
        private readonly BarcodeService $barcodeService,
        private readonly LocationCompatibilityService $compatibilityService,
        private readonly InventoryAutomationService $inventory,
        private readonly AuditLogger $auditLogger,
        private readonly LedgerIntegrityService $ledgerIntegrity,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $actor = null, ?Model $reference = null): WarehouseTask
    {
        return DB::transaction(function () use ($data, $actor, $reference): WarehouseTask {
            if (! empty($data['idempotency_key'])) {
                $existing = WarehouseTask::query()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return $existing;
                }
            }

            $type = $data['task_type'] instanceof WarehouseTaskType
                ? $data['task_type']
                : WarehouseTaskType::from($data['task_type']);

            if ($type->movesStock() && (empty($data['source_location_id']) || empty($data['destination_location_id']))) {
                throw new DomainException('Stock-moving warehouse tasks require source and destination locations.');
            }
            if ($type->movesStock()) {
                if ((int) $data['source_location_id'] === (int) $data['destination_location_id']) {
                    throw new DomainException('Source and destination locations must be different.');
                }
                $item = InventoryItem::findOrFail($data['item_id']);
                if ($item->is_serial_tracked && (int) $data['requested_quantity'] !== 1) {
                    throw new DomainException('Serialized items require one warehouse task per serial number.');
                }
                $destination = StorageLocation::findOrFail($data['destination_location_id']);
                $this->assertTaskDestination($type, $destination, $item, (int) $data['requested_quantity']);
                if (! empty($data['item_batch_id']) && ! $item->batches()->whereKey($data['item_batch_id'])->exists()) {
                    throw new DomainException('The selected lot does not belong to the selected item.');
                }
            }

            $task = WarehouseTask::create([
                ...$data,
                'task_number' => $data['task_number'] ?? $this->identifier($type),
                'task_type' => $type,
                'status' => ! empty($data['assigned_to_id']) ? WarehouseTaskStatus::Assigned : WarehouseTaskStatus::Ready,
                'created_by_id' => $actor?->id,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);

            $this->event($task, 'created', null, $task->status, $actor);
            $this->auditLogger->record(
                AuditAction::CreatedWarehouseTask,
                actor: $actor,
                target: $task,
                description: "Created {$type->label()} task {$task->task_number}",
                newValues: ['task_number' => $task->task_number, 'task_type' => $type->value, 'status' => $task->status->value],
            );

            return $task;
        });
    }

    public function assign(WarehouseTask $task, User $assignee, User $actor): WarehouseTask
    {
        if (! $assignee->hasPermission(Permission::ExecuteWarehouseTasks)) {
            throw new DomainException('The selected assignee is not an active warehouse task operator.');
        }

        return DB::transaction(function () use ($task, $assignee, $actor): WarehouseTask {
            $locked = WarehouseTask::lockForUpdate()->findOrFail($task->id);
            if (in_array($locked->status, [WarehouseTaskStatus::Completed, WarehouseTaskStatus::Cancelled], true)) {
                throw new DomainException('Completed or cancelled tasks cannot be reassigned.');
            }

            $from = $locked->status;
            $locked->assigned_to_id = $assignee->id;
            $locked->status = WarehouseTaskStatus::Assigned;
            $locked->save();
            $this->event($locked, 'assigned', $from, $locked->status, $actor, ['assignee_id' => $assignee->id]);
            $this->auditLogger->record(AuditAction::AssignedWarehouseTask, actor: $actor, target: $locked,
                description: "Assigned warehouse task {$locked->task_number} to {$assignee->name}",
                newValues: ['assigned_to_id' => $assignee->id]);

            return $locked;
        });
    }

    public function start(WarehouseTask $task, User $actor): WarehouseTask
    {
        return DB::transaction(function () use ($task, $actor): WarehouseTask {
            $locked = WarehouseTask::lockForUpdate()->with('sourceLocation')->findOrFail($task->id);
            $this->assertOperator($locked, $actor);
            if (! in_array($locked->status, [WarehouseTaskStatus::Ready, WarehouseTaskStatus::Assigned, WarehouseTaskStatus::PartiallyCompleted], true)) {
                throw new DomainException("Task {$locked->task_number} cannot be started from {$locked->status->label()}.");
            }
            if ($locked->sourceLocation?->excursion_hold) {
                throw new DomainException("Source location {$locked->sourceLocation->code} is under an active temperature excursion hold.");
            }

            $from = $locked->status;
            $locked->assigned_to_id ??= $actor->id;
            $locked->status = WarehouseTaskStatus::InProgress;
            $locked->started_at ??= now();
            $locked->save();
            $this->event($locked, 'started', $from, $locked->status, $actor);
            $this->auditLogger->record(AuditAction::StartedWarehouseTask, actor: $actor, target: $locked,
                description: "Started warehouse task {$locked->task_number}");

            return $locked;
        });
    }

    public function scan(WarehouseTask $task, string $rawValue, User $actor, ?string $idempotencyKey = null): WarehouseScanEvent
    {
        return DB::transaction(function () use ($task, $rawValue, $actor, $idempotencyKey): WarehouseScanEvent {
            if ($idempotencyKey) {
                $existing = WarehouseScanEvent::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $locked = WarehouseTask::lockForUpdate()->findOrFail($task->id);
            $this->assertOperator($locked, $actor);
            if ($locked->status !== WarehouseTaskStatus::InProgress) {
                if (in_array($locked->status, [WarehouseTaskStatus::Ready, WarehouseTaskStatus::Assigned], true)) {
                    $this->start($locked, $actor);
                    $locked->refresh();
                } else {
                    throw new DomainException('Start the task before scanning.');
                }
            }

            $parsed = $this->barcodeService->parseAndResolve($rawValue);
            // Only accepted scans advance the sequence, so an identification scan
            // never consumes a step.
            $accepted = $locked->scans()->where('outcome', 'accepted')->count();
            $expected = $this->expectedScan($locked, $accepted);
            [$valid, $message, $match] = $this->validateScan($locked, $expected, $parsed);

            $event = WarehouseScanEvent::create([
                'scan_identifier' => 'SCN-'.Str::ulid(),
                'warehouse_task_id' => $locked->id,
                'sequence_number' => $accepted + 1,
                'raw_value' => $rawValue,
                'normalized_value' => $parsed['normalized'],
                'symbology' => $parsed['symbology'],
                'resolved_type' => $parsed['resolved_type'],
                'resolved_id' => $parsed['resolved_id'],
                'outcome' => match ($match) {
                    'proceed' => 'accepted',
                    'identify' => 'identified',
                    default => 'rejected',
                },
                'message' => $message,
                'metadata' => array_filter([
                    'gtin' => $parsed['gtin'],
                    'batch' => $parsed['batch'],
                    'expiry' => $parsed['expiry'],
                    'serial' => $parsed['serial'],
                    'expected' => $expected,
                ], fn ($value) => $value !== null),
                'scanned_by_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);

            // A task label answers "which task am I on?", so it is recorded and
            // acknowledged rather than escalated into an exception ticket.
            if (! $valid && $match === 'reject') {
                $this->raiseException($locked, $this->exceptionTypeFor($expected, $parsed), $message, $actor);
            }

            return $event;
        });
    }

    public function complete(WarehouseTask $task, int $quantity, User $actor, ?string $overrideReason = null): WarehouseTask
    {
        return DB::transaction(function () use ($task, $quantity, $actor, $overrideReason): WarehouseTask {
            $locked = WarehouseTask::lockForUpdate()->with(['item', 'batch', 'reference'])->findOrFail($task->id);
            $this->assertOperator($locked, $actor);
            if ($locked->status !== WarehouseTaskStatus::InProgress) {
                throw new DomainException('Only an in-progress task can be completed.');
            }
            if ($quantity <= 0 || $quantity > $locked->remainingQuantity()) {
                throw new DomainException('Completed quantity must be positive and cannot exceed the task remainder.');
            }

            $requiredScans = $this->requiredScanCount($locked);
            if ($locked->scans()->where('outcome', 'accepted')->count() < $requiredScans) {
                throw new DomainException('Complete every required scan for this task first.');
            }

            if ($locked->task_type->movesStock()) {
                $this->assertTaskDestination($locked->task_type, $locked->destinationLocation()->firstOrFail(), $locked->item, $quantity);
                $this->completeStockMovement($locked, $quantity, $actor);
            } elseif ($locked->task_type === WarehouseTaskType::Dispatch) {
                $this->completeDispatch($locked, $quantity, $actor);
            }

            $from = $locked->status;
            $locked->completed_quantity += $quantity;
            $locked->status = $locked->completed_quantity >= $locked->requested_quantity
                ? WarehouseTaskStatus::Completed
                : WarehouseTaskStatus::PartiallyCompleted;
            $locked->completed_at = $locked->status === WarehouseTaskStatus::Completed ? now() : null;
            $locked->override_reason = $overrideReason;
            $locked->save();

            $this->event($locked, 'completed', $from, $locked->status, $actor, ['quantity' => $quantity]);
            $this->auditLogger->record(AuditAction::CompletedWarehouseTask, actor: $actor, target: $locked,
                description: "Completed {$quantity} units on warehouse task {$locked->task_number}",
                newValues: ['completed_quantity' => $locked->completed_quantity, 'status' => $locked->status->value]);

            if ($locked->status === WarehouseTaskStatus::Completed) {
                $this->createDependentTask($locked, $actor);
            }

            return $locked;
        });
    }

    public function cancel(WarehouseTask $task, string $reason, User $actor): WarehouseTask
    {
        return DB::transaction(function () use ($task, $reason, $actor): WarehouseTask {
            $locked = WarehouseTask::lockForUpdate()->findOrFail($task->id);
            if (in_array($locked->status, [WarehouseTaskStatus::Completed, WarehouseTaskStatus::Cancelled], true)) {
                throw new DomainException('This task can no longer be cancelled.');
            }
            $from = $locked->status;
            $locked->status = WarehouseTaskStatus::Cancelled;
            $locked->override_reason = $reason;
            $locked->save();
            $this->event($locked, 'cancelled', $from, $locked->status, $actor, ['reason' => $reason]);
            $this->auditLogger->record(AuditAction::CancelledWarehouseTask, actor: $actor, target: $locked,
                description: "Cancelled warehouse task {$locked->task_number}: {$reason}");

            return $locked;
        });
    }

    public function raiseException(WarehouseTask $task, string $type, string $details, User $actor, string $priority = 'normal'): WarehouseException
    {
        $exception = WarehouseException::create([
            'exception_number' => 'EXC-'.now()->format('Ymd').'-'.Str::ulid(),
            'warehouse_task_id' => $task->id,
            'exception_type' => $type,
            'priority' => $priority,
            'status' => 'open',
            'storage_location_id' => $task->source_location_id,
            'item_id' => $task->item_id,
            'details' => $details,
            'raised_by_id' => $actor->id,
        ]);
        $this->auditLogger->record(AuditAction::RaisedWarehouseException, actor: $actor, target: $exception,
            description: "Raised {$type} exception {$exception->exception_number} for task {$task->task_number}");

        return $exception;
    }

    public function resolveException(WarehouseException $exception, string $resolution, User $actor): WarehouseException
    {
        return DB::transaction(function () use ($exception, $resolution, $actor): WarehouseException {
            $locked = WarehouseException::lockForUpdate()->findOrFail($exception->id);
            if ($locked->status === 'resolved') {
                throw new DomainException('This exception is already resolved.');
            }
            $locked->status = 'resolved';
            $locked->resolution = $resolution;
            $locked->resolved_by_id = $actor->id;
            $locked->resolved_at = now();
            $locked->save();
            $this->auditLogger->record(AuditAction::ResolvedWarehouseException, actor: $actor, target: $locked,
                description: "Resolved warehouse exception {$locked->exception_number}", newValues: ['resolution' => $resolution]);

            return $locked;
        });
    }

    private function completeStockMovement(WarehouseTask $task, int $quantity, User $actor): void
    {
        if ($task->task_type === WarehouseTaskType::Pick && $task->reference instanceof MaterialRequisitionLine) {
            $line = MaterialRequisitionLine::lockForUpdate()->findOrFail($task->reference->id);
            $reserved = min($quantity, $line->reserved_quantity);
            if ($reserved > 0) {
                $this->inventory->releaseReservation($task->item_id, $task->source_location_id, $task->item_batch_id, $reserved);
                $line->reserved_quantity -= $reserved;
                $line->line_status = 'picking';
                $line->save();
            }
        }

        $movements = $this->inventory->recordMovement([
            'item_id' => $task->item_id,
            'item_batch_id' => $task->item_batch_id,
            'movement_type' => MovementType::Transfer,
            'quantity' => $quantity,
            'from_location_id' => $task->source_location_id,
            'to_location_id' => $task->destination_location_id,
            'remarks' => "Warehouse task {$task->task_number}",
        ], $actor->id, $task);
        foreach ($movements as $movement) {
            $this->ledgerIntegrity->sealMovement($movement);
        }
        $this->moveSerializedUnit($task);
    }

    private function assertTaskDestination(WarehouseTaskType $type, StorageLocation $destination, InventoryItem $item, int $quantity): void
    {
        if ($type === WarehouseTaskType::Pick) {
            if (! $destination->isOperational() || ! $destination->is_dispatch_staging) {
                throw new DomainException('Pick tasks must end in an active dispatch staging location.');
            }

            if ($destination->capacity !== null && ($destination->totalQuantity() + $quantity) > $destination->capacity) {
                throw new DomainException("Location {$destination->code} does not have enough available capacity.");
            }

            return;
        }

        $this->compatibilityService->assertCompatible($destination, $item, $quantity);
    }

    private function createDependentTask(WarehouseTask $task, User $actor): void
    {
        if (! ($task->reference instanceof MaterialRequisitionLine)) {
            return;
        }

        $nextType = match ($task->task_type) {
            WarehouseTaskType::Pick => WarehouseTaskType::Pack,
            WarehouseTaskType::Pack => WarehouseTaskType::Dispatch,
            default => null,
        };
        if ($nextType === null) {
            return;
        }

        $this->create([
            'task_type' => $nextType,
            'priority' => $task->priority,
            'source_location_id' => $task->destination_location_id,
            'destination_location_id' => $task->destination_location_id,
            'item_id' => $task->item_id,
            'item_batch_id' => $task->item_batch_id,
            'requested_quantity' => $task->completed_quantity,
            'assigned_to_id' => null,
            'idempotency_key' => "{$nextType->value}-for-{$task->id}",
            'notes' => $nextType === WarehouseTaskType::Pack
                ? 'Independently verify the picked item, lot, and quantity before dispatch.'
                : 'Perform the final source and product scans before department handover.',
        ], $actor, $task->reference);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{0: bool, 1: string, 2: 'proceed'|'identify'|'reject'}
     */
    private function validateScan(WarehouseTask $task, string $expected, array $parsed): array
    {
        if ($parsed['resolved_type'] === null) {
            return [false, "Unknown barcode or identifier: '{$parsed['raw']}'. Please verify the barcode label.", 'reject'];
        }

        // Scanning the task's own label identifies the job in hand; it is neither a
        // step forward nor a wrong scan, so it answers with the pending prompt.
        if ($parsed['resolved_type'] === 'task') {
            if ((int) $parsed['resolved_id'] === (int) $task->id) {
                return [false, "Task {$task->task_number} identified. {$this->scanPrompt($task, $expected)}", 'identify'];
            }

            return [false, "Scanned barcode for task {$parsed['normalized']}, but the active task is {$task->task_number}.", 'reject'];
        }

        if ($expected === 'source') {
            if ($parsed['resolved_type'] === 'location') {
                if ((int) $parsed['resolved_id'] === (int) $task->source_location_id) {
                    return [true, "Source location {$task->sourceLocation?->code} confirmed.", 'proceed'];
                }
                if ((int) $parsed['resolved_id'] === (int) $task->destination_location_id) {
                    return [false, "Scanned destination location ({$task->destinationLocation?->code}), but Step 1 requires scanning the source location ({$task->sourceLocation?->code}) first.", 'reject'];
                }
                $otherLoc = StorageLocation::find($parsed['resolved_id']);
                $otherCode = $otherLoc?->code ?? $parsed['normalized'];

                return [false, "Scanned location '{$otherCode}', but expected source location '{$task->sourceLocation?->code}'.", 'reject'];
            }

            if (in_array($parsed['resolved_type'], ['item', 'batch'], true)) {
                return [false, "Scanned product barcode ({$parsed['normalized']}). Step 1 requires scanning the source location ({$task->sourceLocation?->code}) to confirm you are at the correct shelf before picking.", 'reject'];
            }

            return [false, "Wrong source location scanned. Expected: {$task->sourceLocation?->code}.", 'reject'];
        }

        if ($expected === 'destination') {
            if ($parsed['resolved_type'] === 'location') {
                if ((int) $parsed['resolved_id'] === (int) $task->destination_location_id) {
                    return [true, "Destination location {$task->destinationLocation?->code} confirmed.", 'proceed'];
                }
                if ((int) $parsed['resolved_id'] === (int) $task->source_location_id) {
                    return [false, "Scanned source location ({$task->sourceLocation?->code}), but Step 3 requires scanning the destination location ({$task->destinationLocation?->code}).", 'reject'];
                }
                $otherLoc = StorageLocation::find($parsed['resolved_id']);
                $otherCode = $otherLoc?->code ?? $parsed['normalized'];

                return [false, "Scanned location '{$otherCode}', but expected destination location '{$task->destinationLocation?->code}'.", 'reject'];
            }

            if (in_array($parsed['resolved_type'], ['item', 'batch'], true)) {
                return [false, "Scanned product barcode ({$parsed['normalized']}). Step 3 requires scanning the destination location ({$task->destinationLocation?->code}) to confirm putaway.", 'reject'];
            }

            return [false, "Wrong destination location scanned. Expected: {$task->destinationLocation?->code}.", 'reject'];
        }

        if ($expected === 'item') {
            if ($parsed['resolved_type'] === 'location') {
                $scannedLoc = StorageLocation::find($parsed['resolved_id']);
                $locCode = $scannedLoc?->code ?? $parsed['normalized'];

                return [false, "Scanned location '{$locCode}'. Step 2 requires scanning the product or GS1 barcode ({$task->item?->sku}).", 'reject'];
            }

            $validItem = ($parsed['resolved_type'] === 'item' && (int) $parsed['resolved_id'] === (int) $task->item_id)
                || ($parsed['resolved_type'] === 'batch' && (int) $task->item_batch_id === (int) $parsed['resolved_id']);
            if (! $validItem) {
                return [false, "Scanned product ({$parsed['normalized']}) does not match task item ({$task->item?->sku}).", 'reject'];
            }
            if ($task->batch && $parsed['batch'] && ! in_array($parsed['batch'], [$task->batch->batch_number, $task->batch->lot_number], true)) {
                return [false, "The scanned GS1 lot '{$parsed['batch']}' does not match the allocated lot '{$task->batch->batch_number}'.", 'reject'];
            }
            if ($task->batch?->expiry_date && $parsed['expiry'] && $parsed['expiry'] !== $task->batch->expiry_date->toDateString()) {
                return [false, "The scanned GS1 expiry date '{$parsed['expiry']}' does not match the allocated lot expiry '{$task->batch->expiry_date->toDateString()}'.", 'reject'];
            }
            if ($task->item->is_serial_tracked) {
                if (! $parsed['serial']) {
                    return [false, 'A GS1 serial number is required for this serialized item.', 'reject'];
                }
                $serialMatches = InventorySerial::query()
                    ->where('item_id', $task->item_id)
                    ->where('serial_number', $parsed['serial'])
                    ->when($task->item_batch_id, fn ($query) => $query->where('item_batch_id', $task->item_batch_id))
                    ->exists();
                if (! $serialMatches) {
                    return [false, "The scanned serial number '{$parsed['serial']}' is not registered for this item and lot.", 'reject'];
                }
            }

            return [true, "Product and applicable lot confirmed ({$task->item?->sku}).", 'proceed'];
        }

        return [false, 'All required scans are already complete.', 'reject'];
    }

    /** Operator-facing instruction for the scan step the task is waiting on. */
    private function scanPrompt(WarehouseTask $task, string $expected): string
    {
        return match ($expected) {
            'source' => trim('Now scan the source location '.($task->sourceLocation?->code ?? '')).'.',
            'destination' => trim('Now scan the destination location '.($task->destinationLocation?->code ?? '')).'.',
            'item' => trim('Now scan the product '.($task->item?->sku ?? '')).'.',
            default => 'All required scans are already done; post the quantity to complete.',
        };
    }

    private function expectedScan(WarehouseTask $task, int $accepted): string
    {
        return $this->scanSequence($task)[$accepted] ?? 'complete';
    }

    private function requiredScanCount(WarehouseTask $task): int
    {
        return count($this->scanSequence($task));
    }

    /** @return array<int, string> */
    private function scanSequence(WarehouseTask $task): array
    {
        return match ($task->task_type) {
            WarehouseTaskType::PutAway, WarehouseTaskType::Replenishment,
            WarehouseTaskType::Pick, WarehouseTaskType::Move => ['source', 'item', 'destination'],
            WarehouseTaskType::Pack => ['item'],
            WarehouseTaskType::Dispatch => ['source', 'item'],
            default => [],
        };
    }

    private function completeDispatch(WarehouseTask $task, int $quantity, User $actor): void
    {
        if (! ($task->reference instanceof MaterialRequisitionLine)) {
            throw new DomainException('Dispatch task is not linked to a requisition line.');
        }

        $line = MaterialRequisitionLine::lockForUpdate()->with('requisition')->findOrFail($task->reference->id);
        if ($quantity > $line->unfulfilledQuantity()) {
            throw new DomainException('Dispatch quantity exceeds the unfulfilled requisition quantity.');
        }

        $movements = $this->inventory->recordMovement([
            'item_id' => $task->item_id,
            'item_batch_id' => $task->item_batch_id,
            'movement_type' => MovementType::Issuance,
            'quantity' => $quantity,
            'from_location_id' => $task->source_location_id,
            'remarks' => "Department dispatch for {$line->requisition->requisition_number} via {$task->task_number}",
        ], $actor->id, $line->requisition);
        foreach ($movements as $movement) {
            $this->ledgerIntegrity->sealMovement($movement);
        }
        $this->moveSerializedUnit($task, 'issued');

        $line->issued_quantity += $quantity;
        $line->line_status = $line->issued_quantity >= $line->requested_quantity ? 'issued' : 'partially_issued';
        $line->save();

        $requisition = MaterialRequisition::lockForUpdate()->findOrFail($line->material_requisition_id);
        $allIssued = $requisition->lines()->get()->every(fn (MaterialRequisitionLine $candidate) => $candidate->issued_quantity >= $candidate->requested_quantity);
        $requisition->status = $allIssued ? 'issued' : 'picking';
        $requisition->issued_by_id = $actor->id;
        $requisition->issued_at = now();
        $requisition->save();
    }

    private function moveSerializedUnit(WarehouseTask $task, string $status = 'available'): void
    {
        if (! $task->item->is_serial_tracked) {
            return;
        }

        $serial = $task->scans()
            ->where('outcome', 'accepted')
            ->get()
            ->pluck('metadata')
            ->first(fn (?array $metadata) => filled($metadata['serial'] ?? null));

        if (! $serial) {
            throw new DomainException('The serialized unit scan is missing.');
        }

        InventorySerial::query()
            ->where('item_id', $task->item_id)
            ->where('serial_number', $serial['serial'])
            ->update([
                'storage_location_id' => $task->task_type === WarehouseTaskType::Dispatch ? null : $task->destination_location_id,
                'status' => $status,
            ]);
    }

    /** @param array<string, mixed> $parsed */
    private function exceptionTypeFor(string $expected, array $parsed): string
    {
        if ($parsed['resolved_type'] === null) {
            return 'unknown_barcode';
        }

        return match ($expected) {
            'source', 'destination' => 'wrong_location',
            default => 'wrong_item_or_lot',
        };
    }

    private function assertOperator(WarehouseTask $task, User $actor): void
    {
        if (! $actor->hasPermission(Permission::ExecuteWarehouseTasks)) {
            throw new DomainException('You are not authorized to execute warehouse tasks.');
        }
        if ($task->assigned_to_id !== null && $task->assigned_to_id !== $actor->id) {
            throw new DomainException('This task is assigned to another operator.');
        }
    }

    private function identifier(WarehouseTaskType $type): string
    {
        $prefix = match ($type) {
            WarehouseTaskType::PutAway => 'PUT',
            WarehouseTaskType::Replenishment => 'REP',
            WarehouseTaskType::Pick => 'PICK',
            WarehouseTaskType::Pack => 'PACK',
            WarehouseTaskType::Stage => 'STG',
            WarehouseTaskType::Dispatch => 'DSP',
            WarehouseTaskType::Move => 'MOV',
            WarehouseTaskType::TransferDispatch => 'TRD',
            WarehouseTaskType::TransferReceipt => 'TRR',
            WarehouseTaskType::CycleCount => 'CNT',
            WarehouseTaskType::ExceptionResolution => 'EXR',
            WarehouseTaskType::RecallRetrieval => 'RCL',
        };

        return $prefix.'-'.now()->format('Ymd').'-'.Str::ulid();
    }

    /** @param array<string, mixed> $metadata */
    private function event(WarehouseTask $task, string $eventType, ?WarehouseTaskStatus $from, WarehouseTaskStatus $to, ?User $actor, array $metadata = []): void
    {
        WarehouseTaskEvent::create([
            'warehouse_task_id' => $task->id,
            'event_type' => $eventType,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_id' => $actor?->id,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
