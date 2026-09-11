<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('inventory.warehouse-tasks.index') }}" class="text-sm font-semibold text-primary-700 hover:underline">← Task registry</a>
                <h2 class="mt-1 font-mono text-2xl font-bold text-neutral-900">{{ $warehouseTask->task_number }}</h2>
                <p class="text-sm text-neutral-600">{{ $warehouseTask->task_type->label() }} · {{ $warehouseTask->status->label() }}</p>
            </div>
            <x-ui.badge :status="$warehouseTask->status->value">{{ $warehouseTask->status->label() }}</x-ui.badge>
        </div>
    </x-slot>

    <div class="py-6"><div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
        @if ($errors->any())<x-ui.alert variant="danger" title="Action blocked"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif

        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.card title="Task details" class="lg:col-span-2">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs uppercase text-neutral-500">Item</dt><dd class="font-semibold">{{ $warehouseTask->item?->name ?? '—' }}</dd><dd class="font-mono text-xs">{{ $warehouseTask->item?->sku }}</dd></div>
                    <div><dt class="text-xs uppercase text-neutral-500">Lot / batch</dt><dd class="font-mono">{{ $warehouseTask->batch?->batch_number ?? 'Not applicable' }}</dd></div>
                    <div><dt class="text-xs uppercase text-neutral-500">Source</dt><dd>{{ $warehouseTask->sourceLocation?->fullPath() ?? '—' }}</dd><dd class="font-mono text-xs">{{ $warehouseTask->sourceLocation?->code }}</dd></div>
                    <div><dt class="text-xs uppercase text-neutral-500">Destination</dt><dd>{{ $warehouseTask->destinationLocation?->fullPath() ?? '—' }}</dd><dd class="font-mono text-xs">{{ $warehouseTask->destinationLocation?->code }}</dd></div>
                    <div><dt class="text-xs uppercase text-neutral-500">Progress</dt><dd class="font-semibold">{{ $warehouseTask->completed_quantity }} / {{ $warehouseTask->requested_quantity }} {{ $warehouseTask->item?->unit }}</dd></div>
                    <div><dt class="text-xs uppercase text-neutral-500">Assigned operator</dt><dd>{{ $warehouseTask->assignedTo?->name ?? 'Unassigned' }}</dd></div>
                </dl>
                @if ($warehouseTask->recommendation_reason)<p class="mt-4 rounded-lg bg-primary-50 p-3 text-sm text-primary-900"><strong>Why this destination:</strong> {{ $warehouseTask->recommendation_reason }}</p>@endif
            </x-ui.card>

            <div class="space-y-4">
                @can(\App\Enums\Permission::ManageWarehouseTasks->value)
                    @if (! in_array($warehouseTask->status, [\App\Enums\WarehouseTaskStatus::Completed, \App\Enums\WarehouseTaskStatus::Cancelled], true))
                        <x-ui.card title="Assignment">
                            <form method="POST" action="{{ route('inventory.warehouse-tasks.assign', $warehouseTask) }}" class="space-y-3">@csrf
                                <select name="assigned_to_id" required class="w-full rounded-lg border-neutral-300"><option value="">Select operator</option>@foreach ($operators as $operator)<option value="{{ $operator->id }}" @selected($warehouseTask->assigned_to_id === $operator->id)>{{ $operator->name }}</option>@endforeach</select>
                                <x-ui.button type="submit" size="sm">Assign</x-ui.button>
                            </form>
                        </x-ui.card>
                    @endif
                @endcan
                @can(\App\Enums\Permission::PrintWarehouseLabels->value)
                    <x-ui.card title="Internal task label"><form method="POST" action="{{ route('inventory.warehouse-tasks.label', $warehouseTask) }}" target="_blank" class="flex gap-2">@csrf<input type="number" name="copies" min="1" max="20" value="1" class="w-20 rounded-lg border-neutral-300"><x-ui.button type="submit" size="sm" variant="secondary">Generate</x-ui.button></form></x-ui.card>
                @endcan
            </div>
        </div>

        @can(\App\Enums\Permission::ExecuteWarehouseTasks->value)
            @if (in_array($warehouseTask->status, [\App\Enums\WarehouseTaskStatus::Ready, \App\Enums\WarehouseTaskStatus::Assigned, \App\Enums\WarehouseTaskStatus::PartiallyCompleted], true))
                <x-ui.card title="Begin physical work"><form method="POST" action="{{ route('inventory.warehouse-tasks.start', $warehouseTask) }}">@csrf<x-ui.button type="submit">Start task</x-ui.button></form></x-ui.card>
            @elseif ($warehouseTask->status === \App\Enums\WarehouseTaskStatus::InProgress)
                @php($acceptedScans = $warehouseTask->scans->where('outcome', 'accepted')->count())
                @php($scanPrompts = match($warehouseTask->task_type) {
                    \App\Enums\WarehouseTaskType::Pack => ['Scan product / GS1 code'],
                    \App\Enums\WarehouseTaskType::Dispatch => ['Scan dispatch source', 'Scan product / GS1 code'],
                    default => ['Scan source location', 'Scan product / GS1 code', 'Scan destination location'],
                })
                @php($prompt = $scanPrompts[$acceptedScans] ?? 'Required scans complete')
                <x-ui.card title="Scan verification" :subtitle="$prompt">
                    <form method="POST" action="{{ route('inventory.warehouse-tasks.scan', $warehouseTask) }}" class="flex flex-col gap-3 sm:flex-row">@csrf
                        <input type="text" id="task_scan_input_{{ $warehouseTask->id }}" name="scan_value" required autofocus autocomplete="off" placeholder="Scan or type the identifier" class="min-w-0 flex-1 rounded-lg border-neutral-300 font-mono" aria-label="Warehouse scan value">
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                        <x-ui.camera-scanner
                            id="camera-scanner-task-{{ $warehouseTask->id }}"
                            target-input-id="task_scan_input_{{ $warehouseTask->id }}"
                            button-text="Scan with Camera"
                            button-variant="secondary"
                            :auto-submit="true"
                            title="Verify Task Step with Camera"
                            hint="{{ $prompt }}"
                        />
                        <x-ui.button type="submit">Validate scan</x-ui.button>
                    </form>
                    <p class="mt-2 text-xs text-neutral-500">Identifiers stay as text so leading zeroes, separators, lots, and serials are preserved.</p>
                </x-ui.card>
                <x-ui.card title="Complete quantity" subtitle="Stock posts only after all required scans succeed.">
                    <form method="POST" action="{{ route('inventory.warehouse-tasks.complete', $warehouseTask) }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">@csrf
                        <div><label class="text-sm font-medium">Quantity</label><input type="number" name="quantity" min="1" max="{{ $warehouseTask->remainingQuantity() }}" step="1" required value="{{ $warehouseTask->remainingQuantity() }}" class="mt-1 w-40 rounded-lg border-neutral-300"></div>
                        <x-ui.button type="submit">Post and complete</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        @endcan

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card title="Scan history">
                <div class="space-y-3">@forelse ($warehouseTask->scans as $scan)<div class="rounded-lg border border-neutral-200 p-3"><div class="flex justify-between gap-2"><span class="font-mono text-sm">{{ $scan->normalized_value }}</span><x-ui.badge :status="$scan->outcome">{{ ucfirst($scan->outcome) }}</x-ui.badge></div><p class="mt-1 text-xs text-neutral-600">{{ $scan->message }} · {{ $scan->created_at?->format('M j, Y g:i A') }}</p></div>@empty<p class="text-sm text-neutral-500">No scans recorded.</p>@endforelse</div>
            </x-ui.card>
            <x-ui.card title="Exceptions">
                <div class="space-y-3">@forelse ($warehouseTask->exceptions as $exception)<div class="rounded-lg border border-rose-200 bg-rose-50 p-3"><p class="font-mono text-xs font-semibold text-rose-800">{{ $exception->exception_number }}</p><p class="text-sm font-semibold text-rose-900">{{ str($exception->exception_type)->replace('_', ' ')->title() }}</p><p class="text-sm text-rose-800">{{ $exception->details }}</p>@can(\App\Enums\Permission::ResolveWarehouseExceptions->value)@if($exception->status !== 'resolved')<form method="POST" action="{{ route('inventory.warehouse-exceptions.resolve', $exception) }}" class="mt-3 space-y-2">@csrf<textarea name="resolution" required maxlength="2000" rows="2" class="w-full rounded-lg border-rose-300" placeholder="Investigation and resolution"></textarea><x-ui.button type="submit" size="sm">Resolve</x-ui.button></form>@endif @endcan</div>@empty<p class="text-sm text-neutral-500">No exceptions recorded.</p>@endforelse</div>
            </x-ui.card>
        </div>

        @can(\App\Enums\Permission::ManageWarehouseTasks->value)
            @if (! in_array($warehouseTask->status, [\App\Enums\WarehouseTaskStatus::Completed, \App\Enums\WarehouseTaskStatus::Cancelled], true))
                <x-ui.card title="Cancel task"><form method="POST" action="{{ route('inventory.warehouse-tasks.cancel', $warehouseTask) }}" data-confirm-title="Cancel warehouse task" data-confirm-message="The task will close without moving stock. Continue?" data-confirm-label="Cancel task" class="flex flex-col gap-3 sm:flex-row">@csrf<input type="text" name="reason" required maxlength="1000" placeholder="Required cancellation reason" class="flex-1 rounded-lg border-neutral-300"><x-ui.button type="submit" variant="danger">Cancel task</x-ui.button></form></x-ui.card>
            @endif
        @endcan
    </div></div>
</x-app-layout>
