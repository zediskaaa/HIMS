<x-app-layout>
    @php
        $isClosed = in_array($warehouseTask->status, [
            \App\Enums\WarehouseTaskStatus::Completed,
            \App\Enums\WarehouseTaskStatus::Cancelled,
        ], true);
        $canStart = in_array($warehouseTask->status, [
            \App\Enums\WarehouseTaskStatus::Ready,
            \App\Enums\WarehouseTaskStatus::Assigned,
            \App\Enums\WarehouseTaskStatus::PartiallyCompleted,
        ], true);
        $isInProgress = $warehouseTask->status === \App\Enums\WarehouseTaskStatus::InProgress;
        $acceptedScans = $warehouseTask->scans->where('outcome', 'accepted')->count();
        $scanPrompts = match($warehouseTask->task_type) {
            \App\Enums\WarehouseTaskType::Pack => ['Scan product / GS1 code'],
            \App\Enums\WarehouseTaskType::Dispatch => ['Scan dispatch source', 'Scan product / GS1 code'],
            default => ['Scan source location', 'Scan product / GS1 code', 'Scan destination location'],
        };
        $requiredScanCount = count($scanPrompts);
        $requiredScansComplete = $acceptedScans >= $requiredScanCount;
        $prompt = $scanPrompts[$acceptedScans] ?? 'Required scans complete';
        $sourceVal = $warehouseTask->sourceLocation?->barcode_value ?: ($warehouseTask->sourceLocation?->code ?? '');
        $itemVal = $warehouseTask->item?->barcode_value ?: ($warehouseTask->item?->sku ?? '');
        $destVal = $warehouseTask->destinationLocation?->barcode_value ?: ($warehouseTask->destinationLocation?->code ?? '');
        $targetCode = $isInProgress ? match($acceptedScans) {
            0 => $sourceVal,
            1 => $itemVal,
            2 => $destVal,
            default => '',
        } : '';
        $targetQr = $targetCode
            ? app(\App\Services\Warehouse\WarehouseLabelService::class)->qrDataUri($targetCode)
            : null;
        $openExceptions = $warehouseTask->exceptions->where('status', '!=', 'resolved')->count();
        $showSplitWorkspace = $isInProgress
            && auth()->user()?->can(\App\Enums\Permission::ExecuteWarehouseTasks->value);
        $errorModal = $errors->has('reason')
            ? 'cancel-warehouse-task'
            : ($errors->has('assigned_to_id') || $errors->has('assignment') ? 'change-task-assignment' : null);
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <a href="{{ route('inventory.warehouse-tasks.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:text-primary-800 dark:text-primary-300 dark:hover:text-primary-200">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" />
                    Task registry
                </a>
                <div class="mt-1 flex min-w-0 flex-wrap items-center gap-2.5">
                    <h2 class="truncate font-mono text-2xl font-bold tracking-tight text-neutral-950 dark:text-white" title="{{ $warehouseTask->task_number }}">{{ $warehouseTask->task_number }}</h2>
                    <x-ui.badge :status="$warehouseTask->status->value">{{ $warehouseTask->status->label() }}</x-ui.badge>
                </div>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ $warehouseTask->task_type->label() }} warehouse task · {{ $warehouseTask->item?->name ?? 'Item not recorded' }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @can(\App\Enums\Permission::ManageWarehouseTasks->value)
                    @if (! $isClosed)
                        <x-ui.button type="button" variant="secondary" size="sm" icon="user-circle" x-on:click="$dispatch('open-modal', 'change-task-assignment')">Change assignment</x-ui.button>
                    @endif
                @endcan
                @can(\App\Enums\Permission::PrintWarehouseLabels->value)
                    <x-ui.button type="button" variant="secondary" size="sm" icon="printer" x-on:click="$dispatch('open-modal', 'generate-task-label')">Generate label</x-ui.button>
                @endcan
                @can(\App\Enums\Permission::ManageWarehouseTasks->value)
                    @if (! $isClosed)
                        <x-ui.button type="button" variant="danger" size="sm" icon="trash" x-on:click="$dispatch('open-modal', 'cancel-warehouse-task')">Cancel task</x-ui.button>
                    @endif
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="space-y-4" x-data @if($errorModal) x-init='$nextTick(() => $dispatch("open-modal", @js($errorModal)))' @endif>
        @if (session('notice'))
            <x-ui.alert variant="info" title="Task identified" :message="session('notice')" />
        @endif
        @if ($errors->any())
            <x-ui.alert variant="danger" title="Action blocked">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900" aria-labelledby="task-summary-title">
            <div class="flex flex-col gap-3 border-b border-neutral-200 px-4 py-3.5 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <h3 id="task-summary-title" class="text-sm font-bold text-neutral-950 dark:text-white">Task summary</h3>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Movement details and current operational ownership.</p>
                </div>
                <div class="flex items-baseline gap-2 text-sm">
                    <span class="text-neutral-500 dark:text-neutral-400">Progress</span>
                    <strong class="tabular-nums text-neutral-950 dark:text-white">{{ number_format($warehouseTask->completed_quantity) }} / {{ number_format($warehouseTask->requested_quantity) }}</strong>
                    <span class="text-neutral-500 dark:text-neutral-400">{{ $warehouseTask->item?->unit }}</span>
                </div>
            </div>

            <dl class="grid grid-cols-1 divide-y divide-neutral-100 dark:divide-neutral-800 sm:grid-cols-2 sm:divide-y-0 lg:grid-cols-4">
                <div class="min-w-0 px-4 py-3 sm:px-5">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Item / SKU</dt>
                    <dd class="mt-1 truncate text-sm font-semibold text-neutral-950 dark:text-white" title="{{ $warehouseTask->item?->name ?? 'Not recorded' }}">{{ $warehouseTask->item?->name ?? 'Not recorded' }}</dd>
                    <dd class="mt-0.5 truncate font-mono text-xs text-neutral-500 dark:text-neutral-400" title="{{ $warehouseTask->item?->sku }}">{{ $warehouseTask->item?->sku ?: 'No SKU' }}</dd>
                </div>
                <div class="min-w-0 border-neutral-100 px-4 py-3 dark:border-neutral-800 sm:border-l sm:px-5">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Source</dt>
                    <dd class="mt-1 break-words text-sm font-semibold text-neutral-950 dark:text-white">{{ $warehouseTask->sourceLocation?->fullPath() ?? 'Not recorded' }}</dd>
                    <dd class="mt-0.5 font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ $warehouseTask->sourceLocation?->code ?: 'No location code' }}</dd>
                </div>
                <div class="min-w-0 border-neutral-100 px-4 py-3 dark:border-neutral-800 lg:border-l lg:px-5">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Destination</dt>
                    <dd class="mt-1 break-words text-sm font-semibold text-neutral-950 dark:text-white">{{ $warehouseTask->destinationLocation?->fullPath() ?? 'Not recorded' }}</dd>
                    <dd class="mt-0.5 font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ $warehouseTask->destinationLocation?->code ?: 'No location code' }}</dd>
                </div>
                <div class="min-w-0 border-neutral-100 px-4 py-3 dark:border-neutral-800 sm:border-l sm:px-5">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Lot / Assigned operator</dt>
                    <dd class="mt-1 truncate font-mono text-sm font-semibold text-neutral-950 dark:text-white" title="{{ $warehouseTask->batch?->batch_number ?? 'Not applicable' }}">{{ $warehouseTask->batch?->batch_number ?? 'Not applicable' }}</dd>
                    <dd class="mt-0.5 truncate text-xs text-neutral-500 dark:text-neutral-400" title="{{ $warehouseTask->assignedTo?->name ?? 'Unassigned' }}">{{ $warehouseTask->assignedTo?->name ?? 'Unassigned' }}</dd>
                </div>
            </dl>

            @if ($warehouseTask->recommendation_reason)
                <details class="group border-t border-neutral-200 bg-primary-50/50 px-4 py-2.5 dark:border-neutral-800 dark:bg-primary-950/20 sm:px-5">
                    <summary class="flex cursor-pointer list-none items-center gap-2 text-xs font-semibold text-primary-800 marker:hidden dark:text-primary-300">
                        <x-ui.icon name="information-circle" class="h-4 w-4 shrink-0" />
                        Why this destination?
                        <x-ui.icon name="chevron-down" class="ml-auto h-3.5 w-3.5 transition-transform group-open:rotate-180" />
                    </summary>
                    <p class="mt-2 pl-6 text-xs leading-5 text-primary-900 dark:text-primary-200">{{ $warehouseTask->recommendation_reason }}</p>
                </details>
            @endif
        </section>

        <div @class([
            'space-y-4',
            'xl:grid xl:grid-cols-2 xl:items-start xl:gap-4 xl:space-y-0' => $showSplitWorkspace,
        ])>
        @can(\App\Enums\Permission::ExecuteWarehouseTasks->value)
            @if ($canStart)
                <section class="flex flex-col gap-3 rounded-xl border border-primary-200 bg-primary-50/70 p-4 dark:border-primary-900/70 dark:bg-primary-950/30 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="begin-work-title">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-900/70 dark:text-primary-300"><x-ui.icon name="clipboard-document-check" class="h-4 w-4" /></span>
                        <div class="min-w-0">
                            <h3 id="begin-work-title" class="text-sm font-bold text-primary-950 dark:text-primary-100">Ready for physical handling</h3>
                            <p class="mt-0.5 text-xs text-primary-800 dark:text-primary-300">Start this task to activate the required scan sequence.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('inventory.warehouse-tasks.start', $warehouseTask) }}" data-confirm-title="Start warehouse task" data-confirm-message="Are you sure you want to start this warehouse task and begin physical handling?" data-confirm-label="Start Task">
                        @csrf
                        <x-ui.button type="submit" size="sm" data-loading-text="Starting task...">Start task</x-ui.button>
                    </form>
                </section>
            @elseif ($isInProgress)
                <section class="min-w-0 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900" aria-labelledby="scan-verification-title">
                    <header class="flex flex-col gap-3 border-b border-neutral-200 px-4 py-3.5 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div>
                            <h3 id="scan-verification-title" class="text-sm font-bold text-neutral-950 dark:text-white">Scan verification</h3>
                            <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $prompt }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold tabular-nums text-neutral-700 dark:text-neutral-200">Step {{ min($acceptedScans + 1, $requiredScanCount) }} of {{ $requiredScanCount }}</span>
                            @if($targetQr)
                                <x-ui.button type="button" variant="secondary" size="sm" icon="qr-code" x-on:click="$dispatch('open-modal', 'target-task-code')">Target code</x-ui.button>
                            @endif
                        </div>
                    </header>

                    <div class="space-y-4 p-4 sm:p-5">
                        <div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Expected identifier</span>
                                    @if($targetCode)
                                        <span class="break-all font-mono text-sm font-bold text-primary-800 dark:text-primary-300">{{ $targetCode }}</span>
                                    @else
                                        <span class="text-sm font-semibold text-success-700 dark:text-emerald-300">All required scans verified</span>
                                    @endif
                                </div>

                                <form method="POST" action="{{ route('inventory.warehouse-tasks.scan', $warehouseTask) }}" class="mt-2 flex flex-col gap-2 2xl:flex-row">
                                    @csrf
                                    <input type="text" id="task_scan_input_{{ $warehouseTask->id }}" name="scan_value" required autofocus autocomplete="off" placeholder="{{ $targetCode ? 'Scan or enter '.$targetCode : 'Required scans complete' }}" class="min-h-10 min-w-0 flex-1 rounded-lg border-neutral-300 font-mono text-sm shadow-xs focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500" aria-label="Warehouse scan value" @disabled(! $targetCode)>
                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                                    <div class="flex flex-wrap gap-2 sm:flex-nowrap">
                                        <x-ui.camera-scanner
                                            id="camera-scanner-task-{{ $warehouseTask->id }}"
                                            target-input-id="task_scan_input_{{ $warehouseTask->id }}"
                                            button-text="Scan with Camera"
                                            button-variant="secondary"
                                            :auto-submit="true"
                                            title="Verify Task Step with Camera"
                                            hint="{{ $prompt }}. Aim camera at code {{ $targetCode }}."
                                            :show-trigger="(bool) $targetCode"
                                        />
                                        <x-ui.button type="submit" size="sm" data-loading-text="Validating..." :disabled="! $targetCode">Validate scan</x-ui.button>
                                    </div>
                                </form>
                            </div>

                        </div>

                        <div class="border-t border-neutral-100 pt-3 text-xs text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                            <p>Identifiers remain text-safe so leading zeroes, separators, lots, and serials are preserved.</p>
                        </div>
                    </div>

                    <footer class="border-t border-neutral-200 bg-neutral-50/70 px-4 py-3.5 dark:border-neutral-800 dark:bg-neutral-950/30 sm:px-5">
                        <form method="POST" action="{{ route('inventory.warehouse-tasks.complete', $warehouseTask) }}" class="flex flex-col gap-3 2xl:flex-row 2xl:items-end 2xl:justify-between" data-confirm-title="Complete warehouse task" data-confirm-message="Are you sure you want to mark this task as completed? Stock movements will be posted." data-confirm-label="Complete Task">
                            @csrf
                            <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-end">
                                <div>
                                    <label for="complete_quantity_{{ $warehouseTask->id }}" class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Complete quantity</label>
                                    <input id="complete_quantity_{{ $warehouseTask->id }}" type="number" name="quantity" min="1" max="{{ $warehouseTask->remainingQuantity() }}" step="1" required value="{{ $warehouseTask->remainingQuantity() }}" class="mt-1 min-h-10 w-full rounded-lg border-neutral-300 text-sm tabular-nums shadow-xs focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 sm:w-40" @disabled(! $requiredScansComplete)>
                                </div>
                                <p class="pb-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $requiredScansComplete ? 'All scan checks passed. Stock can now be posted.' : 'Complete all required scans before posting stock.' }}</p>
                            </div>
                            <x-ui.button type="submit" data-loading-text="Posting stock..." :disabled="! $requiredScansComplete">Post and complete</x-ui.button>
                        </form>
                    </footer>
                </section>
            @endif
        @endcan

        <section class="min-w-0 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900" x-data="{ activeTab: '{{ $openExceptions > 0 ? 'exceptions' : 'history' }}' }" aria-labelledby="task-activity-title">
            <div class="flex flex-col gap-3 border-b border-neutral-200 px-4 py-3 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <h3 id="task-activity-title" class="text-sm font-bold text-neutral-950 dark:text-white">Task activity</h3>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Scan evidence and operational exceptions.</p>
                </div>
                <div class="inline-flex w-full rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800 sm:w-auto" role="tablist" aria-label="Task activity views">
                    <button type="button" role="tab" x-on:click="activeTab = 'history'" :aria-selected="activeTab === 'history'" :class="activeTab === 'history' ? 'bg-white text-neutral-950 shadow-sm dark:bg-neutral-700 dark:text-white' : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-300 dark:hover:text-white'" class="min-h-8 flex-1 rounded-md px-3 py-1.5 text-xs font-semibold transition sm:flex-none">Scan History ({{ $warehouseTask->scans->count() }})</button>
                    <button type="button" role="tab" x-on:click="activeTab = 'exceptions'" :aria-selected="activeTab === 'exceptions'" :class="activeTab === 'exceptions' ? 'bg-white text-neutral-950 shadow-sm dark:bg-neutral-700 dark:text-white' : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-300 dark:hover:text-white'" class="min-h-8 flex-1 rounded-md px-3 py-1.5 text-xs font-semibold transition sm:flex-none">Exceptions ({{ $warehouseTask->exceptions->count() }})</button>
                </div>
            </div>

            <div x-show="activeTab === 'history'" role="tabpanel" class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($warehouseTask->scans as $scan)
                    <div class="grid gap-1.5 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-4 sm:px-5">
                        <div class="min-w-0">
                            <p class="truncate font-mono text-sm font-semibold text-neutral-900 dark:text-neutral-100" title="{{ $scan->normalized_value }}">{{ $scan->normalized_value }}</p>
                            <p class="mt-0.5 text-xs leading-5 text-neutral-500 dark:text-neutral-400">{{ $scan->message }}</p>
                        </div>
                        <div class="flex items-center gap-3 sm:justify-end">
                            <time class="whitespace-nowrap text-xs tabular-nums text-neutral-500 dark:text-neutral-400">{{ $scan->created_at?->format('M j, Y · g:i A') }}</time>
                            <x-ui.badge :status="$scan->outcome" :variant="$scan->outcome === 'identified' ? 'warning' : null">{{ ucfirst($scan->outcome) }}</x-ui.badge>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">No scans recorded for this task.</p>
                @endforelse
            </div>

            <div x-show="activeTab === 'exceptions'" x-cloak role="tabpanel" class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($warehouseTask->exceptions as $exception)
                    <article class="px-4 py-3 sm:px-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-sm font-bold text-neutral-950 dark:text-white">{{ str($exception->exception_type)->replace('_', ' ')->title() }}</h4>
                                    <span class="font-mono text-[11px] text-neutral-500 dark:text-neutral-400">{{ $exception->exception_number }}</span>
                                </div>
                                <p class="mt-1 text-xs leading-5 text-neutral-600 dark:text-neutral-300">{{ $exception->details }}</p>
                            </div>
                            <x-ui.badge :status="$exception->status">{{ str($exception->status)->headline() }}</x-ui.badge>
                        </div>
                        @can(\App\Enums\Permission::ResolveWarehouseExceptions->value)
                            @if($exception->status !== 'resolved')
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-xs font-semibold text-primary-700 hover:text-primary-800 dark:text-primary-300 dark:hover:text-primary-200">Document investigation and resolve</summary>
                                    <form method="POST" action="{{ route('inventory.warehouse-exceptions.resolve', $exception) }}" class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end" data-confirm-title="Resolve warehouse exception" data-confirm-message="Are you sure you want to mark this exception as resolved with your documented investigation?" data-confirm-label="Resolve Exception">
                                        @csrf
                                        <div>
                                            <label for="resolution_{{ $exception->id }}" class="sr-only">Investigation and resolution</label>
                                            <textarea id="resolution_{{ $exception->id }}" name="resolution" required maxlength="2000" rows="2" class="w-full rounded-lg border-neutral-300 text-sm shadow-xs focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100" placeholder="Investigation findings and resolution"></textarea>
                                        </div>
                                        <x-ui.button type="submit" size="sm" data-loading-text="Resolving...">Resolve</x-ui.button>
                                    </form>
                                </details>
                            @endif
                        @endcan
                    </article>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">No exceptions recorded for this task.</p>
                @endforelse
            </div>
        </section>
        </div>

        @can(\App\Enums\Permission::ManageWarehouseTasks->value)
            @if (! $isClosed)
                <x-ui.modal name="change-task-assignment" title="Change task assignment" maxWidth="md">
                    <form method="POST" action="{{ route('inventory.warehouse-tasks.assign', $warehouseTask) }}" class="space-y-5">
                        @csrf
                        <div class="rounded-lg bg-neutral-50 p-3 text-sm dark:bg-neutral-800/70">
                            <span class="text-neutral-500 dark:text-neutral-400">Currently assigned to</span>
                            <p class="mt-0.5 font-semibold text-neutral-950 dark:text-white">{{ $warehouseTask->assignedTo?->name ?? 'Unassigned' }}</p>
                        </div>
                        <div>
                            <label for="assigned_to_id" class="block text-sm font-semibold text-neutral-800 dark:text-neutral-200">Warehouse operator</label>
                            <select id="assigned_to_id" name="assigned_to_id" required class="mt-1.5 min-h-10 w-full rounded-lg border-neutral-300 py-2 pl-3 pr-10 text-sm shadow-xs focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                                <option value="">Select operator</option>
                                @foreach ($operators as $operator)
                                    <option value="{{ $operator->id }}" @selected($warehouseTask->assigned_to_id === $operator->id)>{{ $operator->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col-reverse gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800 sm:flex-row sm:justify-end">
                            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'change-task-assignment')">Close</x-ui.button>
                            <x-ui.button type="submit" data-loading-text="Assigning...">Save assignment</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>
            @endif
        @endcan

        @can(\App\Enums\Permission::PrintWarehouseLabels->value)
            <x-ui.modal name="generate-task-label" title="Generate internal task label" maxWidth="md">
                <form method="POST" action="{{ route('inventory.warehouse-tasks.label', $warehouseTask) }}" target="_blank" class="space-y-5">
                    @csrf
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800/70">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Task identifier</p>
                        <p class="mt-1 break-all font-mono text-base font-bold text-neutral-950 dark:text-white">{{ $warehouseTask->task_number }}</p>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ $warehouseTask->item?->name ?? 'Item not recorded' }}</p>
                    </div>
                    <div>
                        <label for="task_label_copies" class="block text-sm font-semibold text-neutral-800 dark:text-neutral-200">Number of labels</label>
                        <input id="task_label_copies" type="number" name="copies" min="1" max="20" value="1" required class="mt-1.5 min-h-10 w-full rounded-lg border-neutral-300 text-sm tabular-nums shadow-xs focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Generate between 1 and 20 printable copies.</p>
                    </div>
                    <div class="flex flex-col-reverse gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800 sm:flex-row sm:justify-end">
                        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'generate-task-label')">Close</x-ui.button>
                        <x-ui.button type="submit" icon="printer" data-loading-text="Generating...">Generate label</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        @endcan

        @if($targetQr)
            <x-ui.modal name="target-task-code" title="Target scan code" maxWidth="sm">
                <div class="text-center">
                    <p class="text-xs font-semibold text-neutral-500 dark:text-neutral-400">Step {{ min($acceptedScans + 1, $requiredScanCount) }} of {{ $requiredScanCount }} · {{ $prompt }}</p>
                    <div class="mt-4 flex justify-center">
                        <img src="{{ $targetQr }}" alt="QR code for expected identifier {{ $targetCode }}" class="h-56 w-56 rounded-xl border border-neutral-200 bg-white p-2 shadow-sm">
                    </div>
                    <p class="mt-3 break-all font-mono text-sm font-bold text-neutral-900 dark:text-neutral-100">{{ $targetCode }}</p>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ $warehouseTask->item?->name ?? $warehouseTask->destinationLocation?->fullPath() }}</p>
                    <x-ui.button type="button" variant="secondary" class="mt-5 w-full" x-on:click="$dispatch('close-modal', 'target-task-code')">Close</x-ui.button>
                </div>
            </x-ui.modal>
        @endif

        @can(\App\Enums\Permission::ManageWarehouseTasks->value)
            @if (! $isClosed)
                <x-ui.modal name="cancel-warehouse-task" title="Cancel warehouse task" maxWidth="md">
                    <form method="POST" action="{{ route('inventory.warehouse-tasks.cancel', $warehouseTask) }}" class="space-y-5" x-data='{ reason: @js(old("reason", "")) }'>
                        @csrf
                        <div class="flex items-start gap-3 rounded-lg border border-danger-200 bg-danger-50 p-3.5 text-danger-900 dark:border-danger-900/70 dark:bg-danger-950/40 dark:text-danger-200">
                            <x-ui.icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0" />
                            <div>
                                <p class="text-sm font-bold">This task will be closed without completing the stock movement.</p>
                                <p class="mt-1 text-xs leading-5">The cancellation and reason will remain in the operational record.</p>
                            </div>
                        </div>
                        <div>
                            <label for="task_cancel_reason" class="block text-sm font-semibold text-neutral-800 dark:text-neutral-200">Cancellation reason <span class="text-danger-600">*</span></label>
                            <textarea id="task_cancel_reason" name="reason" x-model="reason" required maxlength="1000" rows="3" class="mt-1.5 w-full rounded-lg border-neutral-300 text-sm shadow-xs focus:border-danger-500 focus:ring-danger-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100" placeholder="Explain why this task must be cancelled"></textarea>
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Required · maximum 1,000 characters</p>
                        </div>
                        <div class="flex flex-col-reverse gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800 sm:flex-row sm:justify-end">
                            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'cancel-warehouse-task')">Keep task</x-ui.button>
                            <x-ui.button type="submit" variant="danger" data-loading-text="Cancelling..." x-bind:disabled="reason.trim().length === 0">Confirm cancellation</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>
            @endif
        @endcan
    </div>
</x-app-layout>
