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
            @if(session('notice'))
                <x-ui.alert variant="info" :message="session('notice')" />
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

                                {{-- Visual Scan Sequence Steps with Scannable QR Codes --}}
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-xs font-semibold uppercase text-neutral-500 tracking-wider">Scan Verification Progression</h4>
                                        <span class="text-xs font-medium @if($activeStepNum === 4) text-emerald-600 @else text-primary-700 @endif">
                                            @if($activeStepNum === 4)
                                                ✓ All 3 scan steps verified
                                            @else
                                                Waiting for Step {{ $activeStepNum }} of 3
                                            @endif
                                        </span>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-3">
                                        {{-- Step 1: Source Location --}}
                                        <div x-data="{ showQr: false }" class="rounded-xl border p-3.5 transition-all @if($step1Done) bg-emerald-50/70 border-emerald-300 text-emerald-950 @elseif($activeStepNum === 1) bg-primary-50/70 border-primary-400 ring-2 ring-primary-500/20 text-neutral-900 shadow-sm @else bg-neutral-50/50 border-neutral-200 text-neutral-500 @endif">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[10px] font-extrabold uppercase tracking-wider @if($step1Done) text-emerald-700 @elseif($activeStepNum === 1) text-primary-700 @else text-neutral-400 @endif">
                                                    Step 1: Source Location
                                                </span>
                                                @if($step1Done)
                                                    <span class="inline-flex items-center gap-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg> Done
                                                    </span>
                                                @elseif($activeStepNum === 1)
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-primary-600 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm animate-pulse">
                                                        Scan Now
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="font-mono text-sm font-bold mt-1.5 truncate">{{ $sourceVal ?: 'N/A' }}</div>
                                            <div class="text-[11px] text-neutral-500 truncate">{{ $task->sourceLocation?->name }}</div>

                                            @if($sourceQr)
                                                <div class="mt-2.5 pt-2 border-t border-neutral-200/60 flex items-center justify-between">
                                                    <button type="button" @click="showQr = true" class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary-600 hover:text-primary-800 hover:underline">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                                        Show QR Code
                                                    </button>
                                                    <span class="text-[10px] text-neutral-400 font-mono">Location</span>
                                                </div>

                                                {{-- QR Code Modal --}}
                                                <div x-show="showQr" @click.outside="showQr = false" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                                                    <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl text-center">
                                                        <h3 class="text-base font-bold text-neutral-900">Step 1: Source Location QR</h3>
                                                        <p class="mt-1 text-xs text-neutral-500">Scan this code with your camera or phone</p>
                                                        <div class="mt-4 flex justify-center">
                                                            <img src="{{ $sourceQr }}" alt="Source Location QR" class="h-56 w-56 rounded-xl border border-neutral-200 p-2 shadow-sm bg-white">
                                                        </div>
                                                        <p class="mt-3 font-mono text-sm font-bold text-neutral-800">{{ $sourceVal }}</p>
                                                        <p class="text-xs text-neutral-500">{{ $task->sourceLocation?->name }}</p>
                                                        <button type="button" @click="showQr = false" class="mt-5 w-full rounded-xl bg-neutral-800 py-2 text-sm font-semibold text-white hover:bg-neutral-900">
                                                            Close QR Code
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Step 2: Item / GS1 Lot --}}
                                        <div x-data="{ showQr: false }" class="rounded-xl border p-3.5 transition-all @if($step2Done) bg-emerald-50/70 border-emerald-300 text-emerald-950 @elseif($activeStepNum === 2) bg-primary-50/70 border-primary-400 ring-2 ring-primary-500/20 text-neutral-900 shadow-sm @else bg-neutral-50/50 border-neutral-200 text-neutral-500 @endif">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[10px] font-extrabold uppercase tracking-wider @if($step2Done) text-emerald-700 @elseif($activeStepNum === 2) text-primary-700 @else text-neutral-400 @endif">
                                                    Step 2: Item / GS1 Lot
                                                </span>
                                                @if($step2Done)
                                                    <span class="inline-flex items-center gap-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg> Done
                                                    </span>
                                                @elseif($activeStepNum === 2)
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-primary-600 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm animate-pulse">
                                                        Scan Now
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="font-mono text-sm font-bold mt-1.5 truncate">{{ $itemVal ?: 'N/A' }}</div>
                                            <div class="text-[11px] text-neutral-500 truncate">Lot: {{ $task->batch?->batch_number ?? 'Any lot' }}</div>

                                            @if($itemQr)
                                                <div class="mt-2.5 pt-2 border-t border-neutral-200/60 flex items-center justify-between">
                                                    <button type="button" @click="showQr = true" class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary-600 hover:text-primary-800 hover:underline">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                                        Show QR Code
                                                    </button>
                                                    <span class="text-[10px] text-neutral-400 font-mono">Product</span>
                                                </div>

                                                {{-- QR Code Modal --}}
                                                <div x-show="showQr" @click.outside="showQr = false" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                                                    <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl text-center">
                                                        <h3 class="text-base font-bold text-neutral-900">Step 2: Product QR Code</h3>
                                                        <p class="mt-1 text-xs text-neutral-500">Scan this code with your camera or phone</p>
                                                        <div class="mt-4 flex justify-center">
                                                            <img src="{{ $itemQr }}" alt="Product QR Code" class="h-56 w-56 rounded-xl border border-neutral-200 p-2 shadow-sm bg-white">
                                                        </div>
                                                        <p class="mt-3 font-mono text-sm font-bold text-neutral-800">{{ $itemVal }}</p>
                                                        <p class="text-xs text-neutral-500">{{ $task->item?->name }}</p>
                                                        <button type="button" @click="showQr = false" class="mt-5 w-full rounded-xl bg-neutral-800 py-2 text-sm font-semibold text-white hover:bg-neutral-900">
                                                            Close QR Code
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Step 3: Destination Location --}}
                                        <div x-data="{ showQr: false }" class="rounded-xl border p-3.5 transition-all @if($step3Done) bg-emerald-50/70 border-emerald-300 text-emerald-950 @elseif($activeStepNum === 3) bg-primary-50/70 border-primary-400 ring-2 ring-primary-500/20 text-neutral-900 shadow-sm @else bg-neutral-50/50 border-neutral-200 text-neutral-500 @endif">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[10px] font-extrabold uppercase tracking-wider @if($step3Done) text-emerald-700 @elseif($activeStepNum === 3) text-primary-700 @else text-neutral-400 @endif">
                                                    Step 3: Destination
                                                </span>
                                                @if($step3Done)
                                                    <span class="inline-flex items-center gap-1 rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg> Done
                                                    </span>
                                                @elseif($activeStepNum === 3)
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-primary-600 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm animate-pulse">
                                                        Scan Now
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="font-mono text-sm font-bold mt-1.5 truncate">{{ $destVal ?: 'N/A' }}</div>
                                            <div class="text-[11px] text-neutral-500 truncate">{{ $task->destinationLocation?->name }}</div>

                                            @if($destQr)
                                                <div class="mt-2.5 pt-2 border-t border-neutral-200/60 flex items-center justify-between">
                                                    <button type="button" @click="showQr = true" class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary-600 hover:text-primary-800 hover:underline">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                                                        Show QR Code
                                                    </button>
                                                    <span class="text-[10px] text-neutral-400 font-mono">Location</span>
                                                </div>

                                                {{-- QR Code Modal --}}
                                                <div x-show="showQr" @click.outside="showQr = false" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                                                    <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl text-center">
                                                        <h3 class="text-base font-bold text-neutral-900">Step 3: Destination Location QR</h3>
                                                        <p class="mt-1 text-xs text-neutral-500">Scan this code with your camera or phone</p>
                                                        <div class="mt-4 flex justify-center">
                                                            <img src="{{ $destQr }}" alt="Destination Location QR" class="h-56 w-56 rounded-xl border border-neutral-200 p-2 shadow-sm bg-white">
                                                        </div>
                                                        <p class="mt-3 font-mono text-sm font-bold text-neutral-800">{{ $destVal }}</p>
                                                        <p class="text-xs text-neutral-500">{{ $task->destinationLocation?->name }}</p>
                                                        <button type="button" @click="showQr = false" class="mt-5 w-full rounded-xl bg-neutral-800 py-2 text-sm font-semibold text-white hover:bg-neutral-900">
                                                            Close QR Code
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
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
                                                <span>Ready for Barcode / QR Scanner Input</span>
                                                <span class="text-xs font-normal text-primary-700 ml-1">
                                                    @if($activeStepNum <= 3)
                                                        &bull; Expecting Step {{ $activeStepNum }}: {{ $activeStepInfo['title'] }}
                                                    @else
                                                        &bull; ✓ All Steps Complete
                                                    @endif
                                                </span>
                                            </label>
                                            <span class="text-xs text-neutral-500 font-mono">Camera / Laser Active</span>
                                        </div>

                                        <div class="flex flex-col sm:flex-row gap-2">
                                            <div class="relative flex-1">
                                                <input type="text" id="scan_value_{{ $task->id }}" name="scan_value" autofocus required placeholder="{{ $activeStepNum <= 3 ? 'Waiting for Step '.$activeStepNum.': Scan '.$activeStepInfo['title'].' ('.$activeStepInfo['code'].')...' : 'All steps verified' }}" class="w-full rounded-xl border-neutral-300 font-mono text-base font-semibold shadow-sm focus:border-primary-500 focus:ring-primary-500">
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

                                    {{-- Fast Simulator Buttons for Rapid Testing --}}
                                    <div class="mt-4 border-t border-primary-200/60 pt-3">
                                        <p class="text-[11px] font-semibold text-neutral-500 uppercase tracking-wider mb-2">1-Click Test Shortcuts (or use Camera Scanner)</p>
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if($task->sourceLocation)
                                                <form method="POST" action="{{ route('inventory.warehouse-tasks.scan', $task) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="scan_value" value="{{ $sourceVal }}">
                                                    <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-mono border transition-all @if($activeStepNum === 1) bg-primary-600 text-white font-bold border-primary-700 ring-2 ring-primary-500/30 shadow-sm @else bg-white text-neutral-700 border-neutral-300 hover:bg-neutral-50 @endif">
                                                        @if($activeStepNum === 1) 👉 @endif Step 1: Scan Source ({{ $task->sourceLocation->code }})
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('inventory.warehouse-tasks.scan', $task) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="scan_value" value="{{ $itemVal }}">
                                                <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-mono border transition-all @if($activeStepNum === 2) bg-primary-600 text-white font-bold border-primary-700 ring-2 ring-primary-500/30 shadow-sm @else bg-white text-neutral-700 border-neutral-300 hover:bg-neutral-50 @endif">
                                                    @if($activeStepNum === 2) 👉 @endif Step 2: Scan Item ({{ $task->item?->sku }})
                                                </button>
                                            </form>
                                            @if($task->destinationLocation)
                                                <form method="POST" action="{{ route('inventory.warehouse-tasks.scan', $task) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="scan_value" value="{{ $destVal }}">
                                                    <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-mono border transition-all @if($activeStepNum === 3) bg-primary-600 text-white font-bold border-primary-700 ring-2 ring-primary-500/30 shadow-sm @else bg-white text-neutral-700 border-neutral-300 hover:bg-neutral-50 @endif">
                                                        @if($activeStepNum === 3) 👉 @endif Step 3: Scan Dest ({{ $task->destinationLocation->code }})
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('inventory.warehouse-tasks.scan', $task) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="scan_value" value="INVALID-BARCODE-999">
                                                <button type="submit" class="rounded-lg bg-rose-50 text-rose-700 px-2.5 py-1.5 text-xs font-mono border border-rose-200 hover:bg-rose-100">
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
                                    <span class="mt-1 h-2 w-2 rounded-full shrink-0 @if($scan->outcome === 'accepted') bg-emerald-500 @elseif($scan->outcome === 'identified') bg-amber-500 @else bg-red-500 @endif"></span>
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
