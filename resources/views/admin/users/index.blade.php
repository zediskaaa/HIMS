<x-app-layout>
    @php($accountCreatedSuccess = session()->pull('account_created_success'))

    <x-ui.page-header
        title="User Management"
        subtitle="Staff accounts and what each role is allowed to do."
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'User Management' => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('admin.permissions')" icon="shield-check">Access Control</x-ui.button>
            <x-ui.button :href="route('admin.users.create')" icon="plus">Add User</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($accountCreatedSuccess)
        <x-ui.alert variant="success" title="Account created" dismissible>
            {{ $accountCreatedSuccess }}
        </x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert variant="danger" title="That change was not applied">
            <ul class="space-y-0.5 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Total accounts" :value="number_format($counts['total'])" icon="users" tone="primary" />
        <x-ui.stat label="Active" :value="number_format($counts['active'])" icon="check-circle" tone="success"
                   hint="Inactive accounts cannot sign in." />
        <x-ui.stat label="Administrators" :value="number_format($counts['administrators'])" icon="shield-check"
                   tone="warning" hint="The system always keeps at least one." />
    </div>

    <x-ui.card title="Find an Account" subtitle="Search by name, email, employee ID or department.">
        <form method="GET" action="{{ route('admin.users.index') }}"
              class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            <x-ui.field
                name="search"
                label="Search"
                :value="$filters['search'] ?? null"
                placeholder="e.g. Cruz, EMP-014, Pharmacy" />

            <x-ui.field
                name="role"
                label="Role"
                type="select"
                :value="$filters['role'] ?? null"
                placeholder="All roles"
                :options="$roles" />

            <x-ui.field
                name="status"
                label="Status"
                type="select"
                :value="$filters['status'] ?? null"
                placeholder="All statuses"
                :options="$statuses" />

            <div class="flex items-center gap-2">
                <x-ui.button type="submit" icon="magnifying-glass" data-loading-text="Loading accounts...">Search</x-ui.button>
                @if (array_filter($filters))
                    <x-ui.button variant="secondary" :href="route('admin.users.index')">Clear</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>

    <x-ui.card
        title="Accounts"
        :subtitle="$users->total().' '.\Illuminate\Support\Str::plural('account', $users->total())"
        :padding="false">
        <x-slot:actions>
            <x-ui.button
                type="button"
                variant="secondary"
                size="sm"
                icon="shield-check"
                x-data
                x-on:click="$dispatch('open-modal', 'role-permissions-modal')">
                View Role Permissions
            </x-ui.button>
        </x-slot:actions>

        {{-- Mobile & Tablet card view (visible on screens below lg / 1024px) --}}
        <div class="lg:hidden divide-y divide-neutral-200">
            @forelse ($users as $account)
                @php($nameComponents = $account->nameComponents())
                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="flex items-center justify-center w-9 h-9 rounded-full shrink-0
                                         text-xs font-semibold
                                         {{ $account->isActive() ? 'bg-primary-50 text-primary-700' : 'bg-neutral-100 text-neutral-400' }}">
                                {{ $account->initials() }}
                            </span>
                            <div class="min-w-0">
                                <a href="{{ route('admin.users.show', $account) }}"
                                   title="{{ $account->name }}"
                                   class="font-medium text-neutral-900 hover:text-primary-700 hover:underline truncate block">
                                    {{ $account->name }}
                                </a>
                                <span class="block text-xs text-neutral-500 truncate">{{ $account->email }}</span>
                            </div>
                        </div>
                        @if ($account->is(auth()->user()))
                            <span class="text-[11px] font-medium text-neutral-400 shrink-0">(you)</span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <x-ui.badge :variant="$account->isAdministrator() ? 'primary' : 'neutral'">
                            {{ $account->role->label() }}
                        </x-ui.badge>
                        <x-ui.badge :status="$account->status->value" dot>
                            {{ $account->status->label() }}
                        </x-ui.badge>
                        @if ($account->isTemporarilyLocked())
                            <x-ui.badge variant="warning">Temporarily Locked</x-ui.badge>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs text-neutral-600 pt-1">
                        <div>
                            <span class="text-neutral-400">ID:</span>
                            <span class="font-mono font-medium">{{ $account->employee_id ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400">Dept:</span>
                            <span class="font-medium">{{ $account->department ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400">Phone:</span>
                            <span>{{ $account->phone ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-neutral-400">Sign-in:</span>
                            <span>{{ $account->last_login_at?->format('M d, Y') ?? 'Never' }}</span>
                        </div>
                    </div>

                    {{-- Actions on mobile/tablet: neatly arranged and fully accessible --}}
                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-neutral-100">
                        @if (in_array($account->getKey(), $manageableAccountIds, true))
                            <x-ui.button variant="secondary" size="sm"
                                         :href="route('admin.users.edit', $account)" icon="pencil-square">
                                Edit
                            </x-ui.button>

                            @if (in_array($account->getKey(), $unlockableAccountIds, true))
                                <form method="POST" action="{{ route('admin.users.unlock', $account) }}"
                                      data-confirm-title="Confirm account unlock"
                                      data-confirm-message="Are you sure you want to unlock this account?"
                                      data-confirm-label="Unlock Account">
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.button
                                        type="submit"
                                        size="sm"
                                        data-loading-text="Unlocking account...">
                                        Unlock
                                    </x-ui.button>
                                </form>
                            @endif

                            @unless ($account->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.users.toggle-status', $account) }}"
                                      data-confirm-title="Confirm account status change"
                                      data-confirm-message="Are you sure you want to {{ $account->isActive() ? 'deactivate' : 'reactivate' }} this user?"
                                      data-confirm-label="{{ $account->isActive() ? 'Deactivate' : 'Reactivate' }}">
                                    @csrf
                                    @method('PATCH')
                                    <x-ui.button
                                        type="submit"
                                        size="sm"
                                        data-loading-text="Updating account..."
                                        :variant="$account->isActive() ? 'secondary' : 'primary'">
                                        {{ $account->isActive() ? 'Deactivate' : 'Reactivate' }}
                                    </x-ui.button>
                                </form>
                            @endunless
                        @elseif ($account->isProtected())
                            <x-ui.badge variant="warning">Protected</x-ui.badge>
                        @else
                            <span class="text-xs text-neutral-400">Restricted</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-neutral-500">
                    <x-ui.icon name="users" class="mx-auto h-8 w-8 text-neutral-400 mb-2" />
                    <p class="font-semibold text-neutral-700">No accounts match</p>
                    <p class="text-xs text-neutral-500 mt-1">Adjust the filters, or add the first staff account.</p>
                </div>
            @endforelse
        </div>

        {{-- Desktop table view (visible on screens lg / 1024px and wider) --}}
        <div class="hidden lg:block">
            <x-ui.table :sticky-header="false">
                <x-ui.table.head>
                    <x-ui.table.th class="px-3 py-3 w-24">Employee ID</x-ui.table.th>
                    <x-ui.table.th class="px-3 py-3 min-w-[130px]">Surname</x-ui.table.th>
                    <x-ui.table.th class="px-3 py-3 min-w-[150px]">First Name</x-ui.table.th>
                    <x-ui.table.th class="px-3 py-3 w-24">Middle Name</x-ui.table.th>
                    <x-ui.table.th class="px-3 py-3">Department</x-ui.table.th>
                    <x-ui.table.th class="px-3 py-3 whitespace-nowrap">Contact Number</x-ui.table.th>
                    <x-ui.table.th class="px-3 py-3">Role</x-ui.table.th>
                    <x-ui.table.th class="px-3 py-3">Status</x-ui.table.th>
                    <x-ui.table.th class="px-3 py-3 w-28">Last Sign-in</x-ui.table.th>
                    <x-ui.table.th align="right" class="px-3 py-3 min-w-[125px]">Actions</x-ui.table.th>
                </x-ui.table.head>
                <tbody>
                    @forelse ($users as $account)
                        @php($nameComponents = $account->nameComponents())
                        <x-ui.table.row>
                            <x-ui.table.td muted class="px-3 py-2.5 whitespace-nowrap">
                                <span class="font-mono text-xs">{{ $account->employee_id ?? '—' }}</span>
                            </x-ui.table.td>

                            <x-ui.table.td class="px-3 py-2.5">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="flex items-center justify-center w-7 h-7 rounded-full shrink-0
                                                 text-xs font-semibold
                                                 {{ $account->isActive() ? 'bg-primary-50 text-primary-700' : 'bg-neutral-100 text-neutral-400' }}">
                                        {{ $account->initials() }}
                                    </span>
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.users.show', $account) }}"
                                           title="{{ $account->name }}"
                                           class="font-medium text-neutral-900 hover:text-primary-700 hover:underline truncate block">
                                            {{ $nameComponents['surname'] ?? $account->name }}
                                        </a>
                                        @if ($account->is(auth()->user()))
                                            <span class="text-[11px] font-medium text-neutral-400 block -mt-0.5">(you)</span>
                                        @endif
                                    </div>
                                </div>
                            </x-ui.table.td>

                            <x-ui.table.td class="px-3 py-2.5 min-w-0">
                                <span class="text-neutral-900 block truncate">{{ $nameComponents['first_name'] ?? '—' }}</span>
                                <span class="block text-xs text-neutral-500 truncate max-w-[140px] xl:max-w-[190px]" title="{{ $account->email }}">{{ $account->email }}</span>
                            </x-ui.table.td>

                            <x-ui.table.td muted class="px-3 py-2.5 truncate max-w-[100px]">{{ $nameComponents['middle_name'] ?? '—' }}</x-ui.table.td>

                            <x-ui.table.td muted class="px-3 py-2.5 truncate max-w-[130px]">{{ $account->department ?? '—' }}</x-ui.table.td>

                            <x-ui.table.td muted class="px-3 py-2.5 whitespace-nowrap font-mono text-xs">{{ $account->phone ?? '—' }}</x-ui.table.td>

                            <x-ui.table.td class="px-3 py-2.5 whitespace-nowrap">
                                <x-ui.badge :variant="$account->isAdministrator() ? 'primary' : 'neutral'">
                                    {{ $account->role->label() }}
                                </x-ui.badge>
                            </x-ui.table.td>

                            <x-ui.table.td class="px-3 py-2.5">
                                <x-ui.badge :status="$account->status->value" dot>
                                    {{ $account->status->label() }}
                                </x-ui.badge>
                                @if ($account->isTemporarilyLocked())
                                    <x-ui.badge variant="warning" class="mt-1">Locked</x-ui.badge>
                                    <span class="mt-0.5 block text-[11px] text-neutral-500">
                                        Until {{ $account->login_locked_until->timezone(config('app.timezone'))->format('M d, g:i A') }}
                                    </span>
                                @endif
                            </x-ui.table.td>

                            <x-ui.table.td muted class="px-3 py-2.5 whitespace-nowrap">
                                @if ($account->last_login_at)
                                    <span class="block text-xs text-neutral-800">{{ $account->last_login_at->format('M d, Y') }}</span>
                                    <span class="block text-[11px] text-neutral-400">{{ $account->last_login_at->format('g:i A') }}</span>
                                @else
                                    <span class="text-xs text-neutral-400">Never</span>
                                @endif
                            </x-ui.table.td>

                            <x-ui.table.td align="right" class="px-3 py-2.5 min-w-[125px]">
                                <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                                    @if (in_array($account->getKey(), $manageableAccountIds, true))
                                        <x-ui.button variant="ghost" size="sm" class="px-2 py-1"
                                                     :href="route('admin.users.edit', $account)">
                                            Edit
                                        </x-ui.button>

                                        @if (in_array($account->getKey(), $unlockableAccountIds, true))
                                            <form method="POST" action="{{ route('admin.users.unlock', $account) }}"
                                                  data-confirm-title="Confirm account unlock"
                                                  data-confirm-message="Are you sure you want to unlock this account?"
                                                  data-confirm-label="Unlock Account">
                                                @csrf
                                                @method('PATCH')
                                                <x-ui.button
                                                    type="submit"
                                                    size="sm"
                                                    class="px-2 py-1"
                                                    data-loading-text="Unlocking account...">
                                                    Unlock
                                                </x-ui.button>
                                            </form>
                                        @endif

                                        {{-- Deactivating yourself is refused by the service;
                                             hide the impossible action here as well. --}}
                                        @unless ($account->is(auth()->user()))
                                            <form method="POST" action="{{ route('admin.users.toggle-status', $account) }}"
                                                  data-confirm-title="Confirm account status change"
                                                  data-confirm-message="Are you sure you want to {{ $account->isActive() ? 'deactivate' : 'reactivate' }} this user?"
                                                  data-confirm-label="{{ $account->isActive() ? 'Deactivate' : 'Reactivate' }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-ui.button
                                                    type="submit"
                                                    size="sm"
                                                    class="px-2 py-1"
                                                    data-loading-text="Updating account..."
                                                    :variant="$account->isActive() ? 'secondary' : 'primary'">
                                                    {{ $account->isActive() ? 'Deactivate' : 'Reactivate' }}
                                                </x-ui.button>
                                            </form>
                                        @endunless
                                    @elseif ($account->isProtected())
                                        <x-ui.badge variant="warning">Protected</x-ui.badge>
                                    @else
                                        <span class="text-xs text-neutral-400">Restricted</span>
                                    @endif
                                </div>
                            </x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty
                            :colspan="10"
                            icon="users"
                            title="No accounts match"
                            message="Adjust the filters, or add the first staff account." />
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>

        @if ($users->hasPages())
            <x-slot:footer>
                {{ $users->links() }}
            </x-slot:footer>
        @endif
    </x-ui.card>

    {{-- Compact Role Permissions Reference --}}
    <x-ui.card>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3 min-w-0">
                <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary-50 text-primary-600 shrink-0">
                    <x-ui.icon name="shield-check" class="w-5 h-5" />
                </span>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-neutral-900">Role Permissions Reference</h3>
                    <p class="text-xs text-neutral-500">
                        Permissions are attached to system roles, not individual staff accounts. View what each role is authorized to do.
                    </p>
                </div>
            </div>
            <div class="shrink-0">
                <x-ui.button
                    type="button"
                    variant="secondary"
                    size="sm"
                    icon="shield-check"
                    x-data
                    x-on:click="$dispatch('open-modal', 'role-permissions-modal')">
                    View Role Permissions
                </x-ui.button>
            </div>
        </div>
    </x-ui.card>

    {{-- Role Permissions Interactive Modal --}}
    <x-ui.modal name="role-permissions-modal" title="Role Capabilities & Permissions" maxWidth="2xl">
        <div x-data="{ activeRole: '{{ \App\Enums\UserRole::SuperAdministrator->value }}' }" class="space-y-4">
            {{-- Role selector tabs --}}
            <div>
                <p class="text-xs font-medium text-neutral-500 uppercase tracking-wider mb-2">Select a Role</p>
                <div class="flex flex-wrap gap-1.5 border-b border-neutral-200 pb-3">
                    @foreach (\App\Enums\UserRole::cases() as $role)
                        <button
                            type="button"
                            x-on:click="activeRole = '{{ $role->value }}'"
                            :class="activeRole === '{{ $role->value }}'
                                ? 'bg-primary-50 text-primary-700 border-primary-300 font-semibold shadow-xs'
                                : 'bg-neutral-50 text-neutral-600 border-neutral-200 hover:bg-neutral-100 hover:text-neutral-900'"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border text-xs transition-colors">
                            <span>{{ $role->label() }}</span>
                            <span
                                :class="activeRole === '{{ $role->value }}' ? 'bg-primary-200 text-primary-800' : 'bg-neutral-200 text-neutral-600'"
                                class="rounded-full px-1.5 py-0.2 text-[10px] font-mono">
                                {{ count($role->permissions()) }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Role detail panels --}}
            @foreach (\App\Enums\UserRole::cases() as $role)
                <div x-show="activeRole === '{{ $role->value }}'" x-cloak class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 p-3 bg-neutral-50 rounded-lg border border-neutral-200">
                        <div>
                            <div class="flex items-center gap-2">
                                <x-ui.badge :variant="$role->isAdministrator() ? 'primary' : 'neutral'">
                                    {{ $role->label() }}
                                </x-ui.badge>
                                <span class="text-xs text-neutral-500 font-mono">{{ count($role->permissions()) }} granted permissions</span>
                            </div>
                            <p class="mt-1 text-xs text-neutral-600">{{ $role->description() }}</p>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-500 mb-2">Granted Permissions</h4>
                        <ul class="grid gap-2 sm:grid-cols-2 text-xs text-neutral-700 max-h-[50vh] overflow-y-auto pr-1">
                            @foreach ($role->permissions() as $permission)
                                <li class="flex items-start gap-2 p-2 rounded-md bg-white border border-neutral-100 hover:border-neutral-200 transition-colors">
                                    <x-ui.icon name="check-circle" class="w-4 h-4 mt-0.5 shrink-0 text-success-600" />
                                    <span class="leading-tight">{{ $permission->label() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>

        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'role-permissions-modal')">
                Close
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</x-app-layout>
