<x-app-layout>
    <div class="space-y-3.5" x-data="itemAlertManager()" x-on:open-manage-modal.window="openModal($event.detail.item, $event.detail.alertType)">
        {{-- Page Header --}}
        <x-ui.page-header
            title="Reorder Alerts & Inventory Notices"
            :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Inventory' => route('inventory.items'), 'Stock Alerts' => null]"
        />

        {{-- Consolidated Inventory Workflow Navigation --}}
        @include('inventory.partials.workflow_nav')

        {{-- Top Summary Metric Cards --}}
        <div class="grid gap-3.5 sm:gap-4 grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
            {{-- Card 1: Out of Stock (Critical Shortage) --}}
            <div class="rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-neutral-300 dark:hover:border-neutral-700 transition-all duration-150">
                {{-- Zone 1: Header --}}
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-rose-700 dark:text-rose-300">Out of Stock</p>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-950/80 dark:text-rose-300 dark:ring-rose-800/50">
                        <x-ui.icon name="x-mark" class="h-5 w-5" />
                    </span>
                </div>
                {{-- Zone 2: Primary Value --}}
                <div class="mt-3 flex items-baseline gap-1.5">
                    <span id="stat-out-of-stock" class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-rose-600 dark:text-rose-400">—</span>
                    <span class="text-sm sm:text-base font-bold text-rose-500/80 dark:text-rose-400/80">items</span>
                </div>
                {{-- Zone 3: Footer --}}
                <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                    <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate">Zero on hand available</span>
                </div>
            </div>

            {{-- Card 2: Low Stock Warnings (Reorder Trigger) --}}
            <div class="rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-neutral-300 dark:hover:border-neutral-700 transition-all duration-150">
                {{-- Zone 1: Header --}}
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">Low Stock Warnings</p>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950/80 dark:text-amber-300 dark:ring-amber-800/50">
                        <x-ui.icon name="bell-alert" class="h-5 w-5" />
                    </span>
                </div>
                {{-- Zone 2: Primary Value --}}
                <div class="mt-3 flex items-baseline gap-1.5">
                    <span id="stat-low-stock" class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-amber-600 dark:text-amber-400">—</span>
                    <span class="text-sm sm:text-base font-bold text-amber-600/80 dark:text-amber-400/80">items</span>
                </div>
                {{-- Zone 3: Footer --}}
                <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                    <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate">Below reorder threshold</span>
                </div>
            </div>

            {{-- Card 3: Active Expiring Batches --}}
            <div class="rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-neutral-300 dark:hover:border-neutral-700 transition-all duration-150">
                {{-- Zone 1: Header --}}
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">Expiring Soon</p>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 ring-1 ring-indigo-200 dark:bg-indigo-950/80 dark:text-indigo-300 dark:ring-indigo-800/50">
                        <x-ui.icon name="clock" class="h-5 w-5" />
                    </span>
                </div>
                {{-- Zone 2: Primary Value --}}
                <div class="mt-3 flex items-baseline gap-1.5">
                    <span id="stat-expiring" class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-indigo-600 dark:text-indigo-400">—</span>
                    <span class="text-sm sm:text-base font-bold text-indigo-600/80 dark:text-indigo-400/80">batches</span>
                </div>
                {{-- Zone 3: Footer --}}
                <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                    <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate">1&ndash;90 days remaining, including critical</span>
                </div>
            </div>

            {{-- Card 4: Expired Batches --}}
            <div class="rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs dark:border-neutral-800 dark:bg-neutral-900/95 flex flex-col justify-between hover:border-neutral-300 dark:hover:border-neutral-700 transition-all duration-150">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm font-bold uppercase tracking-wider text-rose-700 dark:text-rose-300">Expired Batches</p>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-950/80 dark:text-rose-300 dark:ring-rose-800/50">
                        <x-ui.icon name="exclamation-triangle" class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-1.5">
                    <span id="stat-expired" class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight tabular-nums text-rose-600 dark:text-rose-400">&mdash;</span>
                    <span class="text-sm sm:text-base font-bold text-rose-500/80 dark:text-rose-400/80">batches</span>
                </div>
                <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
                    <span class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-300 truncate">0 days remaining or past due</span>
                </div>
            </div>
        </div>

        {{-- Main Alerts Section (Viewport-Fitting with Internal Scroll) --}}
        <x-ui.card :padding="false">
            <x-slot:header>
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Live Inventory Attention Queue</h2>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Automated reorder triggers and clinical stock surveillance.</p>
                </div>
            </x-slot:header>

            <x-slot:actions>
                <x-ui.loader id="alerts-api-status" size="sm" label="Scanning stock levels..." class="text-xs text-neutral-500 dark:text-neutral-400" />
            </x-slot:actions>

            {{-- Viewport-Constrained Scrollable Body --}}
            <div class="p-4 sm:p-5 overflow-y-auto max-h-[calc(100vh-21rem)] min-h-[280px]">
                <div id="alerts-list" class="grid gap-4 grid-cols-1">
                    {{-- Skeleton Loading Placeholder --}}
                    <div class="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/70 dark:bg-neutral-800/30 p-4 space-y-3 animate-pulse">
                        <div class="h-4 bg-neutral-200 dark:bg-neutral-700 rounded w-1/4"></div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="h-28 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700"></div>
                            <div class="h-28 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700"></div>
                            <div class="h-28 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700"></div>
                            <div class="h-28 bg-white dark:bg-neutral-800 rounded-xl border border-neutral-200 dark:border-neutral-700"></div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui.card>

        {{-- Manage Item Alert & Replenishment Quick-Action Modal --}}
        <div
            x-show="open"
            x-cloak
            x-on:keydown.escape.window="closeModal()"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="manage-alert-modal-title"
        >
            {{-- Backdrop --}}
            <div
                x-show="open"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-on:click="closeModal()"
                class="fixed inset-0 bg-neutral-950/75 backdrop-blur-xs transition-opacity"
                aria-hidden="true"
            ></div>

            {{-- Modal Box --}}
            <div
                x-show="open"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-3 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-3 sm:translate-y-0 sm:scale-95"
                class="relative w-full max-w-2xl max-h-[calc(100dvh-2.5rem)] flex flex-col rounded-2xl bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 shadow-2xl overflow-hidden z-10"
            >
                {{-- Modal Header with Alert Accent Background --}}
                <div
                    class="flex items-start justify-between gap-3 px-5 py-4 border-b transition-colors"
                    :class="{
                        'border-rose-200 dark:border-rose-900/60 bg-rose-50/60 dark:bg-rose-950/30': isOut || alertType === 'out_of_stock',
                        'border-amber-200 dark:border-amber-900/60 bg-amber-50/60 dark:bg-amber-950/30': !isOut && alertType === 'low_stock',
                        'border-indigo-200 dark:border-indigo-900/60 bg-indigo-50/60 dark:bg-indigo-950/30': alertType === 'near_expiry'
                    }"
                >
                    <div class="flex items-start gap-3 min-w-0 flex-1">
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1"
                            :class="{
                                'bg-rose-100 dark:bg-rose-900/70 text-rose-600 dark:text-rose-300 ring-rose-200 dark:ring-rose-800/60': isOut || alertType === 'out_of_stock',
                                'bg-amber-100 dark:bg-amber-900/70 text-amber-600 dark:text-amber-300 ring-amber-200 dark:ring-amber-800/60': !isOut && alertType === 'low_stock',
                                'bg-indigo-100 dark:bg-indigo-900/70 text-indigo-600 dark:text-indigo-300 ring-indigo-200 dark:ring-indigo-800/60': alertType === 'near_expiry'
                            }"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 id="manage-alert-modal-title" class="text-base font-bold text-neutral-900 dark:text-neutral-100 truncate" x-text="item?.name || 'Item Management'"></h3>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-bold ring-1 ring-inset shrink-0"
                                    :class="{
                                        'bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 ring-rose-300 dark:ring-rose-800/60': isOut || alertType === 'out_of_stock',
                                        'bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300 ring-amber-300 dark:ring-amber-800/60': !isOut && alertType === 'low_stock',
                                        'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300 ring-indigo-300 dark:ring-indigo-800/60': alertType === 'near_expiry'
                                    }"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full animate-pulse"
                                        :class="{
                                            'bg-rose-500': isOut || alertType === 'out_of_stock',
                                            'bg-amber-500': !isOut && alertType === 'low_stock',
                                            'bg-indigo-500': alertType === 'near_expiry'
                                        }"
                                    ></span>
                                    <span x-text="alertType === 'near_expiry' ? (item?.expiry_status_label || 'EXPIRING SOON') : (isOut ? 'OUT OF STOCK' : 'LOW STOCK')"></span>
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs font-mono text-neutral-500 dark:text-neutral-400 truncate">
                                SKU: <span class="font-semibold text-neutral-700 dark:text-neutral-300" x-text="item?.sku || 'N/A'"></span>
                                <span x-show="item?.category?.name" x-text="' · ' + (item?.category?.name || '')"></span>
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        x-on:click="closeModal()"
                        class="rounded-lg p-1.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors focus:outline-hidden"
                        title="Close modal"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Modal Scrollable Body --}}
                <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4">
                    {{-- Alert Diagnostic Banner --}}
                    <div
                        class="rounded-xl border p-3.5 text-xs flex items-start gap-3 shadow-2xs"
                        :class="{
                            'border-rose-200 bg-rose-50/70 text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200': isOut || alertType === 'out_of_stock',
                            'border-amber-200 bg-amber-50/70 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200': !isOut && alertType === 'low_stock',
                            'border-indigo-200 bg-indigo-50/70 text-indigo-900 dark:border-indigo-900/50 dark:bg-indigo-950/30 dark:text-indigo-200': alertType === 'near_expiry'
                        }"
                    >
                        <div class="shrink-0 mt-0.5">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <div class="font-bold text-xs" x-text="alertType === 'near_expiry' ? (item?.expiry_status_label || 'Expiring Soon') : (isOut ? 'Critical Depleted Stock' : 'Reorder Threshold Triggered')"></div>
                            <p class="mt-0.5 leading-relaxed text-[11px] opacity-90" x-show="isOut || alertType === 'out_of_stock'">
                                Physical stock is completely exhausted (0 on hand). Immediate purchase requisition or internal stock transfer is recommended to maintain clinical readiness.
                            </p>
                            <p class="mt-0.5 leading-relaxed text-[11px] opacity-90" x-show="!isOut && alertType === 'low_stock'">
                                Available inventory has fallen below the established safety threshold. Prompt restocking is advised to prevent stockouts during surge periods.
                            </p>
                            <p class="mt-0.5 leading-relaxed text-[11px] opacity-90" x-show="alertType === 'near_expiry'">
                                This inventory batch has 1&ndash;90 days remaining. Batches with 1&ndash;30 days are Critical / Near Expiry. Prioritize FEFO (First-Expired, First-Out) dispensing or initiate return/exchange protocols.
                            </p>
                        </div>
                    </div>

                    {{-- 4-Cell Key Metrics Grid --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        {{-- On Hand --}}
                        <div class="rounded-xl border border-neutral-200/90 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-800/40 p-3 text-center">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">On Hand</span>
                            <span class="mt-1 block text-lg sm:text-xl font-black tabular-nums"
                                  :class="isOut ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400'">
                                <span x-text="item?.quantity_on_hand ?? 0"></span>
                                <span class="text-xs font-normal text-neutral-400" x-text="item?.unit || 'units'"></span>
                            </span>
                            <span class="mt-0.5 block text-[10px] text-neutral-400">Physical count</span>
                        </div>

                        {{-- Reorder Level --}}
                        <div class="rounded-xl border border-neutral-200/90 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-800/40 p-3 text-center">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Reorder At</span>
                            <span class="mt-1 block text-lg sm:text-xl font-bold text-neutral-800 dark:text-neutral-200 tabular-nums">
                                <span x-text="item?.reorder_level || item?.reorder_point || 0"></span>
                                <span class="text-xs font-normal text-neutral-400" x-text="item?.unit || 'units'"></span>
                            </span>
                            <span class="mt-0.5 block text-[10px] text-neutral-400">Safety threshold</span>
                        </div>

                        {{-- Deficit or Time Remaining --}}
                        <div class="rounded-xl border border-neutral-200/90 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-800/40 p-3 text-center">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400" x-text="alertType === 'near_expiry' ? 'Time Remaining' : 'Shortage Deficit'"></span>
                            <span class="mt-1 block text-lg sm:text-xl font-black tabular-nums"
                                  :class="isOut || alertType === 'out_of_stock' ? 'text-rose-600 dark:text-rose-400' : (alertType === 'near_expiry' ? 'text-indigo-600 dark:text-indigo-400' : 'text-amber-600 dark:text-amber-400')">
                                <template x-if="alertType === 'near_expiry'">
                                    <span x-text="daysUntilExpiry !== null ? (daysUntilExpiry <= 0 ? 'Expired' : daysUntilExpiry + 'd') : 'N/A'"></span>
                                </template>
                                <template x-if="alertType !== 'near_expiry'">
                                    <span>
                                        <span x-text="deficit > 0 ? ('-' + deficit) : '0'"></span>
                                        <span class="text-xs font-normal text-neutral-400" x-text="item?.unit || 'units'"></span>
                                    </span>
                                </template>
                            </span>
                            <span class="mt-0.5 block text-[10px] text-neutral-400" x-text="alertType === 'near_expiry' ? (item?.expiry_date || 'Lot expiry') : (isOut ? 'Depleted stock' : 'Needed buffer')"></span>
                        </div>

                        {{-- Storage Location / Lot --}}
                        <div class="rounded-xl border border-neutral-200/90 dark:border-neutral-800 bg-neutral-50/80 dark:bg-neutral-800/40 p-3 text-center">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Storage / Lot</span>
                            <span class="mt-1 block text-xs sm:text-sm font-bold text-neutral-800 dark:text-neutral-200 truncate" x-text="item?.batch_number ? ('Lot ' + item.batch_number) : (item?.warehouse_name || 'Central Store')"></span>
                            <span class="mt-0.5 block text-[10px] text-neutral-400 truncate" x-text="item?.warehouse_name || 'Hospital Warehouse'"></span>
                        </div>
                    </div>

                    {{-- Item Specifications & Tracking Details --}}
                    <div class="rounded-xl border border-neutral-200/90 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-3.5 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-neutral-500 dark:text-neutral-400 font-medium">Assigned Supplier:</span>
                            <span class="font-semibold text-neutral-900 dark:text-neutral-100" x-text="item?.supplier?.name || 'No accredited supplier assigned'"></span>
                        </div>
                        <div class="flex items-center justify-between border-t border-neutral-100 dark:border-neutral-800/80 pt-2">
                            <span class="text-neutral-500 dark:text-neutral-400 font-medium">Classification:</span>
                            <span class="font-mono text-[11px] text-neutral-700 dark:text-neutral-300" x-text="(item?.storage_classification || 'general') + ' · ' + (item?.temperature_classification || 'ambient')"></span>
                        </div>
                        <div class="flex items-center justify-between border-t border-neutral-100 dark:border-neutral-800/80 pt-2">
                            <span class="text-neutral-500 dark:text-neutral-400 font-medium">Tracking Controls:</span>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <template x-if="item?.is_batch_tracked">
                                    <span class="inline-flex items-center rounded-md bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 text-[10px] font-semibold text-neutral-700 dark:text-neutral-300">Batch Tracked</span>
                                </template>
                                <template x-if="item?.is_expiry_tracked">
                                    <span class="inline-flex items-center rounded-md bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 text-[10px] font-semibold text-neutral-700 dark:text-neutral-300">Expiry Tracked</span>
                                </template>
                                <template x-if="!item?.is_batch_tracked && !item?.is_expiry_tracked">
                                    <span class="inline-flex items-center rounded-md bg-neutral-100 dark:bg-neutral-800 px-2 py-0.5 text-[10px] font-medium text-neutral-500">Standard Tracking</span>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Action Pathways / Recommended Workflows --}}
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400 mb-2.5">Available Operational Actions</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                            {{-- 1. Store Requisition --}}
                            <a
                                :href="requisitionUrl"
                                class="flex flex-col justify-between rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/60 dark:bg-neutral-800/30 p-3 hover:border-primary-500 dark:hover:border-primary-500 hover:bg-primary-50/30 dark:hover:bg-primary-950/20 transition-all group shadow-2xs"
                            >
                                <div>
                                    <div class="flex items-center gap-1.5 font-bold text-xs text-neutral-900 dark:text-neutral-100 group-hover:text-primary-600 dark:group-hover:text-primary-400">
                                        <svg class="h-4 w-4 text-neutral-400 group-hover:text-primary-600 dark:group-hover:text-primary-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <span>Store Requisition</span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400 leading-snug">Auto-select item &amp; submit replenishment.</p>
                                </div>
                                <span class="mt-2.5 text-[11px] font-semibold text-primary-600 dark:text-primary-400 inline-flex items-center gap-0.5">
                                    Create Requisition &rarr;
                                </span>
                            </a>

                            {{-- 2. Stock Adjustment --}}
                            <a
                                :href="adjustmentUrl"
                                class="flex flex-col justify-between rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/60 dark:bg-neutral-800/30 p-3 hover:border-amber-500 dark:hover:border-amber-500 hover:bg-amber-50/30 dark:hover:bg-amber-950/20 transition-all group shadow-2xs"
                            >
                                <div>
                                    <div class="flex items-center gap-1.5 font-bold text-xs text-neutral-900 dark:text-neutral-100 group-hover:text-amber-600 dark:group-hover:text-amber-400">
                                        <svg class="h-4 w-4 text-neutral-400 group-hover:text-amber-600 dark:group-hover:text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                        </svg>
                                        <span>Stock Adjustment</span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400 leading-snug">Reconcile variance or damage write-off.</p>
                                </div>
                                <span class="mt-2.5 text-[11px] font-semibold text-amber-600 dark:text-amber-400 inline-flex items-center gap-0.5">
                                    Record Adjustment &rarr;
                                </span>
                            </a>

                            {{-- 3. Stock Transfer --}}
                            <a
                                :href="transferUrl"
                                class="flex flex-col justify-between rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/60 dark:bg-neutral-800/30 p-3 hover:border-indigo-500 dark:hover:border-indigo-500 hover:bg-indigo-50/30 dark:hover:bg-indigo-950/20 transition-all group shadow-2xs"
                            >
                                <div>
                                    <div class="flex items-center gap-1.5 font-bold text-xs text-neutral-900 dark:text-neutral-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                        <svg class="h-4 w-4 text-neutral-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                        </svg>
                                        <span>Stock Transfer</span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400 leading-snug">Move stock between hospital storage coordinates.</p>
                                </div>
                                <span class="mt-2.5 text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 inline-flex items-center gap-0.5">
                                    Initiate Transfer &rarr;
                                </span>
                            </a>

                            {{-- 4. Catalogue Master --}}
                            <a
                                :href="catalogueUrl"
                                class="flex flex-col justify-between rounded-xl border border-neutral-200 dark:border-neutral-800 bg-neutral-50/60 dark:bg-neutral-800/30 p-3 hover:border-emerald-500 dark:hover:border-emerald-500 hover:bg-emerald-50/30 dark:hover:bg-emerald-950/20 transition-all group shadow-2xs"
                            >
                                <div>
                                    <div class="flex items-center gap-1.5 font-bold text-xs text-neutral-900 dark:text-neutral-100 group-hover:text-emerald-600 dark:group-hover:text-emerald-400">
                                        <svg class="h-4 w-4 text-neutral-400 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                        </svg>
                                        <span>Item Catalogue</span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400 leading-snug">View complete ledger, barcodes, and master data.</p>
                                </div>
                                <span class="mt-2.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 inline-flex items-center gap-0.5">
                                    View in Items &rarr;
                                </span>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-between border-t border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/60 px-4 py-3 sm:px-5">
                    <div class="text-[11px] text-neutral-500 dark:text-neutral-400">
                        SKU: <strong class="font-mono text-neutral-700 dark:text-neutral-300" x-text="item?.sku || 'N/A'"></strong>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            x-on:click="closeModal()"
                            class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3.5 py-1.5 text-xs font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700 transition-colors shadow-2xs"
                        >
                            Close
                        </button>
                        <a
                            :href="catalogueUrl"
                            class="inline-flex items-center gap-1 rounded-lg bg-primary-600 hover:bg-primary-700 px-3.5 py-1.5 text-xs font-semibold text-white transition-colors shadow-2xs"
                        >
                            <span>Inspect Item Master</span>
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function escapeHtml(value) {
            if (value === null || value === undefined) {
                return '';
            }

            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        async function fetchAllInventoryItems() {
            const items = [];
            let page = 1;
            let lastPage = 1;

            do {
                const response = await fetch(`/api/v1/inventory-items?per_page=100&page=${page}`, {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Session-Activity': 'passive'
                    }
                });

                if (!response.ok) {
                    throw new Error(`API request failed with status ${response.status}`);
                }

                const payload = await response.json();
                items.push(...(payload.data || []));
                lastPage = payload.meta?.last_page ?? 1;
                page += 1;
            } while (page <= lastPage);

            return items;
        }

        async function loadAlertsFromApi() {
            const status = document.getElementById('alerts-api-status');
            const container = document.getElementById('alerts-list');
            const statOutOfStock = document.getElementById('stat-out-of-stock');
            const statLowStock = document.getElementById('stat-low-stock');
            const statExpiring = document.getElementById('stat-expiring');
            const statExpired = document.getElementById('stat-expired');

            try {
                await fetch('/sanctum/csrf-cookie', {
                    credentials: 'same-origin',
                    headers: {
                        'X-Session-Activity': 'passive'
                    }
                });
                const items = await fetchAllInventoryItems();
                window.__cachedAlertItems = new Map();
                items.forEach(item => {
                    window.__cachedAlertItems.set(String(item.id), item);
                });

                const outOfStock = items.filter(item => item.stock_status === 'out_of_stock' || (parseInt(item.quantity_on_hand) || 0) <= 0);
                const lowStockOnly = items.filter(item => item.stock_status === 'low_stock' && (parseInt(item.quantity_on_hand) || 0) > 0);
                const allLowOrOut = items.filter(item => ['low_stock', 'out_of_stock'].includes(item.stock_status) || (parseInt(item.quantity_on_hand) || 0) <= (parseInt(item.reorder_level) || 0));

                // Sort ascending by quantity on hand (0 / lowest stock items appear first)
                allLowOrOut.sort((a, b) => {
                    const qtyA = parseInt(a.quantity_on_hand, 10) || 0;
                    const qtyB = parseInt(b.quantity_on_hand, 10) || 0;
                    if (qtyA !== qtyB) {
                        return qtyA - qtyB;
                    }
                    return (a.name || '').localeCompare(b.name || '');
                });

                const expiryBatches = items.flatMap(item => (item.expiry_batches || []).map(batch => {
                    const alertItem = {
                        ...item,
                        ...batch,
                        id: item.id,
                        _alert_key: `${item.id}:${batch.batch_id}`,
                    };
                    window.__cachedAlertItems.set(alertItem._alert_key, alertItem);

                    return alertItem;
                }));
                const byRemainingDays = (a, b) => (a.days_remaining ?? 0) - (b.days_remaining ?? 0);
                const expiringSoon = expiryBatches
                    .filter(batch => ['expiring_soon', 'critical'].includes(batch.expiry_status))
                    .sort(byRemainingDays);
                const expired = expiryBatches
                    .filter(batch => batch.expiry_status === 'expired')
                    .sort(byRemainingDays);

                // Update Stat Cards
                if (statOutOfStock) statOutOfStock.textContent = outOfStock.length.toLocaleString();
                if (statLowStock) statLowStock.textContent = lowStockOnly.length.toLocaleString();
                if (statExpiring) statExpiring.textContent = expiringSoon.length.toLocaleString();
                if (statExpired) statExpired.textContent = expired.length.toLocaleString();

                if (allLowOrOut.length === 0 && expiringSoon.length === 0 && expired.length === 0) {
                    container.className = 'w-full';
                    container.innerHTML = `
                        <div class="rounded-xl border border-dashed border-neutral-300 dark:border-neutral-700 bg-neutral-50/60 dark:bg-neutral-800/30 p-8 text-center">
                            <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 mb-3">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-200">All Stock Levels Optimal</h3>
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400 max-w-sm mx-auto">No items are below reorder levels, expiring within 90 days, or expired.</p>
                        </div>
                    `;
                } else {
                    const cards = [];
                    const hasLowStock = allLowOrOut.length > 0;
                    const hasExpiring = expiringSoon.length > 0;
                    const hasExpired = expired.length > 0;
                    const isSingleCategory = [hasLowStock, hasExpiring, hasExpired].filter(Boolean).length === 1;
                    const itemsGridClass = isSingleCategory
                        ? 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3'
                        : 'grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3';

                    // 1. Low Stock & Depleted Inventory Card
                    if (hasLowStock) {
                        cards.push(`
                            <div class="${isSingleCategory ? 'col-span-1' : ''} rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 p-4 sm:p-5 shadow-2xs">
                                <div class="flex items-center justify-between pb-3 border-b border-rose-200/70 dark:border-rose-900/40">
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-800/50">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                        </span>
                                        <div>
                                            <h3 class="text-xs sm:text-sm font-bold text-rose-900 dark:text-rose-200">Reorder &amp; Low Stock Triggers</h3>
                                            <p class="text-[11px] text-rose-700 dark:text-rose-300">${allLowOrOut.length} item(s) require replenishment</p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 dark:bg-rose-900/70 px-2.5 py-1 text-xs font-bold text-rose-800 dark:text-rose-200 tabular-nums">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                        ${allLowOrOut.length} alerts
                                    </span>
                                </div>
                                <div class="mt-3.5 ${itemsGridClass}">
                                    ${allLowOrOut.map(item => {
                                        const qty = parseInt(item.quantity_on_hand, 10) || 0;
                                        const reorder = parseInt(item.reorder_level || item.reorder_point || 0, 10);
                                        const isOut = qty <= 0;
                                        const deficit = Math.max(0, reorder - qty);
                                        const unitText = item.unit ? escapeHtml(item.unit) : 'units';
                                        const skuText = item.sku ? escapeHtml(item.sku) : 'No SKU';
                                        const categoryText = item.category?.name ? escapeHtml(item.category.name) : '';
                                        const itemTarget = item.sku || item.name || '';
                                        const itemUrl = itemTarget ? `/inventory/items?search=${encodeURIComponent(itemTarget)}` : '/inventory/items';

                                        const badgeClass = isOut 
                                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 ring-rose-200 dark:ring-rose-800/60' 
                                            : 'bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300 ring-amber-200 dark:ring-amber-800/60';
                                        const dotClass = isOut ? 'bg-rose-500' : 'bg-amber-500';
                                        const badgeLabel = isOut ? 'OUT OF STOCK' : 'LOW STOCK';
                                        const borderAccent = isOut ? 'border-l-rose-500' : 'border-l-amber-500';

                                        return `
                                            <div class="rounded-xl bg-white dark:bg-neutral-900 p-3.5 shadow-2xs border border-neutral-200/90 dark:border-neutral-800 border-l-4 ${borderAccent} hover:border-neutral-300 dark:hover:border-neutral-700 hover:shadow-xs transition-all flex flex-col justify-between">
                                                <div>
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="min-w-0 flex-1">
                                                            <h4 class="font-bold text-xs text-neutral-900 dark:text-neutral-100 line-clamp-2 leading-snug" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</h4>
                                                            <p class="text-[10px] font-mono text-neutral-400 dark:text-neutral-500 mt-0.5 truncate">${skuText}${categoryText ? ' · ' + categoryText : ''}</p>
                                                        </div>
                                                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset ${badgeClass}">
                                                            <span class="h-1.5 w-1.5 rounded-full ${dotClass} ${isOut ? 'animate-pulse' : ''}"></span>
                                                            ${badgeLabel}
                                                        </span>
                                                    </div>

                                                    <div class="mt-2.5 grid grid-cols-2 gap-1.5 rounded-lg bg-neutral-50/90 dark:bg-neutral-800/50 p-1.5 text-center text-xs">
                                                        <div class="rounded-md bg-white dark:bg-neutral-900 py-1.5 px-2 border border-neutral-100 dark:border-neutral-700/60">
                                                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">On Hand</span>
                                                            <span class="text-sm font-black tabular-nums ${isOut ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400'}">${escapeHtml(item.quantity_on_hand)} <span class="text-[10px] font-normal text-neutral-400">${unitText}</span></span>
                                                        </div>
                                                        <div class="rounded-md bg-white dark:bg-neutral-900 py-1.5 px-2 border border-neutral-100 dark:border-neutral-700/60">
                                                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Reorder At</span>
                                                            <span class="text-sm font-bold tabular-nums text-neutral-800 dark:text-neutral-200">${reorder} <span class="text-[10px] font-normal text-neutral-400">${unitText}</span></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mt-2.5 pt-2 border-t border-neutral-100 dark:border-neutral-800/80 flex items-center justify-between text-[11px]">
                                                    <span class="font-medium ${isOut ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400'}">
                                                        ${isOut ? 'Depleted stock' : (deficit > 0 ? `Deficit: -${deficit} ${unitText}` : 'At threshold')}
                                                    </span>
                                                    <button type="button" onclick="window.__openItemManageModal('${escapeHtml(item.id)}', '${isOut ? 'out_of_stock' : 'low_stock'}')" class="font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 hover:underline inline-flex items-center gap-0.5 text-[11px] cursor-pointer" title="Manage alert details">
                                                        Manage
                                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            </div>
                        `);
                    }

                    // 2. Active Expiring Inventory Card
                    if (hasExpiring) {
                        cards.push(`
                            <div class="${isSingleCategory ? 'col-span-1' : ''} rounded-xl border border-indigo-200 dark:border-indigo-900/50 bg-indigo-50/40 dark:bg-indigo-950/20 p-4 sm:p-5 shadow-2xs">
                                <div class="flex items-center justify-between pb-3 border-b border-indigo-200/70 dark:border-indigo-900/40">
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/60 text-indigo-600 dark:text-indigo-300 ring-1 ring-indigo-200 dark:ring-indigo-800/50">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </span>
                                        <div>
                                            <h3 class="text-xs sm:text-sm font-bold text-indigo-900 dark:text-indigo-200">Expiring Soon Inventory</h3>
                                            <p class="text-[11px] text-indigo-700 dark:text-indigo-300">${expiringSoon.length} batch(es) with 1&ndash;90 days remaining</p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-100 dark:bg-indigo-900/70 px-2.5 py-1 text-xs font-bold text-indigo-800 dark:text-indigo-200 tabular-nums">
                                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                        ${expiringSoon.length} batches
                                    </span>
                                </div>
                                <div class="mt-3.5 ${itemsGridClass}">
                                    ${expiringSoon.map(item => {
                                        const qty = parseInt(item.quantity_on_hand, 10) || 0;
                                        const unitText = item.unit ? escapeHtml(item.unit) : 'units';
                                        const skuText = item.sku ? escapeHtml(item.sku) : 'No SKU';
                                        const categoryText = item.category?.name ? escapeHtml(item.category.name) : '';
                                        const itemTarget = item.sku || item.name || '';
                                        const itemUrl = itemTarget ? `/inventory/items?search=${encodeURIComponent(itemTarget)}` : '/inventory/items';
                                        const daysLeft = item.days_remaining;
                                        const daysLabel = item.expiry_status_label || 'Expiring Soon';
                                        const isCritical = item.expiry_status === 'critical';

                                        return `
                                            <div class="rounded-xl bg-white dark:bg-neutral-900 p-3.5 shadow-2xs border border-neutral-200/90 dark:border-neutral-800 border-l-4 border-l-indigo-500 hover:border-neutral-300 dark:hover:border-neutral-700 hover:shadow-xs transition-all flex flex-col justify-between">
                                                <div>
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="min-w-0 flex-1">
                                                            <h4 class="font-bold text-xs text-neutral-900 dark:text-neutral-100 line-clamp-2 leading-snug" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</h4>
                                                            <p class="text-[10px] font-mono text-neutral-400 dark:text-neutral-500 mt-0.5 truncate">${skuText}${categoryText ? ' · ' + categoryText : ''}</p>
                                                        </div>
                                                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset ${isCritical ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 ring-rose-200 dark:ring-rose-800/60' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300 ring-indigo-200 dark:ring-indigo-800/60'}">
                                                            <span class="h-1.5 w-1.5 rounded-full ${isCritical ? 'bg-rose-500 animate-pulse' : 'bg-indigo-500'}"></span>
                                                            ${escapeHtml(daysLabel)} · ${daysLeft}d
                                                        </span>
                                                    </div>

                                                    <div class="mt-2.5 grid grid-cols-2 gap-1.5 rounded-lg bg-neutral-50/90 dark:bg-neutral-800/50 p-1.5 text-center text-xs">
                                                        <div class="rounded-md bg-white dark:bg-neutral-900 py-1.5 px-2 border border-neutral-100 dark:border-neutral-700/60">
                                                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">On Hand</span>
                                                            <span class="text-sm font-black tabular-nums text-neutral-800 dark:text-neutral-200">${escapeHtml(item.quantity_on_hand)} <span class="text-[10px] font-normal text-neutral-400">${unitText}</span></span>
                                                        </div>
                                                        <div class="rounded-md bg-white dark:bg-neutral-900 py-1.5 px-2 border border-neutral-100 dark:border-neutral-700/60">
                                                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Expiry Date</span>
                                                            <span class="text-sm font-bold tabular-nums text-indigo-600 dark:text-indigo-400">${escapeHtml(item.expiry_date || 'N/A')}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mt-2.5 pt-2 border-t border-neutral-100 dark:border-neutral-800/80 flex items-center justify-between text-[11px]">
                                                    <span class="font-medium text-neutral-500 dark:text-neutral-400 truncate">
                                                        Batch: <strong class="text-neutral-700 dark:text-neutral-300">${escapeHtml(item.batch_number || 'Default')}</strong>
                                                    </span>
                                                    <button type="button" onclick="window.__openItemManageModal('${escapeHtml(item._alert_key)}', 'near_expiry')" class="font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 hover:underline inline-flex items-center gap-0.5 text-[11px] cursor-pointer" title="Manage alert details">
                                                        Manage
                                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            </div>
                        `);
                    }

                    // 3. Expired Inventory Card
                    if (hasExpired) {
                        cards.push(`
                            <div class="${isSingleCategory ? 'col-span-1' : ''} rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 p-4 sm:p-5 shadow-2xs">
                                <div class="flex items-center justify-between pb-3 border-b border-rose-200/70 dark:border-rose-900/40">
                                    <div>
                                        <h3 class="text-xs sm:text-sm font-bold text-rose-900 dark:text-rose-200">Expired Inventory</h3>
                                        <p class="text-[11px] text-rose-700 dark:text-rose-300">0 days remaining or past due</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 dark:bg-rose-900/70 px-2.5 py-1 text-xs font-bold text-rose-800 dark:text-rose-200 tabular-nums">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                        ${expired.length} batches
                                    </span>
                                </div>
                                <div class="mt-3.5 ${itemsGridClass}">
                                    ${expired.map(item => {
                                        const unitText = item.unit ? escapeHtml(item.unit) : 'units';
                                        const skuText = item.sku ? escapeHtml(item.sku) : 'No SKU';
                                        const itemTarget = item.sku || item.name || '';
                                        const itemUrl = itemTarget ? `/inventory/items?search=${encodeURIComponent(itemTarget)}` : '/inventory/items';
                                        const elapsedLabel = item.days_remaining === 0 ? 'Expired today' : `${Math.abs(item.days_remaining)}d overdue`;

                                        return `
                                            <div class="rounded-xl bg-white dark:bg-neutral-900 p-3.5 shadow-2xs border border-neutral-200/90 dark:border-neutral-800 border-l-4 border-l-rose-500 flex flex-col justify-between">
                                                <div>
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="min-w-0 flex-1">
                                                            <h4 class="font-bold text-xs text-neutral-900 dark:text-neutral-100 line-clamp-2 leading-snug">${escapeHtml(item.name)}</h4>
                                                            <p class="text-[10px] font-mono text-neutral-400 dark:text-neutral-500 mt-0.5 truncate">${skuText} &middot; Batch ${escapeHtml(item.batch_number || 'Default')}</p>
                                                        </div>
                                                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset ring-rose-200 dark:ring-rose-800/60">
                                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                                            EXPIRED
                                                        </span>
                                                    </div>
                                                    <div class="mt-2.5 grid grid-cols-2 gap-1.5 rounded-lg bg-neutral-50/90 dark:bg-neutral-800/50 p-1.5 text-center text-xs">
                                                        <div class="rounded-md bg-white dark:bg-neutral-900 py-1.5 px-2 border border-neutral-100 dark:border-neutral-700/60">
                                                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">On Hand</span>
                                                            <span class="text-sm font-black tabular-nums text-neutral-800 dark:text-neutral-200">${escapeHtml(item.quantity_on_hand)} <span class="text-[10px] font-normal text-neutral-400">${unitText}</span></span>
                                                        </div>
                                                        <div class="rounded-md bg-white dark:bg-neutral-900 py-1.5 px-2 border border-neutral-100 dark:border-neutral-700/60">
                                                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">Expiry Date</span>
                                                            <span class="text-sm font-bold tabular-nums text-rose-600 dark:text-rose-400">${escapeHtml(item.expiry_date || 'N/A')}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mt-2.5 pt-2 border-t border-neutral-100 dark:border-neutral-800/80 flex items-center justify-between text-[11px]">
                                                    <span class="font-semibold text-rose-600 dark:text-rose-400">${elapsedLabel}</span>
                                                    <a href="${itemUrl}" class="font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 hover:underline">View Item</a>
                                                </div>
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            </div>
                        `);
                    }

                    container.className = isSingleCategory ? 'grid gap-4 grid-cols-1' : 'grid gap-4 lg:grid-cols-2';
                    container.innerHTML = cards.join('');
                }

                // Hide loader cleanly without showing 'Alerts loaded from API.' text
                if (status) {
                    status.classList.add('hidden');
                }
            } catch (error) {
                console.error(error);
                if (statOutOfStock) statOutOfStock.textContent = '0';
                if (statLowStock) statLowStock.textContent = '0';
                if (statExpiring) statExpiring.textContent = '0';
                if (statExpired) statExpired.textContent = '0';

                container.className = 'w-full';
                container.innerHTML = `<div class="rounded-xl border border-dashed border-rose-300 dark:border-rose-800 bg-rose-50/50 dark:bg-rose-950/20 p-6 text-xs text-rose-700 dark:text-rose-300 text-center">Unable to load active stock alerts from API. Please refresh the page.</div>`;
                if (status) {
                    status.classList.add('hidden');
                }
            }
        }

        function itemAlertManager() {
            return {
                open: false,
                item: null,
                alertType: 'out_of_stock',

                openModal(itemData, type) {
                    this.item = itemData;
                    this.alertType = type || (parseInt(itemData?.quantity_on_hand || 0, 10) <= 0 ? 'out_of_stock' : 'low_stock');
                    this.open = true;
                },
                closeModal() {
                    this.open = false;
                },
                get isOut() {
                    return !this.item || parseInt(this.item.quantity_on_hand || 0, 10) <= 0;
                },
                get deficit() {
                    if (!this.item) return 0;
                    const qoh = parseInt(this.item.quantity_on_hand || 0, 10);
                    const reorder = parseInt(this.item.reorder_level || this.item.reorder_point || 0, 10);
                    return Math.max(0, reorder - qoh);
                },
                get daysUntilExpiry() {
                    return this.item?.days_remaining ?? null;
                },
                get catalogueUrl() {
                    if (!this.item) return '{{ route('inventory.items') }}';
                    const target = this.item.sku || this.item.name || '';
                    return `{{ route('inventory.items') }}?search=${encodeURIComponent(target)}`;
                },
                get requisitionUrl() {
                    if (!this.item) return '{{ route('inventory.requisitions.index') }}';
                    return `{{ route('inventory.requisitions.index') }}?item_id=${encodeURIComponent(this.item.id)}`;
                },
                get adjustmentUrl() {
                    if (!this.item) return '{{ route('inventory.adjustments') }}';
                    const type = this.alertType === 'near_expiry' ? 'expiry' : (this.isOut ? 'correction' : 'correction');
                    return `{{ route('inventory.adjustments') }}?item_id=${encodeURIComponent(this.item.id)}&adjustment_type=${type}`;
                },
                get transferUrl() {
                    if (!this.item) return '{{ route('inventory.transfers.index') }}';
                    return `{{ route('inventory.transfers.index') }}?item_id=${encodeURIComponent(this.item.id)}`;
                }
            };
        }

        window.__openItemManageModal = function(itemId, alertType) {
            if (window.__cachedAlertItems && window.__cachedAlertItems.has(String(itemId))) {
                const item = window.__cachedAlertItems.get(String(itemId));
                window.dispatchEvent(new CustomEvent('open-manage-modal', {
                    detail: { item, alertType }
                }));
            }
        };

        document.addEventListener('DOMContentLoaded', loadAlertsFromApi);
    </script>
</x-app-layout>
