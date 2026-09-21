<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Smart Warehousing</p>
                <h2 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">Warehouse Task Registry</h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can(\App\Enums\Permission::ManageWarehouseTasks->value)
                    <x-ui.button type="button" variant="primary" icon="plus" x-data x-on:click="$dispatch('open-modal', 'create-operational-task')">
                        Create Task
                    </x-ui.button>
                @endcan
                <x-ui.button variant="secondary" :href="route('inventory.warehousing.dashboard')" icon="arrow-left">Back to Smart Warehousing</x-ui.button>
            </div>
        </div>
    </x-slot>

    {{-- SWS Consolidated Workflow Navigation --}}
    @include('inventory.warehousing.partials.workflow_nav')

    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4 xl:gap-4">
        {{-- 1. Open Tasks --}}
        <div class="group rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-primary-400 dark:hover:border-primary-600 transition-all duration-150">
            {{-- Zone 1: Header --}}
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-primary-700 dark:text-primary-300">Open Tasks</p>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 dark:bg-primary-950/80 dark:text-primary-300 ring-1 ring-primary-200 dark:ring-primary-800/50 group-hover:scale-105 transition-transform duration-150">
                    <x-ui.icon name="clipboard-document-list" class="h-5 w-5" />
                </span>
            </div>
            {{-- Zone 2: Value --}}
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-neutral-950 dark:text-white">{{ number_format($metrics['open']) }}</span>
                <span class="text-sm sm:text-base font-bold text-neutral-500 dark:text-neutral-400">tasks</span>
            </div>
            {{-- Zone 3: Footer --}}
            <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate">Pending operational execution</span>
            </div>
        </div>

        {{-- 2. Overdue Tasks --}}
        <div class="group rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between {{ $metrics['overdue'] > 0 ? 'hover:border-amber-400 dark:hover:border-amber-600' : 'hover:border-emerald-400 dark:hover:border-emerald-600' }} transition-all duration-150">
            {{-- Zone 1: Header --}}
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs sm:text-sm font-bold uppercase tracking-wider {{ $metrics['overdue'] > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300' }}">Overdue</p>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $metrics['overdue'] > 0 ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300 ring-1 ring-amber-200 dark:ring-amber-800/50' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-800/50' }} group-hover:scale-105 transition-transform duration-150">
                    <x-ui.icon :name="$metrics['overdue'] > 0 ? 'exclamation-triangle' : 'check-circle'" class="h-5 w-5" />
                </span>
            </div>
            {{-- Zone 2: Value --}}
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums {{ $metrics['overdue'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ number_format($metrics['overdue']) }}</span>
                <span class="text-sm sm:text-base font-bold text-neutral-500 dark:text-neutral-400">tasks</span>
            </div>
            {{-- Zone 3: Footer --}}
            <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate">{{ $metrics['overdue'] > 0 ? 'Exceeded scheduled due time' : 'All tasks within schedule' }}</span>
            </div>
        </div>

        {{-- 3. Open Exceptions --}}
        <div class="group rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between {{ $metrics['exceptions'] > 0 ? 'hover:border-rose-400 dark:hover:border-rose-600' : 'hover:border-emerald-400 dark:hover:border-emerald-600' }} transition-all duration-150">
            {{-- Zone 1: Header --}}
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs sm:text-sm font-bold uppercase tracking-wider {{ $metrics['exceptions'] > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">Open Exceptions</p>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $metrics['exceptions'] > 0 ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-800/50' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-800/50' }} group-hover:scale-105 transition-transform duration-150">
                    <x-ui.icon :name="$metrics['exceptions'] > 0 ? 'exclamation-triangle' : 'shield-check'" class="h-5 w-5" />
                </span>
            </div>
            {{-- Zone 2: Value --}}
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums {{ $metrics['exceptions'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ number_format($metrics['exceptions']) }}</span>
                <span class="text-sm sm:text-base font-bold text-neutral-500 dark:text-neutral-400">exceptions</span>
            </div>
            {{-- Zone 3: Footer --}}
            <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate">{{ $metrics['exceptions'] > 0 ? 'Require supervisor resolution' : 'Zero operational discrepancies' }}</span>
            </div>
        </div>

        {{-- 4. Completed Today --}}
        <div class="group rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-emerald-400 dark:hover:border-emerald-600 transition-all duration-150">
            {{-- Zone 1: Header --}}
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Completed Today</p>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-800/50 group-hover:scale-105 transition-transform duration-150">
                    <x-ui.icon name="check-badge" class="h-5 w-5" />
                </span>
            </div>
            {{-- Zone 2: Value --}}
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-emerald-600 dark:text-emerald-400">{{ number_format($metrics['completed_today']) }}</span>
                <span class="text-sm sm:text-base font-bold text-neutral-500 dark:text-neutral-400">tasks</span>
            </div>
            {{-- Zone 3: Footer --}}
            <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate">Finished in current shift</span>
            </div>
        </div>
    </div>

    @can(\App\Enums\Permission::ManageWarehouseTasks->value)
        <x-ui.modal name="create-operational-task" maxWidth="3xl">
            <x-slot:header>
                <div>
                    <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Create Operational Task</h2>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                        Use for a real physical move, replenishment, or put-away not already generated by a source document.
                    </p>
                </div>
            </x-slot:header>

            <form method="POST" action="{{ route('inventory.warehouse-tasks.store') }}"
                  @if ($errors->any()) x-init="open = true" @endif
                  class="space-y-4">
                @csrf

                @if ($errors->any())
                    <x-ui.alert variant="danger" title="Warehouse task could not be saved">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="task_type" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Task type <span class="text-rose-500">*</span></label>
                        <select id="task_type" name="task_type" required class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm">
                            <option value="">Select task type</option>
                            <option value="put_away" @selected(old('task_type') === 'put_away')>Put-away</option>
                            <option value="replenishment" @selected(old('task_type') === 'replenishment')>Pick-face replenishment</option>
                            <option value="move" @selected(old('task_type') === 'move')>Internal movement</option>
                        </select>
                    </div>
                    <div>
                        <label for="priority" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Priority <span class="text-rose-500">*</span></label>
                        <select id="priority" name="priority" required class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm">
                            @foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="item_id" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Inventory item <span class="text-rose-500">*</span></label>
                    <select id="item_id" name="item_id" required class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm">
                        <option value="">Select item</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}" @selected((string) old('item_id') === (string) $item->id)>{{ $item->name }} ({{ $item->sku }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="source_location_id" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Source location <span class="text-rose-500">*</span></label>
                        <select id="source_location_id" name="source_location_id" required class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm">
                            <option value="">Select source</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) old('source_location_id') === (string) $location->id)>{{ $location->code }} - {{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="destination_location_id" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Destination location <span class="text-rose-500">*</span></label>
                        <select id="destination_location_id" name="destination_location_id" required class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm">
                            <option value="">Select destination</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) old('destination_location_id') === (string) $location->id)>{{ $location->code }} - {{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="requested_quantity" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Quantity <span class="text-rose-500">*</span></label>
                        <input id="requested_quantity" name="requested_quantity" type="number" min="1" step="1" required value="{{ old('requested_quantity') }}" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm" inputmode="numeric">
                    </div>
                    <div>
                        <label for="assigned_to_id" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Assignee</label>
                        <select id="assigned_to_id" name="assigned_to_id" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm">
                            <option value="">Leave unassigned</option>
                            @foreach ($operators as $operator)
                                <option value="{{ $operator->id }}" @selected((string) old('assigned_to_id') === (string) $operator->id)>{{ $operator->name }} - {{ $operator->role->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="due_at" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Due at</label>
                        <input id="due_at" name="due_at" type="datetime-local" value="{{ old('due_at') }}" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm">
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Operational notes</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="1000" placeholder="Operational instructions or handling requirements..." class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm">{{ old('notes') }}</textarea>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-neutral-200 dark:border-neutral-800">
                    <x-ui.button type="button" variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="primary">Create task</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        @if ($errors->any())
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'create-operational-task'))"></div>
        @endif
    @endcan

    <x-ui.card title="Task queue" subtitle="Filter by workflow type or current state.">
        @can(\App\Enums\Permission::ManageWarehouseTasks->value)
            <x-slot:actions>
                <x-ui.button type="button" size="sm" variant="primary" icon="plus" x-data x-on:click="$dispatch('open-modal', 'create-operational-task')">
                    Create Task
                </x-ui.button>
            </x-slot:actions>
        @endcan

        <form method="GET" action="{{ route('inventory.warehouse-tasks.index') }}" class="mb-5 grid gap-3 sm:grid-cols-3">
            <select name="type" class="rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500 pl-3 pr-10">
                <option value="">All task types</option>
                @foreach (\App\Enums\WarehouseTaskType::cases() as $type)<option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>@endforeach
            </select>
            <select name="status" class="rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm focus:border-primary-500 focus:ring-primary-500 pl-3 pr-10">
                <option value="">All statuses</option>
                @foreach (\App\Enums\WarehouseTaskStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach
            </select>
            <div class="flex items-center gap-2">
                <x-ui.button type="submit" variant="secondary" class="flex-1 justify-center whitespace-nowrap">Apply filters</x-ui.button>
                @if (request()->hasAny(['type', 'status']))
                    <x-ui.button variant="ghost" :href="route('inventory.warehouse-tasks.index')" class="shrink-0 whitespace-nowrap">Reset</x-ui.button>
                @endif
            </div>
        </form>

                <x-ui.table>
                    <x-ui.table.head><tr><x-ui.table.th>Task</x-ui.table.th><x-ui.table.th>Item / quantity</x-ui.table.th><x-ui.table.th>Route</x-ui.table.th><x-ui.table.th>Assignee</x-ui.table.th><x-ui.table.th>Status</x-ui.table.th><x-ui.table.th></x-ui.table.th></tr></x-ui.table.head>
                    <tbody>
                    @forelse ($tasks as $task)
                        <x-ui.table.row>
                            <x-ui.table.td><p class="font-mono text-xs font-semibold text-primary-700">{{ $task->task_number }}</p><p>{{ $task->task_type->label() }}</p></x-ui.table.td>
                            <x-ui.table.td>{{ $task->item?->name ?? 'No item' }}<p class="text-xs text-neutral-500">{{ $task->completed_quantity }}/{{ $task->requested_quantity }} {{ $task->item?->unit }}</p></x-ui.table.td>
                            <x-ui.table.td><span class="font-mono text-xs">{{ $task->sourceLocation?->code ?? '—' }} → {{ $task->destinationLocation?->code ?? '—' }}</span></x-ui.table.td>
                            <x-ui.table.td>{{ $task->assignedTo?->name ?? 'Unassigned' }}</x-ui.table.td>
                            <x-ui.table.td><x-ui.badge :status="$task->status->value">{{ $task->status->label() }}</x-ui.badge></x-ui.table.td>
                            <x-ui.table.td><a class="text-sm font-semibold text-primary-700 hover:underline" href="{{ route('inventory.warehouse-tasks.show', $task) }}">Open</a></x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty colspan="6" title="No warehouse tasks" message="Tasks appear when work is generated from an eligible workflow or created by a manager." />
                    @endforelse
                    </tbody>
                </x-ui.table>
                @if ($tasks->hasPages())
                    <x-slot:footer>
                        {{ $tasks->links() }}
                    </x-slot:footer>
                @endif
            </x-ui.card>
</x-app-layout>
