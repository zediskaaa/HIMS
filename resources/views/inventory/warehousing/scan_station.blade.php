<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Scan-Assisted Execution</p>
                <h2 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">Warehouse Scan Workstation</h2>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('inventory.warehouse-tasks.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700/60">
                    Warehouse Tasks
                </a>
                <x-ui.button variant="secondary" :href="route('inventory.warehousing.dashboard')" icon="arrow-left">Back to Smart Warehousing</x-ui.button>
            </div>
        </div>
    </x-slot>

    <div
        class="space-y-6"
        @hims-code-scanned.window="if ($event.detail.targetInputId === 'standby_scan_input') { scanInput = $event.detail.code; lookupBarcode($event.detail.code); }"
        x-data="{
        selectedTaskId: '{{ $activeTasks->first()?->id ?? '' }}',
        scanInput: '',
        lookupLoading: false,
        lookupResult: null,
        lookupError: null,
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
        },
        async lookupBarcode(code) {
            const val = (code || this.scanInput || '').trim();
            if (!val) return;
            this.lookupLoading = true;
            this.lookupResult = null;
            this.lookupError = null;

            try {
                const token = document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content');
                const res = await fetch('{{ route('inventory.warehousing.lookup-barcode') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token || '',
                    },
                    body: JSON.stringify({ barcode: val }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.playBeep(false);
                    this.lookupError = data.message || 'Lookup failed. Identifier could not be resolved.';
                    return;
                }
                this.playBeep(true);
                this.lookupResult = data;
            } catch (err) {
                this.playBeep(false);
                this.lookupError = 'Network error or server unreachable during barcode lookup.';
            } finally {
                this.lookupLoading = false;
            }
        }
    }"
    @hims-code-scanned.window="if ($event.detail.targetInputId === 'standby_scan_input') { scanInput = $event.detail.code; lookupBarcode($event.detail.code); }"
    >

        {{-- SWS Consolidated Workflow Navigation --}}
        @include('inventory.warehousing.partials.workflow_nav')

        {{-- Persistent notices and validation summaries --}}
        @if(session('notice'))
            <x-ui.alert variant="info" :message="session('notice')" />
        @endif
        @if($errors->any())
            <x-ui.alert variant="danger" title="Scan verification error">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-ui.alert>
        @endif

        {{-- Workstation Operational KPI Strip --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Terminal Status</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        ONLINE
                    </span>
                </div>
                <div class="mt-2.5 flex items-baseline justify-between">
                    <span class="text-xl font-bold font-mono text-neutral-900 dark:text-neutral-100">WS-SCAN-01</span>
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">1D / 2D Vision</span>
                </div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400 truncate">Aimer &amp; GS1 parser operational</p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Active Task Queue</span>
                    <span class="rounded-lg bg-blue-50 p-1.5 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </span>
                </div>
                <div class="mt-2.5 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ $workstationMetrics['active_tasks'] ?? $activeTasks->count() }}</span>
                    @if(($workstationMetrics['active_tasks'] ?? $activeTasks->count()) > 0)
                        <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">In Progress</span>
                    @else
                        <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">Queue Clear</span>
                    @endif
                </div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Pending putaway &amp; picking jobs</p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Completed Today</span>
                    <span class="rounded-lg bg-emerald-50 p-1.5 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                </div>
                <div class="mt-2.5 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ $workstationMetrics['completed_today'] ?? 0 }}</span>
                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Finalized</span>
                </div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Inventory movements executed</p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Scans Logged Today</span>
                    <span class="rounded-lg bg-purple-50 p-1.5 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                    </span>
                </div>
                <div class="mt-2.5 flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ $workstationMetrics['total_scans_today'] ?? 0 }}</span>
                    <span class="text-xs text-purple-600 dark:text-purple-400 font-medium">Verifications</span>
                </div>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Optical scans &amp; camera reads</p>
            </div>
        </div>

        {{-- Main Workstation Grid --}}
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Left Column (2/3): Task Execution / Standby Scanner & Recent Activity Registry --}}
            <div class="lg:col-span-2 space-y-6">

                @if($activeTasks->isNotEmpty())
                    {{-- Active Task Card --}}
                    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                            <label for="task_selector" class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Select Task to Execute</label>
                            <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ $activeTasks->count() }} task{{ $activeTasks->count() > 1 ? 's' : '' }} queued</span>
                        </div>

                        {{-- Task Selector Dropdown --}}
                        <div class="relative">
                            <select id="task_selector" x-model="selectedTaskId" class="w-full rounded-xl border border-neutral-300 bg-white py-2.5 pl-3.5 pr-10 text-sm font-semibold text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                                @foreach($activeTasks as $task)
                                    <option value="{{ $task->id }}">
                                        [{{ $task->task_type->label() }}] {{ $task->task_number }} &bull; {{ $task->item?->name }} ({{ $task->requested_quantity }} units) &bull; {{ $task->status->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Quick Selection Chips for Operators --}}
                        @if($activeTasks->count() > 1)
                            <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500 mr-1">Quick Select:</span>
                                @foreach($activeTasks as $chipTask)
                                    <button
                                        type="button"
                                        @click="selectedTaskId = '{{ $chipTask->id }}'"
                                        class="rounded-lg px-2.5 py-1 text-xs font-mono font-semibold transition-colors"
                                        :class="selectedTaskId == '{{ $chipTask->id }}' ? 'bg-primary-600 text-white shadow-xs' : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700'"
                                    >
                                        {{ $chipTask->task_number }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @foreach($activeTasks as $task)
                            @php
                                $acceptedScans = $task->scans->where('outcome', 'accepted')->count();
                                $step1Done = $acceptedScans >= 1;
                                $step2Done = $acceptedScans >= 2;
                                $step3Done = $acceptedScans >= 3;

                                $sourceVal = $task->sourceLocation?->barcode_value ?: ($task->sourceLocation?->code ?? '');
                                $itemVal = $task->item?->barcode_value ?: ($task->item?->sku ?? '');
                                $destVal = $task->destinationLocation?->barcode_value ?: ($task->destinationLocation?->code ?? '');

                                $activeStepNum = match(true) {
                                    $acceptedScans === 0 => 1,
                                    $acceptedScans === 1 => 2,
                                    $acceptedScans === 2 => 3,
                                    default => 4,
                                };

                                $activeStepInfo = match($activeStepNum) {
                                    1 => ['title' => 'Source Location', 'code' => $sourceVal, 'hint' => 'Scan the shelf / rack barcode at the source location.'],
                                    2 => ['title' => 'Item / Product', 'code' => $itemVal, 'hint' => 'Scan the product barcode or GS1 DataMatrix on the packaging.'],
                                    3 => ['title' => 'Destination Location', 'code' => $destVal, 'hint' => 'Scan the destination bin barcode to confirm putaway.'],
                                    default => ['title' => 'All Scans Completed', 'code' => '', 'hint' => 'All scan steps verified! You can now finalize the movement below.'],
                                };

                                $labelService = app(\App\Services\Warehouse\WarehouseLabelService::class);
                                $sourceQr = $sourceVal ? $labelService->qrDataUri($sourceVal) : null;
                                $itemQr = $itemVal ? $labelService->qrDataUri($itemVal) : null;
                                $destQr = $destVal ? $labelService->qrDataUri($destVal) : null;
                                $taskQr = $labelService->qrDataUri($task->task_number);
                            @endphp

                            <div x-show="selectedTaskId == '{{ $task->id }}'" class="mt-6 space-y-6" style="display: none;">
                                {{-- Task Details Banner --}}
                                <div class="rounded-xl bg-neutral-50 p-4 border border-neutral-200 flex flex-wrap items-center justify-between gap-4 dark:border-neutral-800 dark:bg-neutral-800/60">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-sm font-black text-neutral-900 dark:text-neutral-100">{{ $task->task_number }}</span>
                                            <span class="rounded bg-primary-100 px-2 py-0.5 text-xs font-bold text-primary-800 uppercase dark:bg-primary-950/80 dark:text-primary-300">{{ $task->task_type->label() }}</span>
                                            <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 capitalize dark:bg-amber-950/80 dark:text-amber-300">Priority: {{ $task->priority }}</span>
                                        </div>
                                        <p class="mt-1 text-sm font-semibold text-neutral-800 dark:text-neutral-200">{{ $task->item?->name }} (SKU: {{ $task->item?->sku }})</p>
                                        <p class="text-xs text-neutral-500 dark:text-neutral-400">Quantity Required: <span class="font-bold text-neutral-700 dark:text-neutral-300">{{ $task->requested_quantity }} {{ $task->item?->unit ?? 'units' }}</span></p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs text-neutral-500 dark:text-neutral-400">Assigned To</div>
                                        <div class="text-sm font-bold text-neutral-900 dark:text-neutral-100">{{ $task->assignedTo?->name ?? 'Unassigned' }}</div>
                                        <a href="{{ route('inventory.warehouse-tasks.show', $task) }}" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">View Task Details &rarr;</a>
                                    </div>
                                </div>

                                {{-- Visual Scan Sequence Steps with Scannable QR Codes --}}
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-xs font-semibold uppercase text-neutral-500 dark:text-neutral-400 tracking-wider">Scan Verification Progression</h4>
                                        <span class="text-xs font-medium @if($activeStepNum === 4) text-emerald-600 dark:text-emerald-400 @else text-primary-700 dark:text-primary-300 @endif">
                                            @if($activeStepNum === 4)
                                                All 3 scan steps verified
                                            @else
                                                Waiting for Step {{ $activeStepNum }} of 3
                                            @endif
                                        </span>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-3">
                                        {{-- Step 1: Source Location --}}
                                        <div x-data="{ showQr: false }" class="rounded-xl border p-3.5 transition-all @if($step1Done) bg-emerald-50/70 border-emerald-300 text-emerald-950 dark:bg-emerald-950/30 dark:border-emerald-700 dark:text-emerald-200 @elseif($activeStepNum === 1) bg-primary-50/70 border-primary-400 ring-2 ring-primary-500/20 text-neutral-900 dark:bg-primary-950/30 dark:border-primary-600 dark:text-neutral-100 shadow-sm @else bg-neutral-50/50 border-neutral-200 text-neutral-500 dark:bg-neutral-800/40 dark:border-neutral-800 dark:text-neutral-400 @endif">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[10px] font-extrabold uppercase tracking-wider @if($step1Done) text-emerald-700 dark:text-emerald-400 @elseif($activeStepNum === 1) text-primary-700 dark:text-primary-300 @else text-neutral-400 @endif">
                                                    Step 1: Source Location
                                                </span>
                                                @if($step1Done)
                                                    <span class="inline-flex items-center gap-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg> Done
                                                    </span>
                                                @elseif($activeStepNum === 1)
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-primary-600 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm animate-pulse">
                                                        Scan Now
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="font-mono text-sm font-bold mt-1.5 truncate">{{ $sourceVal ?: 'N/A' }}</div>
                                            <div class="text-[11px] text-neutral-500 dark:text-neutral-400 truncate">{{ $task->sourceLocation?->name }}</div>

                                            @if($sourceQr)
                                                <div class="mt-2.5 pt-2 border-t border-neutral-200/60 dark:border-neutral-700/60 flex items-center justify-between">
                                                    <button type="button" @click="showQr = true" class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary-600 hover:text-primary-800 hover:underline dark:text-primary-400">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                                        Show QR Code
                                                    </button>
                                                    <span class="text-[10px] text-neutral-400 font-mono">Location</span>
                                                </div>

                                                {{-- QR Code Modal --}}
                                                <div x-show="showQr" @click.outside="showQr = false" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                                                    <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl text-center dark:bg-neutral-900 dark:border dark:border-neutral-800">
                                                        <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Step 1: Source Location QR</h3>
                                                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Scan this code with your camera or scanner</p>
                                                        <div class="mt-4 flex justify-center">
                                                            <img src="{{ $sourceQr }}" alt="Source Location QR" class="h-56 w-56 rounded-xl border border-neutral-200 p-2 shadow-sm bg-white dark:border-neutral-700">
                                                        </div>
                                                        <p class="mt-3 font-mono text-sm font-bold text-neutral-800 dark:text-neutral-200">{{ $sourceVal }}</p>
                                                        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $task->sourceLocation?->name }}</p>
                                                        <button type="button" @click="showQr = false" class="mt-5 w-full rounded-xl bg-neutral-800 py-2 text-sm font-semibold text-white hover:bg-neutral-900 dark:bg-neutral-700 dark:hover:bg-neutral-600">
                                                            Close QR Code
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Step 2: Item / GS1 Lot --}}
                                        <div x-data="{ showQr: false }" class="rounded-xl border p-3.5 transition-all @if($step2Done) bg-emerald-50/70 border-emerald-300 text-emerald-950 dark:bg-emerald-950/30 dark:border-emerald-700 dark:text-emerald-200 @elseif($activeStepNum === 2) bg-primary-50/70 border-primary-400 ring-2 ring-primary-500/20 text-neutral-900 dark:bg-primary-950/30 dark:border-primary-600 dark:text-neutral-100 shadow-sm @else bg-neutral-50/50 border-neutral-200 text-neutral-500 dark:bg-neutral-800/40 dark:border-neutral-800 dark:text-neutral-400 @endif">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[10px] font-extrabold uppercase tracking-wider @if($step2Done) text-emerald-700 dark:text-emerald-400 @elseif($activeStepNum === 2) text-primary-700 dark:text-primary-300 @else text-neutral-400 @endif">
                                                    Step 2: Item / GS1 Lot
                                                </span>
                                                @if($step2Done)
                                                    <span class="inline-flex items-center gap-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg> Done
                                                    </span>
                                                @elseif($activeStepNum === 2)
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-primary-600 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm animate-pulse">
                                                        Scan Now
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="font-mono text-sm font-bold mt-1.5 truncate">{{ $itemVal ?: 'N/A' }}</div>
                                            <div class="text-[11px] text-neutral-500 dark:text-neutral-400 truncate">Lot: {{ $task->batch?->batch_number ?? 'Any lot' }}</div>

                                            @if($itemQr)
                                                <div class="mt-2.5 pt-2 border-t border-neutral-200/60 dark:border-neutral-700/60 flex items-center justify-between">
                                                    <button type="button" @click="showQr = true" class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary-600 hover:text-primary-800 hover:underline dark:text-primary-400">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                                        Show QR Code
                                                    </button>
                                                    <span class="text-[10px] text-neutral-400 font-mono">Product</span>
                                                </div>

                                                {{-- QR Code Modal --}}
                                                <div x-show="showQr" @click.outside="showQr = false" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                                                    <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl text-center dark:bg-neutral-900 dark:border dark:border-neutral-800">
                                                        <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Step 2: Product QR Code</h3>
                                                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Scan this code with your camera or scanner</p>
                                                        <div class="mt-4 flex justify-center">
                                                            <img src="{{ $itemQr }}" alt="Product QR Code" class="h-56 w-56 rounded-xl border border-neutral-200 p-2 shadow-sm bg-white dark:border-neutral-700">
                                                        </div>
                                                        <p class="mt-3 font-mono text-sm font-bold text-neutral-800 dark:text-neutral-200">{{ $itemVal }}</p>
                                                        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $task->item?->name }}</p>
                                                        <button type="button" @click="showQr = false" class="mt-5 w-full rounded-xl bg-neutral-800 py-2 text-sm font-semibold text-white hover:bg-neutral-900 dark:bg-neutral-700 dark:hover:bg-neutral-600">
                                                            Close QR Code
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Step 3: Destination Location --}}
                                        <div x-data="{ showQr: false }" class="rounded-xl border p-3.5 transition-all @if($step3Done) bg-emerald-50/70 border-emerald-300 text-emerald-950 dark:bg-emerald-950/30 dark:border-emerald-700 dark:text-emerald-200 @elseif($activeStepNum === 3) bg-primary-50/70 border-primary-400 ring-2 ring-primary-500/20 text-neutral-900 dark:bg-primary-950/30 dark:border-primary-600 dark:text-neutral-100 shadow-sm @else bg-neutral-50/50 border-neutral-200 text-neutral-500 dark:bg-neutral-800/40 dark:border-neutral-800 dark:text-neutral-400 @endif">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[10px] font-extrabold uppercase tracking-wider @if($step3Done) text-emerald-700 dark:text-emerald-400 @elseif($activeStepNum === 3) text-primary-700 dark:text-primary-300 @else text-neutral-400 @endif">
                                                    Step 3: Destination
                                                </span>
                                                @if($step3Done)
                                                    <span class="inline-flex items-center gap-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg> Done
                                                    </span>
                                                @elseif($activeStepNum === 3)
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-primary-600 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm animate-pulse">
                                                        Scan Now
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="font-mono text-sm font-bold mt-1.5 truncate">{{ $destVal ?: 'N/A' }}</div>
                                            <div class="text-[11px] text-neutral-500 dark:text-neutral-400 truncate">{{ $task->destinationLocation?->name }}</div>

                                            @if($destQr)
                                                <div class="mt-2.5 pt-2 border-t border-neutral-200/60 dark:border-neutral-700/60 flex items-center justify-between">
                                                    <button type="button" @click="showQr = true" class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary-600 hover:text-primary-800 hover:underline dark:text-primary-400">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                                        Show QR Code
                                                    </button>
                                                    <span class="text-[10px] text-neutral-400 font-mono">Location</span>
                                                </div>

                                                {{-- QR Code Modal --}}
                                                <div x-show="showQr" @click.outside="showQr = false" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                                                    <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl text-center dark:bg-neutral-900 dark:border dark:border-neutral-800">
                                                        <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Step 3: Destination Location QR</h3>
                                                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Scan this code with your camera or scanner</p>
                                                        <div class="mt-4 flex justify-center">
                                                            <img src="{{ $destQr }}" alt="Destination Location QR" class="h-56 w-56 rounded-xl border border-neutral-200 p-2 shadow-sm bg-white dark:border-neutral-700">
                                                        </div>
                                                        <p class="mt-3 font-mono text-sm font-bold text-neutral-800 dark:text-neutral-200">{{ $destVal }}</p>
                                                        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $task->destinationLocation?->name }}</p>
                                                        <button type="button" @click="showQr = false" class="mt-5 w-full rounded-xl bg-neutral-800 py-2 text-sm font-semibold text-white hover:bg-neutral-900 dark:bg-neutral-700 dark:hover:bg-neutral-600">
                                                            Close QR Code
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Barcode Scan Form --}}
                                <div class="rounded-xl border border-primary-200 bg-primary-50/50 p-6 dark:border-primary-900/60 dark:bg-primary-950/20">
                                    <form method="POST" action="{{ route('inventory.warehouse-tasks.scan', $task) }}" @submit="playBeep(true)">
                                        @csrf
                                        <div class="flex items-center justify-between mb-2">
                                            <label for="scan_value_{{ $task->id }}" class="text-sm font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                                                <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                                <span>Ready for Barcode / QR Scanner Input</span>
                                                <span class="text-xs font-normal text-primary-700 dark:text-primary-300 ml-1">
                                                    @if($activeStepNum <= 3)
                                                        &bull; Expecting Step {{ $activeStepNum }}: {{ $activeStepInfo['title'] }}
                                                    @else
                                                        &bull; All Steps Complete
                                                    @endif
                                                </span>
                                            </label>
                                            <span class="text-xs text-neutral-500 dark:text-neutral-400 font-mono">Camera / Laser Active</span>
                                        </div>

                                        <div class="flex flex-col sm:flex-row gap-2">
                                            <div class="relative flex-1">
                                                <input type="text" id="scan_value_{{ $task->id }}" name="scan_value" autofocus required placeholder="{{ $activeStepNum <= 3 ? 'Waiting for Step '.$activeStepNum.': Scan '.$activeStepInfo['title'].' ('.$activeStepInfo['code'].')...' : 'All steps verified' }}" class="w-full rounded-xl border border-neutral-300 bg-white font-mono text-base font-semibold shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <x-ui.camera-scanner
                                                    id="camera-scanner-{{ $task->id }}"
                                                    target-input-id="scan_value_{{ $task->id }}"
                                                    button-text="Open Camera"
                                                    button-variant="secondary"
                                                    :auto-submit="true"
                                                    title="Scan Step {{ $activeStepNum }}: {{ $activeStepInfo['title'] }}"
                                                    hint="Expecting Step {{ $activeStepNum }} ({{ $activeStepInfo['title'] }}): Point camera at code {{ $activeStepInfo['code'] }}. You can click 'Show QR Code' above to view the scannable code on screen."
                                                />
                                                <button type="submit" class="rounded-xl bg-primary-600 px-6 py-2.5 font-semibold text-white shadow-sm hover:bg-primary-700">
                                                    Verify
                                                </button>
                                            </div>
                                        </div>
                                    </form>

                                </div>

                                {{-- Task Completion Card --}}
                                <div class="rounded-xl border border-neutral-200 bg-white p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 dark:border-neutral-800 dark:bg-neutral-900">
                                    <div>
                                        <h5 class="text-sm font-bold text-neutral-900 dark:text-neutral-100">Finalize &amp; Post Inventory Movement</h5>
                                        <p class="text-xs text-neutral-500 dark:text-neutral-400">Requires verified scan sequence. Decrements source and increments destination.</p>
                                    </div>
                                    <form method="POST" action="{{ route('inventory.warehouse-tasks.complete', $task) }}" class="flex items-center gap-2">
                                        @csrf
                                        <input type="number" name="quantity" min="1" max="{{ $task->remainingQuantity() }}" value="{{ $task->remainingQuantity() }}" class="w-24 rounded-lg border-neutral-300 text-sm font-bold dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                                        <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 shadow-sm">
                                            Complete Task
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>

                @else
                    {{-- Queue Cleared / Standby Universal Scanner Card --}}
                    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 space-y-6">
                        
                        {{-- Clean Queue Banner --}}
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-5 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                            <div class="flex items-start gap-4">
                                <div class="rounded-xl bg-emerald-100 p-2.5 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300 shrink-0">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-base font-bold text-emerald-950 dark:text-emerald-200">All Assigned Execution Tasks Cleared</h3>
                                    <p class="mt-1 text-xs text-emerald-800/90 dark:text-emerald-300/80 leading-relaxed">
                                        There are no pending putaway, picking, or transfer tasks waiting in the workstation queue. Newly created jobs from dock receiving, store requisitions, or stock movements will appear here automatically.
                                    </p>
                                    <div class="mt-3 flex flex-wrap items-center gap-2.5">
                                        <a href="{{ route('inventory.warehouse-tasks.index') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                            View Tasks Board
                                        </a>
                                        <a href="{{ route('inventory.receiving.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                            Inbound Receiving
                                        </a>
                                        <a href="{{ route('inventory.warehousing.dashboard') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                            SWS Dashboard
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Standby Barcode & Location Scanner Terminal --}}
                        <div class="rounded-xl border border-neutral-200 bg-neutral-50/70 p-5 dark:border-neutral-800 dark:bg-neutral-800/40">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h4 class="text-sm font-bold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                                        <svg class="h-4 w-4 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                        <span>Direct Barcode &amp; Location Scanner Terminal</span>
                                    </h4>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Scan any location bin, pallet, or product packaging barcode to look up details or verify barcodes.</p>
                                </div>
                                <span class="rounded-full bg-primary-100 px-2.5 py-0.5 text-[11px] font-semibold text-primary-700 dark:bg-primary-950 dark:text-primary-300">Standby Ready</span>
                            </div>

                            {{-- Scanner Input & Camera Button --}}
                            <div class="space-y-2">
                                <label for="standby_scan_input" class="text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:text-neutral-400">
                                    Ready for Barcode / QR Scanner Input
                                </label>
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <div class="relative flex-1">
                                        <input
                                            type="text"
                                            id="standby_scan_input"
                                            x-model="scanInput"
                                            placeholder="Scan location barcode (LOC-...), item SKU, or GS1 DataMatrix..."
                                            class="w-full rounded-xl border border-neutral-300 bg-white font-mono text-sm font-semibold shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
                                            @keydown.enter.prevent="lookupBarcode(scanInput)"
                                        />
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-ui.camera-scanner
                                            id="camera-scanner-standby"
                                            target-input-id="standby_scan_input"
                                            button-text="Open Camera"
                                            button-variant="secondary"
                                            title="Scan Location or Item Barcode"
                                            hint="Point camera at any 1D/2D barcode label (Bin, Shelf, SKU, or GS1 DataMatrix)."
                                        />
                                        <button
                                            type="button"
                                            @click="lookupBarcode(scanInput)"
                                            :disabled="lookupLoading || !scanInput.trim()"
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-neutral-900 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-neutral-700 dark:hover:bg-neutral-600"
                                        >
                                            <span x-show="lookupLoading" class="h-3 w-3 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                                            <span x-text="lookupLoading ? 'Looking up...' : 'Lookup'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Live Lookup Result Box --}}
                            <div x-show="lookupResult" x-cloak class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-xs dark:border-emerald-800/80 dark:bg-emerald-950/40">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-800 dark:bg-emerald-900/80 dark:text-emerald-200" x-text="lookupResult?.type"></span>
                                            <span class="font-mono text-xs font-black text-neutral-900 dark:text-neutral-100" x-text="lookupResult?.code || lookupResult?.sku || lookupResult?.task_number || lookupResult?.batch_number"></span>
                                            <template x-if="lookupResult?.location_status === 'inactive' || lookupResult?.item_status === 'archived'">
                                                <span class="rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300" x-text="lookupResult?.location_status === 'inactive' ? 'INACTIVE STORAGE' : 'ARCHIVED'"></span>
                                            </template>
                                        </div>
                                        <p class="text-xs font-semibold text-neutral-800 dark:text-neutral-200" x-text="lookupResult?.name || lookupResult?.message"></p>
                                        <template x-if="lookupResult?.warning">
                                            <p class="mt-1 text-[11px] font-medium text-amber-800 dark:text-amber-300" x-text="lookupResult?.warning"></p>
                                        </template>
                                        <template x-if="lookupResult?.parsed && (lookupResult.parsed.gtin || lookupResult.parsed.batch || lookupResult.parsed.expiry || lookupResult.parsed.serial)">
                                            <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-[11px] text-neutral-600 dark:text-neutral-300 sm:grid-cols-4">
                                                <div x-show="lookupResult.parsed.gtin" class="min-w-0">
                                                    <dt class="font-semibold text-neutral-500 dark:text-neutral-400">GTIN</dt>
                                                    <dd class="truncate font-mono" :title="lookupResult.parsed.gtin" x-text="lookupResult.parsed.gtin"></dd>
                                                </div>
                                                <div x-show="lookupResult.parsed.batch" class="min-w-0">
                                                    <dt class="font-semibold text-neutral-500 dark:text-neutral-400">Lot / Batch</dt>
                                                    <dd class="truncate font-mono" :title="lookupResult.parsed.batch" x-text="lookupResult.parsed.batch"></dd>
                                                </div>
                                                <div x-show="lookupResult.parsed.expiry" class="min-w-0">
                                                    <dt class="font-semibold text-neutral-500 dark:text-neutral-400">Expiry</dt>
                                                    <dd class="font-mono" x-text="lookupResult.parsed.expiry"></dd>
                                                </div>
                                                <div x-show="lookupResult.parsed.serial" class="min-w-0">
                                                    <dt class="font-semibold text-neutral-500 dark:text-neutral-400">Serial</dt>
                                                    <dd class="truncate font-mono" :title="lookupResult.parsed.serial" x-text="lookupResult.parsed.serial"></dd>
                                                </div>
                                            </dl>
                                        </template>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <template x-if="lookupResult?.redirect_url">
                                            <a :href="lookupResult?.redirect_url" class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-emerald-500">
                                                Open Details &rarr;
                                            </a>
                                        </template>
                                        <button type="button" @click="lookupResult = null" class="rounded-lg p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Lookup Error Alert --}}
                            <div x-show="lookupError" x-cloak class="mt-4 flex items-start justify-between gap-3 rounded-xl border border-red-200 bg-red-50 p-3.5 text-xs text-red-800 dark:border-red-800/80 dark:bg-red-950/50 dark:text-red-300">
                                <div class="flex items-start gap-2">
                                    <svg class="h-4 w-4 text-red-600 dark:text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <span x-text="lookupError"></span>
                                </div>
                                <button type="button" @click="lookupError = null" class="rounded p-0.5 text-red-500 hover:text-red-700 dark:hover:text-red-200">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            {{-- Format Guidance Strip --}}
                            <div class="mt-4 grid gap-2 sm:grid-cols-3 pt-3 border-t border-neutral-200/80 dark:border-neutral-700/60">
                                <div class="rounded-lg bg-white p-2.5 border border-neutral-200/60 dark:border-neutral-700/60 dark:bg-neutral-800/80">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">1. GS1 DataMatrix</span>
                                    <p class="text-xs font-mono font-semibold text-neutral-800 dark:text-neutral-200 mt-0.5 truncate">(01) GTIN + (17) Exp + (10) Lot</p>
                                </div>
                                <div class="rounded-lg bg-white p-2.5 border border-neutral-200/60 dark:border-neutral-700/60 dark:bg-neutral-800/80">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">2. Storage Bins</span>
                                    <p class="text-xs font-mono font-semibold text-neutral-800 dark:text-neutral-200 mt-0.5 truncate">LOC-BAY-01 / WARD-03</p>
                                </div>
                                <div class="rounded-lg bg-white p-2.5 border border-neutral-200/60 dark:border-neutral-700/60 dark:bg-neutral-800/80">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400 dark:text-neutral-500">3. Product SKU / UPC</span>
                                    <p class="text-xs font-mono font-semibold text-neutral-800 dark:text-neutral-200 mt-0.5 truncate">PHARMA-PARA-500</p>
                                </div>
                            </div>
                        </div>

                    </div>
                @endif

                {{-- Recent Warehouse Tasks Registry (Always Visible to fill space with real operational data) --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                                <svg class="h-4 w-4 text-neutral-500 dark:text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                Recent Warehouse Tasks Registry
                            </h3>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">Putaway, picking, and relocation execution history</p>
                        </div>
                        <a href="{{ route('inventory.warehouse-tasks.index') }}" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
                            View All Tasks &rarr;
                        </a>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
                        <table class="min-w-full divide-y divide-neutral-200 text-left text-xs dark:divide-neutral-800">
                            <thead class="bg-neutral-50 font-semibold uppercase tracking-wider text-neutral-500 dark:bg-neutral-800/50 dark:text-neutral-400">
                                <tr>
                                    <th class="px-3.5 py-2.5">Task #</th>
                                    <th class="px-3.5 py-2.5">Type</th>
                                    <th class="px-3.5 py-2.5">Item &amp; Qty</th>
                                    <th class="px-3.5 py-2.5">Route</th>
                                    <th class="px-3.5 py-2.5">Status</th>
                                    <th class="px-3.5 py-2.5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100 bg-white font-medium dark:divide-neutral-800 dark:bg-neutral-900">
                                @forelse($recentTasks as $task)
                                    <tr class="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40">
                                        <td class="px-3.5 py-2.5 whitespace-nowrap">
                                            <div class="font-mono font-bold text-neutral-900 dark:text-neutral-100">{{ $task->task_number }}</div>
                                            <div class="text-[10px] text-neutral-400 font-mono">{{ $task->created_at?->format('M d, H:i') }}</div>
                                        </td>
                                        <td class="px-3.5 py-2.5 whitespace-nowrap">
                                            <span class="inline-flex rounded px-1.5 py-0.5 text-[11px] font-bold uppercase
                                                @if($task->task_type->value === 'put_away') bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300
                                                @elseif($task->task_type->value === 'pick') bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300
                                                @else bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300 @endif">
                                                {{ $task->task_type->label() }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-2.5">
                                            <div class="truncate max-w-[180px] font-semibold text-neutral-800 dark:text-neutral-200">{{ $task->item?->name ?? 'N/A' }}</div>
                                            <div class="text-[11px] text-neutral-500 dark:text-neutral-400">{{ $task->requested_quantity }} {{ $task->item?->unit ?? 'units' }}</div>
                                        </td>
                                        <td class="px-3.5 py-2.5 whitespace-nowrap text-[11px] font-mono text-neutral-600 dark:text-neutral-400">
                                            <span class="text-neutral-700 dark:text-neutral-300">{{ $task->sourceLocation?->code ?? 'Dock' }}</span>
                                            <span class="text-neutral-400 mx-1">&rarr;</span>
                                            <span class="text-neutral-700 dark:text-neutral-300">{{ $task->destinationLocation?->code ?? 'Staging' }}</span>
                                        </td>
                                        <td class="px-3.5 py-2.5 whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold
                                                @if($task->status->value === 'completed') bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300
                                                @elseif($task->status->value === 'in_progress') bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300
                                                @elseif($task->status->value === 'cancelled') bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400
                                                @else bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 @endif">
                                                {{ $task->status->label() }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-2.5 text-right whitespace-nowrap">
                                            <a href="{{ route('inventory.warehouse-tasks.show', $task) }}" class="font-semibold text-primary-600 hover:text-primary-800 dark:text-primary-400">
                                                Details &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-xs text-neutral-500 dark:text-neutral-400">
                                            No warehouse tasks recorded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            {{-- Right Column (1/3): Live Scan Feed & Guidelines --}}
            <div class="space-y-6">

                {{-- Recent Scans On Station Card --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Recent Scans On Station</h4>
                        <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-mono font-bold text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                            {{ $recentScans->count() }} logged
                        </span>
                    </div>

                    <div class="max-h-[380px] overflow-y-auto divide-y divide-neutral-100 dark:divide-neutral-800 pr-1 space-y-1">
                        @forelse($recentScans as $scan)
                            <div class="py-2.5 flex items-start gap-2.5">
                                <span class="mt-1 h-2 w-2 rounded-full shrink-0 @if($scan->outcome === 'accepted') bg-emerald-500 @elseif($scan->outcome === 'identified') bg-amber-500 @else bg-red-500 @endif"></span>
                                <div class="truncate flex-1">
                                    <div class="font-mono text-xs font-bold text-neutral-800 dark:text-neutral-200 truncate">{{ $scan->raw_value }}</div>
                                    <div class="text-[11px] text-neutral-500 dark:text-neutral-400 truncate">{{ $scan->message }}</div>
                                    <div class="text-[10px] text-neutral-400 font-mono">{{ $scan->created_at?->diffForHumans() }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-neutral-400 py-4 text-center">No scans recorded yet.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Scanning Best Practices Card --}}
                <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-5 dark:border-neutral-800 dark:bg-neutral-800/50">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-2.5 flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Scanning Best Practices
                    </h4>
                    <ul class="text-xs text-neutral-600 dark:text-neutral-300 space-y-2 list-disc pl-4 leading-relaxed">
                        <li>Keep barcode labels clean, dry, and flat during scanning.</li>
                        <li>For GS1 DataMatrix, ensure the entire 2D matrix is within the aimer reticle.</li>
                        <li>The terminal automatically parses GTIN (01), Expiry (17), Lot (10), and Serial (21).</li>
                        <li>Scanning an incorrect location or product immediately logs an auditable exception ticket.</li>
                    </ul>
                </div>

                {{-- Station Diagnostics Card --}}
                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 mb-2">Terminal Diagnostics</h4>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex items-center justify-between text-neutral-600 dark:text-neutral-400">
                            <span>Decoder:</span>
                            <span class="font-mono text-neutral-900 dark:text-neutral-200 font-semibold">ZXing / Native BarcodeDetector</span>
                        </div>
                        <div class="flex items-center justify-between text-neutral-600 dark:text-neutral-400">
                            <span>Hardware Wedge:</span>
                            <span class="font-mono text-neutral-900 dark:text-neutral-200 font-semibold">HID Keyboard Emulation</span>
                        </div>
                        <div class="flex items-center justify-between text-neutral-600 dark:text-neutral-400">
                            <span>Audit Trail:</span>
                            <span class="font-mono text-emerald-600 dark:text-emerald-400 font-semibold">Append-Only Active</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
</x-app-layout>
