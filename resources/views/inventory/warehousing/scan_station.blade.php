<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700">Scan-Assisted Execution</p>
                <h2 class="text-2xl font-bold text-neutral-900">Warehouse Scan Workstation</h2>
                <p class="text-sm text-neutral-600">Barcode and 2D GS1 DataMatrix scan verification for directed put-away, replenishment, and picking.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('inventory.warehouse-tasks.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    Warehouse Tasks
                </a>
                <a href="{{ route('inventory.warehousing.dashboard') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{
        selectedTaskId: '{{ $activeTasks->first()?->id ?? '' }}',
        scanInput: '',
        playBeep(success = true) {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = success ? 880 : 220;
                gain.gain.value = 0.1;
                osc.start();
                setTimeout(() => { osc.stop(); ctx.close(); }, success ? 150 : 400);
            } catch (e) {}
        }
    }">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

            @if(session('success'))
                <x-ui.alert variant="success" :message="session('success')" />
            @endif
            @if(session('error'))
                <x-ui.alert variant="danger" :message="session('error')" />
            @endif
            @if($errors->any())
                <x-ui.alert variant="danger" title="Scan verification error">
                    <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </x-ui.alert>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">

                {{-- Left: Active Task Selector & Scan Input --}}
                <div class="lg:col-span-2 space-y-6">

                    {{-- Active Task Card --}}
                    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                        <label for="task_selector" class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Select Task to Execute</label>
                        <select id="task_selector" x-model="selectedTaskId" class="mt-2 w-full rounded-xl border-neutral-300 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            @forelse($activeTasks as $task)
                                <option value="{{ $task->id }}">
                                    [{{ $task->task_type->label() }}] {{ $task->task_number }} &bull; {{ $task->item?->name }} ({{ $task->requested_quantity }} units) &bull; {{ $task->status->label() }}
                                </option>
                            @empty
                                <option value="">No active tasks available</option>
                            @endforelse
                        </select>

                        @foreach($activeTasks as $task)
                            <div x-show="selectedTaskId == '{{ $task->id }}'" class="mt-6 space-y-6" style="display: none;">
                                {{-- Task Details Banner --}}
                                <div class="rounded-lg bg-neutral-50 p-4 border border-neutral-200 flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-sm font-black text-neutral-900">{{ $task->task_number }}</span>
                                            <span class="rounded bg-primary-100 px-2 py-0.5 text-xs font-bold text-primary-800 uppercase">{{ $task->task_type->label() }}</span>
                                            <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 capitalize">Priority: {{ $task->priority }}</span>
                                        </div>
                                        <p class="mt-1 text-sm font-semibold text-neutral-800">{{ $task->item?->name }} (SKU: {{ $task->item?->sku }})</p>
                                        <p class="text-xs text-neutral-500">Quantity Required: <span class="font-bold text-neutral-700">{{ $task->requested_quantity }} {{ $task->item?->unit ?? 'units' }}</span></p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs text-neutral-500">Assigned To</div>
                                        <div class="text-sm font-bold text-neutral-900">{{ $task->assignedTo?->name ?? 'Unassigned' }}</div>
                                        <a href="{{ route('inventory.warehouse-tasks.show', $task) }}" class="text-xs font-semibold text-primary-600 hover:underline">View Task Details &rarr;</a>
                                    </div>
                                </div>

                                {{-- Visual Scan Sequence Steps --}}
                                <div>
                                    <h4 class="text-xs font-semibold uppercase text-neutral-500 tracking-wider mb-3">Scan Verification Progression</h4>
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div class="rounded-lg border p-3 @if($task->sourceLocation) bg-blue-50 border-blue-200 text-blue-900 @else bg-neutral-50 border-neutral-200 text-neutral-600 @endif">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-blue-700">Step 1: Source Location</div>
                                            <div class="font-mono text-xs font-bold mt-1">{{ $task->sourceLocation?->code ?? 'N/A' }}</div>
                                            <div class="text-[11px] text-neutral-500 mt-0.5 truncate">{{ $task->sourceLocation?->name }}</div>
                                        </div>

                                        <div class="rounded-lg border p-3 bg-neutral-50 border-neutral-200 text-neutral-900">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-neutral-700">Step 2: Item / GS1 Lot</div>
                                            <div class="font-mono text-xs font-bold mt-1 truncate">{{ $task->item?->barcode_value ?? $task->item?->sku }}</div>
                                            <div class="text-[11px] text-neutral-500 mt-0.5">Lot: {{ $task->batch?->batch_number ?? 'Any lot' }}</div>
                                        </div>

                                        <div class="rounded-lg border p-3 @if($task->destinationLocation) bg-emerald-50 border-emerald-200 text-emerald-900 @else bg-neutral-50 border-neutral-200 text-neutral-600 @endif">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Step 3: Destination</div>
                                            <div class="font-mono text-xs font-bold mt-1">{{ $task->destinationLocation?->code ?? 'N/A' }}</div>
                                            <div class="text-[11px] text-neutral-500 mt-0.5 truncate">{{ $task->destinationLocation?->name }}</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Barcode Scan Form --}}
                                <div class="rounded-xl border border-primary-200 bg-primary-50/50 p-6">
                                    <form method="POST" action="{{ route('inventory.warehouse-tasks.scan', $task) }}" @submit="playBeep(true)">
                                        @csrf
                                        <div class="flex items-center justify-between mb-2">
                                            <label for="scan_value_{{ $task->id }}" class="text-sm font-bold text-neutral-900 flex items-center gap-2">
                                                <svg class="h-5 w-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                                Ready for Barcode / QR Scanner Input
                                            </label>
                                            <span class="text-xs text-neutral-500 font-mono">Auto-Focus Active</span>
                                        </div>

                                        <div class="flex gap-2">
                                            <input type="text" id="scan_value_{{ $task->id }}" name="scan_value" autofocus required placeholder="Scan or enter barcode / GS1 DataMatrix string..." class="w-full rounded-xl border-neutral-300 font-mono text-base font-semibold shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                            <button type="submit" class="rounded-xl bg-primary-600 px-6 py-3 font-semibold text-white shadow-sm hover:bg-primary-700">
                                                Verify
                                            </button>
                                        </div>
                                    </form>

                                    {{-- Fast Simulator Buttons for Rapid Testing --}}
                                    <div class="mt-4 border-t border-primary-200/60 pt-3">
                                        <p class="text-[11px] font-semibold text-neutral-500 uppercase tracking-wider mb-2">Simulation Shortcuts</p>
                                        <div class="flex flex-wrap gap-2">
                                            @if($task->sourceLocation)
                                                <form method="POST" action="{{ route('inventory.warehouse-tasks.scan', $task) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="scan_value" value="{{ $task->sourceLocation->barcode_value ?? $task->sourceLocation->code }}">
                                                    <button type="submit" class="rounded bg-white px-2.5 py-1 text-xs font-mono border border-neutral-300 hover:bg-neutral-50">
                                                        Scan Source: {{ $task->sourceLocation->code }}
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('inventory.warehouse-tasks.scan', $task) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="scan_value" value="{{ $task->item?->barcode_value ?? $task->item?->sku }}">
                                                <button type="submit" class="rounded bg-white px-2.5 py-1 text-xs font-mono border border-neutral-300 hover:bg-neutral-50">
                                                    Scan Item: {{ $task->item?->sku }}
                                                </button>
                                            </form>
                                            @if($task->destinationLocation)
                                                <form method="POST" action="{{ route('inventory.warehouse-tasks.scan', $task) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="scan_value" value="{{ $task->destinationLocation->barcode_value ?? $task->destinationLocation->code }}">
                                                    <button type="submit" class="rounded bg-white px-2.5 py-1 text-xs font-mono border border-neutral-300 hover:bg-neutral-50">
                                                        Scan Dest: {{ $task->destinationLocation->code }}
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('inventory.warehouse-tasks.scan', $task) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="scan_value" value="INVALID-BARCODE-999">
                                                <button type="submit" class="rounded bg-red-50 text-red-700 px-2.5 py-1 text-xs font-mono border border-red-200 hover:bg-red-100">
                                                    Test Wrong Scan
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                {{-- Task Completion Card --}}
                                <div class="rounded-xl border border-neutral-200 bg-white p-5 flex items-center justify-between">
                                    <div>
                                        <h5 class="text-sm font-bold text-neutral-900">Finalize & Post Inventory Movement</h5>
                                        <p class="text-xs text-neutral-500">Requires verified scan sequence. Decrements source and increments destination.</p>
                                    </div>
                                    <form method="POST" action="{{ route('inventory.warehouse-tasks.complete', $task) }}" class="flex items-center gap-2">
                                        @csrf
                                        <input type="number" name="quantity" min="1" max="{{ $task->remainingQuantity() }}" value="{{ $task->remainingQuantity() }}" class="w-24 rounded-lg border-neutral-300 text-sm font-bold">
                                        <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 shadow-sm">
                                            Complete Task
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>

                </div>

                {{-- Right: Live Scan Feed & Guidelines --}}
                <div class="space-y-6">
                    <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 mb-3">Recent Scans On Station</h4>
                        <div class="divide-y divide-neutral-100">
                            @forelse($recentScans as $scan)
                                <div class="py-2.5 flex items-start gap-2.5">
                                    <span class="mt-1 h-2 w-2 rounded-full shrink-0 @if($scan->outcome === 'accepted') bg-emerald-500 @else bg-red-500 @endif"></span>
                                    <div class="truncate">
                                        <div class="font-mono text-xs font-bold text-neutral-800 truncate">{{ $scan->raw_value }}</div>
                                        <div class="text-[11px] text-neutral-500 truncate">{{ $scan->message }}</div>
                                        <div class="text-[10px] text-neutral-400 font-mono">{{ $scan->created_at?->diffForHumans() }}</div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-neutral-400 py-4 text-center">No scans recorded yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-5">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-700 mb-2">Scanning Best Practices</h4>
                        <ul class="text-xs text-neutral-600 space-y-2 list-disc pl-4">
                            <li>Keep barcode labels clean and flat during scanning.</li>
                            <li>For GS1 DataMatrix, ensure the entire 2D matrix is within the aimer field.</li>
                            <li>The terminal automatically parses GTIN (01), Expiry (17), Lot (10), and Serial (21).</li>
                            <li>Scanning the wrong location or item halts the workflow and generates an auditable exception ticket.</li>
                        </ul>
                    </div>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
