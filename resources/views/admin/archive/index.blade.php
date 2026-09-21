<x-app-layout full-width>
    <x-ui.page-header
        title="Master Records Archive"
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Administration' => null, 'Archive' => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('admin.audit-logs.index')" icon="shield-check">
                Audit Trail
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Operation Refused">
            <ul class="space-y-0.5 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    {{-- 3-Zone KPI Summary Cards --}}
    <div class="grid gap-4 grid-cols-2 sm:grid-cols-4">
        <x-ui.stat
            label="Total Archived"
            :value="number_format($counts['total'] ?? 0)"
            icon="archive-box"
            tone="primary"
            hint="Preserved across all modules" />
        <x-ui.stat
            label="Inventory Items"
            :value="number_format($counts['items'] ?? 0)"
            icon="cube"
            tone="neutral"
            hint="Historical stock ledgers intact" />
        <x-ui.stat
            label="Suppliers"
            :value="number_format($counts['suppliers'] ?? 0)"
            icon="building-office-2"
            tone="neutral"
            hint="Past POs and invoices preserved" />
        <x-ui.stat
            label="User Accounts"
            :value="number_format($counts['users'] ?? 0)"
            icon="users"
            tone="neutral"
            hint="Sign-in revoked, audit attribution kept" />
    </div>

    {{-- Filter & Search Card --}}
    <x-ui.card title="Archive Filter" subtitle="Locate archived records by keyword, date range, or responsible administrator.">
        {{-- Record Type Navigation Tabs --}}
        <div class="flex flex-wrap gap-2 pb-4 border-b border-neutral-200 dark:border-neutral-800">
            @php
                $tabItems = [
                    'all' => ['label' => 'All Records', 'icon' => 'archive-box', 'count' => $counts['total'] ?? 0],
                    'items' => ['label' => 'Inventory Items', 'icon' => 'cube', 'count' => $counts['items'] ?? 0],
                    'suppliers' => ['label' => 'Suppliers', 'icon' => 'building-office-2', 'count' => $counts['suppliers'] ?? 0],
                    'users' => ['label' => 'User Accounts', 'icon' => 'users', 'count' => $counts['users'] ?? 0],
                ];
            @endphp
            @foreach ($tabItems as $key => $tab)
                @php
                    $isActiveTab = ($currentType === $key);
                    $query = array_merge(request()->except(['page']), ['type' => $key]);
                @endphp
                <a href="{{ route('admin.archive.index', $query) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ $isActiveTab ? 'bg-primary-600 text-white shadow-xs' : 'bg-neutral-100 dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-200 dark:hover:bg-neutral-700' }}">
                    <x-ui.icon :name="$tab['icon']" class="h-4 w-4" />
                    <span>{{ $tab['label'] }}</span>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px] font-mono {{ $isActiveTab ? 'bg-primary-700 text-white' : 'bg-neutral-200 dark:bg-neutral-700 text-neutral-600 dark:text-neutral-300' }}">
                        {{ number_format($tab['count']) }}
                    </span>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.archive.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end mt-4">
            <input type="hidden" name="type" value="{{ $currentType }}">

            <x-ui.field
                name="search"
                label="Search Keywords"
                :value="$filters['search'] ?? null"
                placeholder="Name, SKU, Tax ID, Reason..." />

            <x-ui.field
                name="archive_date_from"
                label="Archived From"
                type="date"
                :value="$filters['archive_date_from'] ?? null" />

            <x-ui.field
                name="archive_date_to"
                label="Archived To"
                type="date"
                :value="$filters['archive_date_to'] ?? null" />

            <div class="space-y-1">
                <label for="archived_by" class="block text-xs font-medium text-neutral-700 dark:text-neutral-300">
                    Archived By
                </label>
                <select id="archived_by" name="archived_by" class="block min-h-10 w-full rounded-md border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-900 px-3 pr-8 text-sm text-neutral-900 dark:text-neutral-100 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30">
                    <option value="">All Administrators</option>
                    @foreach ($archivedByUsers as $actorUser)
                        <option value="{{ $actorUser->id }}" @selected((string) ($filters['archived_by'] ?? '') === (string) $actorUser->id)>
                            {{ $actorUser->name }} ({{ $actorUser->employee_id ?? 'ID: '.$actorUser->id }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <x-ui.button type="submit" icon="magnifying-glass">Filter</x-ui.button>
                @if (array_filter($filters))
                    <x-ui.button variant="secondary" :href="route('admin.archive.index', ['type' => $currentType])">Clear</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>

    {{-- Main Records Table Card --}}
    <x-ui.card
        title="Archived Master Records"
        :subtitle="$records->total().' '.\Illuminate\Support\Str::plural('record', $records->total()).' currently archived'"
        :padding="false">

        {{-- Mobile & Tablet list (below lg) --}}
        <div class="lg:hidden divide-y divide-neutral-200 dark:divide-neutral-800">
            @forelse ($records as $record)
                @php
                    $recType = $currentType === 'all' ? $record->record_type : rtrim($currentType, 's');
                    $title = match ($recType) {
                        'item', 'inventory_item' => $record->name ?? $record->record_title ?? 'Untitled Item',
                        'supplier' => $record->name ?? $record->record_title ?? 'Untitled Supplier',
                        'user' => $record->name ?? $record->record_title ?? 'Unnamed User',
                        default => $record->record_title ?? 'Record',
                    };
                    $ident = match ($recType) {
                        'item', 'inventory_item' => $record->sku ?? $record->identifier ?? '—',
                        'supplier' => $record->trade_name ?? $record->tax_number ?? $record->identifier ?? '—',
                        'user' => $record->employee_id ?? $record->identifier ?? '—',
                        default => $record->identifier ?? '—',
                    };
                    $archivedAt = $record->archived_at ? \Carbon\Carbon::parse($record->archived_at)->format('M d, Y H:i') : 'Unknown date';
                    $archivedByName = $currentType === 'all'
                        ? ($record->archived_by_user?->name ?? 'Administrator')
                        : ($record->archivedBy?->name ?? 'Administrator');
                    $reason = $record->archive_reason ?: 'No specific reason documented';
                    $recordId = $record->id;
                    $unarchiveRoute = match ($recType) {
                        'item', 'inventory_item' => route('admin.archive.items.unarchive', $recordId),
                        'supplier' => route('admin.archive.suppliers.unarchive', $recordId),
                        'user' => route('admin.archive.users.unarchive', $recordId),
                        default => '#',
                    };
                @endphp
                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <x-ui.badge variant="neutral">
                                    {{ ucfirst(str_replace('_', ' ', $recType)) }}
                                </x-ui.badge>
                                <span class="font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ $ident }}</span>
                            </div>
                            <p class="font-medium text-neutral-900 dark:text-neutral-100 truncate">{{ $title }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs text-neutral-600 dark:text-neutral-400">
                        <div>
                            <span class="text-neutral-400">Archived:</span>
                            <span>{{ $archivedAt }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400">By:</span>
                            <span>{{ $archivedByName }}</span>
                        </div>
                        <div class="col-span-2">
                            <span class="text-neutral-400">Reason:</span>
                            <span class="italic text-neutral-700 dark:text-neutral-300">{{ $reason }}</span>
                        </div>
                    </div>

                    @can(\App\Enums\Permission::ManageArchive->value)
                        <div class="pt-2 border-t border-neutral-100 dark:border-neutral-800">
                            <form method="POST" action="{{ $unarchiveRoute }}"
                                  data-confirm-title="Restore Master Record"
                                  data-confirm-message="Are you sure you want to restore '{{ $title }}'? The system will verify that no active records conflict with its identifier before restoring."
                                  data-confirm-label="Restore Record">
                                @csrf
                                <x-ui.button type="submit" size="sm" variant="secondary" icon="arrow-path">
                                    Restore to Active
                                </x-ui.button>
                            </form>
                        </div>
                    @endcan
                </div>
            @empty
                <div class="p-8 text-center text-sm text-neutral-500">
                    <x-ui.icon name="archive-box" class="mx-auto h-8 w-8 text-neutral-400 mb-2" />
                    <p class="font-semibold text-neutral-700 dark:text-neutral-300">No archived records found</p>
                    <p class="text-xs text-neutral-500 mt-1">Archived items, suppliers, and user accounts will appear here.</p>
                </div>
            @endforelse
        </div>

        {{-- Desktop Table (lg screens and wider) --}}
        <div class="hidden lg:block">
            <x-ui.table :sticky-header="false">
                @if ($currentType === 'all')
                    <x-ui.table.head>
                        <x-ui.table.th class="px-3 py-3 w-28">Type</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3">Record Title</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-36">Identifier / Code</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-40">Archived Date</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-36">Archived By</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3">Archive Reason</x-ui.table.th>
                        <x-ui.table.th align="right" class="px-3 py-3 w-36 hims-sticky-actions">Actions</x-ui.table.th>
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($records as $record)
                            @php
                                $recType = $record->record_type;
                                $unarchiveRoute = match ($recType) {
                                    'item', 'inventory_item' => route('admin.archive.items.unarchive', $record->id),
                                    'supplier' => route('admin.archive.suppliers.unarchive', $record->id),
                                    'user' => route('admin.archive.users.unarchive', $record->id),
                                    default => '#',
                                };
                                $typeVariant = match ($recType) {
                                    'item', 'inventory_item' => 'primary',
                                    'supplier' => 'success',
                                    'user' => 'warning',
                                    default => 'neutral',
                                };
                            @endphp
                            <x-ui.table.row>
                                <x-ui.table.td class="px-3 py-2.5">
                                    <x-ui.badge :variant="$typeVariant">
                                        {{ ucfirst(str_replace('_', ' ', $recType)) }}
                                    </x-ui.badge>
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ $record->record_title }}
                                </x-ui.table.td>
                                <x-ui.table.td muted class="px-3 py-2.5 whitespace-nowrap">
                                    <span class="font-mono text-xs">{{ $record->identifier ?? '—' }}</span>
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    {{ $record->archived_at ? \Carbon\Carbon::parse($record->archived_at)->format('M d, Y H:i') : '—' }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs">
                                    {{ $record->archived_by_user?->name ?? 'Administrator' }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs text-neutral-600 dark:text-neutral-400">
                                    <span title="{{ $record->archive_reason }}" class="line-clamp-2">
                                        {{ $record->archive_reason ?: '—' }}
                                    </span>
                                </x-ui.table.td>
                                <x-ui.table.td align="right" class="px-3 py-2.5 hims-sticky-actions">
                                    @can(\App\Enums\Permission::ManageArchive->value)
                                        <form method="POST" action="{{ $unarchiveRoute }}"
                                              data-confirm-title="Restore Master Record"
                                              data-confirm-message="Are you sure you want to restore '{{ $record->record_title }}'? System integrity checks will verify that no active record uses this identifier before restoring."
                                              data-confirm-label="Restore Record">
                                            @csrf
                                            <x-ui.button type="submit" size="sm" variant="secondary" icon="arrow-path" class="px-2.5 py-1 text-xs">
                                                Restore
                                            </x-ui.button>
                                        </form>
                                    @endcan
                                </x-ui.table.td>
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty
                                :colspan="7"
                                icon="archive-box"
                                title="No archived records match the criteria"
                                message="Adjust your filters or search terms." />
                        @endforelse
                    </tbody>
                @elseif ($currentType === 'items')
                    <x-ui.table.head>
                        <x-ui.table.th class="px-3 py-3">Item Details</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-36">SKU / Barcode</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-28">Preserved Stock</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-40">Archived Date</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-36">Archived By</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3">Reason</x-ui.table.th>
                        <x-ui.table.th align="right" class="px-3 py-3 w-36 hims-sticky-actions">Actions</x-ui.table.th>
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($records as $item)
                            <x-ui.table.row>
                                <x-ui.table.td class="px-3 py-2.5">
                                    <div class="font-medium text-neutral-900 dark:text-neutral-100">{{ $item->name }}</div>
                                    <div class="text-xs text-neutral-500">{{ $item->category?->name ?? 'Uncategorized' }}</div>
                                </x-ui.table.td>
                                <x-ui.table.td muted class="px-3 py-2.5 whitespace-nowrap">
                                    <div class="font-mono text-xs font-semibold">{{ $item->sku }}</div>
                                    @if ($item->barcode_value)
                                        <div class="font-mono text-[11px] text-neutral-400">{{ $item->barcode_value }}</div>
                                    @endif
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs">
                                    <span class="font-mono font-semibold">{{ number_format($item->quantity_on_hand) }}</span>
                                    <span class="text-neutral-400">{{ $item->unit }}</span>
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    {{ $item->archived_at?->format('M d, Y H:i') ?? '—' }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs">
                                    {{ $item->archivedBy?->name ?? 'Administrator' }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs text-neutral-600 dark:text-neutral-400">
                                    <span title="{{ $item->archive_reason }}" class="line-clamp-2">
                                        {{ $item->archive_reason ?: '—' }}
                                    </span>
                                </x-ui.table.td>
                                <x-ui.table.td align="right" class="px-3 py-2.5 hims-sticky-actions">
                                    @can(\App\Enums\Permission::ManageArchive->value)
                                        <form method="POST" action="{{ route('admin.archive.items.unarchive', $item) }}"
                                              data-confirm-title="Restore Inventory Item"
                                              data-confirm-message="Restore '{{ $item->name }}' (SKU: {{ $item->sku }}) to the active catalog? Duplicate SKU and Barcode conflict checks will be performed."
                                              data-confirm-label="Restore Item">
                                            @csrf
                                            <x-ui.button type="submit" size="sm" variant="secondary" icon="arrow-path" class="px-2.5 py-1 text-xs">
                                                Restore
                                            </x-ui.button>
                                        </form>
                                    @endcan
                                </x-ui.table.td>
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty
                                :colspan="7"
                                icon="cube"
                                title="No archived inventory items match"
                                message="Adjust your filters or check other tabs." />
                        @endforelse
                    </tbody>
                @elseif ($currentType === 'suppliers')
                    <x-ui.table.head>
                        <x-ui.table.th class="px-3 py-3">Supplier Name</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-36">Trade Name</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-36">Tax ID / TIN</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-40">Archived Date</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-36">Archived By</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3">Reason</x-ui.table.th>
                        <x-ui.table.th align="right" class="px-3 py-3 w-36 hims-sticky-actions">Actions</x-ui.table.th>
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($records as $supplier)
                            <x-ui.table.row>
                                <x-ui.table.td class="px-3 py-2.5 font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ $supplier->name }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs text-neutral-600 dark:text-neutral-400">
                                    {{ $supplier->trade_name ?? '—' }}
                                </x-ui.table.td>
                                <x-ui.table.td muted class="px-3 py-2.5 whitespace-nowrap">
                                    <span class="font-mono text-xs">{{ $supplier->tax_number ?? '—' }}</span>
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    {{ $supplier->archived_at?->format('M d, Y H:i') ?? '—' }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs">
                                    {{ $supplier->archivedBy?->name ?? 'Administrator' }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs text-neutral-600 dark:text-neutral-400">
                                    <span title="{{ $supplier->archive_reason }}" class="line-clamp-2">
                                        {{ $supplier->archive_reason ?: '—' }}
                                    </span>
                                </x-ui.table.td>
                                <x-ui.table.td align="right" class="px-3 py-2.5 hims-sticky-actions">
                                    @can(\App\Enums\Permission::ManageArchive->value)
                                        <form method="POST" action="{{ route('admin.archive.suppliers.unarchive', $supplier) }}"
                                              data-confirm-title="Restore Supplier"
                                              data-confirm-message="Restore supplier '{{ $supplier->name }}' to active procurement? Tax ID collision checks will be enforced."
                                              data-confirm-label="Restore Supplier">
                                            @csrf
                                            <x-ui.button type="submit" size="sm" variant="secondary" icon="arrow-path" class="px-2.5 py-1 text-xs">
                                                Restore
                                            </x-ui.button>
                                        </form>
                                    @endcan
                                </x-ui.table.td>
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty
                                :colspan="7"
                                icon="building-office-2"
                                title="No archived suppliers match"
                                message="Adjust your filters or check other tabs." />
                        @endforelse
                    </tbody>
                @elseif ($currentType === 'users')
                    <x-ui.table.head>
                        <x-ui.table.th class="px-3 py-3 w-28">Employee ID</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3">User Name &amp; Email</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-32">Role</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-36">Department</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-40">Archived Date</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3 w-36">Archived By</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-3">Reason</x-ui.table.th>
                        <x-ui.table.th align="right" class="px-3 py-3 w-36 hims-sticky-actions">Actions</x-ui.table.th>
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($records as $userAccount)
                            <x-ui.table.row>
                                <x-ui.table.td muted class="px-3 py-2.5 whitespace-nowrap">
                                    <span class="font-mono text-xs">{{ $userAccount->employee_id ?? '—' }}</span>
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <x-ui.avatar :user="$userAccount" size="xs" />
                                        <div>
                                            <div class="font-medium text-neutral-900 dark:text-neutral-100">{{ $userAccount->name }}</div>
                                            <div class="text-xs text-neutral-500">{{ $userAccount->email }}</div>
                                        </div>
                                    </div>
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5">
                                    <x-ui.badge :variant="$userAccount->role?->isAdministrator() ? 'primary' : 'neutral'">
                                        {{ $userAccount->role?->label() ?? 'User' }}
                                    </x-ui.badge>
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs text-neutral-600 dark:text-neutral-400">
                                    {{ $userAccount->department ?? '—' }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    {{ $userAccount->archived_at?->format('M d, Y H:i') ?? '—' }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs">
                                    {{ $userAccount->archivedBy?->name ?? 'Administrator' }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 text-xs text-neutral-600 dark:text-neutral-400">
                                    <span title="{{ $userAccount->archive_reason }}" class="line-clamp-2">
                                        {{ $userAccount->archive_reason ?: '—' }}
                                    </span>
                                </x-ui.table.td>
                                <x-ui.table.td align="right" class="px-3 py-2.5 hims-sticky-actions">
                                    @can(\App\Enums\Permission::ManageArchive->value)
                                        <form method="POST" action="{{ route('admin.archive.users.unarchive', $userAccount) }}"
                                              data-confirm-title="Restore User Account"
                                              data-confirm-message="Restore account for '{{ $userAccount->name }}' ({{ $userAccount->email }})? Duplicate email or employee ID checks will be enforced."
                                              data-confirm-label="Restore Account">
                                            @csrf
                                            <x-ui.button type="submit" size="sm" variant="secondary" icon="arrow-path" class="px-2.5 py-1 text-xs">
                                                Restore
                                            </x-ui.button>
                                        </form>
                                    @endcan
                                </x-ui.table.td>
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty
                                :colspan="8"
                                icon="users"
                                title="No archived user accounts match"
                                message="Adjust your filters or check other tabs." />
                        @endforelse
                    </tbody>
                @endif
            </x-ui.table>
        </div>

        @if ($records->hasPages())
            <x-slot:footer>
                {{ $records->links() }}
            </x-slot:footer>
        @endif
    </x-ui.card>
</x-app-layout>
