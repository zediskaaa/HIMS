<x-app-layout>
    @php($accountCreatedSuccess = session()->pull('account_created_success'))

    <x-ui.page-header
        title="User Management"
        subtitle="Staff accounts and what each role is allowed to do."
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'User Management' => null]">
        <x-slot:actions>
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
        <x-ui.table>
            <x-ui.table.head>
                <x-ui.table.th>Employee ID</x-ui.table.th>
                <x-ui.table.th>Surname</x-ui.table.th>
                <x-ui.table.th>First Name</x-ui.table.th>
                <x-ui.table.th>Middle Name</x-ui.table.th>
                <x-ui.table.th>Department</x-ui.table.th>
                <x-ui.table.th>Contact Number</x-ui.table.th>
                <x-ui.table.th>Role</x-ui.table.th>
                <x-ui.table.th>Status</x-ui.table.th>
                <x-ui.table.th>Last Sign-in</x-ui.table.th>
                <x-ui.table.th align="right">Actions</x-ui.table.th>
            </x-ui.table.head>
            <tbody>
                @forelse ($users as $account)
                    @php($nameComponents = $account->nameComponents())
                    <x-ui.table.row>
                        <x-ui.table.td muted>
                            <span class="font-mono text-xs">{{ $account->employee_id ?? '—' }}</span>
                        </x-ui.table.td>

                        <x-ui.table.td>
                            <div class="flex items-center gap-2.5">
                                <span class="flex items-center justify-center w-8 h-8 rounded-full shrink-0
                                             text-xs font-semibold
                                             {{ $account->isActive() ? 'bg-primary-50 text-primary-700' : 'bg-neutral-100 text-neutral-400' }}">
                                    {{ $account->initials() }}
                                </span>
                                <div class="min-w-0">
                                    <a href="{{ route('admin.users.show', $account) }}"
                                       title="{{ $account->name }}"
                                       class="font-medium text-neutral-900 hover:text-primary-700 hover:underline">
                                        {{ $nameComponents['surname'] ?? $account->name }}
                                    </a>
                                    @if ($account->is(auth()->user()))
                                        <span class="ml-1 text-[11px] font-medium text-neutral-400">(you)</span>
                                    @endif
                                </div>
                            </div>
                        </x-ui.table.td>

                        <x-ui.table.td>
                            <span class="text-neutral-900">{{ $nameComponents['first_name'] ?? '—' }}</span>
                            <span class="block text-xs text-neutral-500 truncate">{{ $account->email }}</span>
                        </x-ui.table.td>

                        <x-ui.table.td muted>{{ $nameComponents['middle_name'] ?? '—' }}</x-ui.table.td>

                        <x-ui.table.td muted>{{ $account->department ?? '—' }}</x-ui.table.td>

                        <x-ui.table.td muted>{{ $account->phone ?? '—' }}</x-ui.table.td>

                        <x-ui.table.td>
                            <x-ui.badge :variant="$account->isAdministrator() ? 'primary' : 'neutral'">
                                {{ $account->role->label() }}
                            </x-ui.badge>
                        </x-ui.table.td>

                        <x-ui.table.td>
                            <x-ui.badge :status="$account->status->value" dot>
                                {{ $account->status->label() }}
                            </x-ui.badge>
                        </x-ui.table.td>

                        <x-ui.table.td muted>
                            {{ $account->last_login_at?->format('M d, Y g:i A') ?? 'Never' }}
                        </x-ui.table.td>

                        <x-ui.table.td align="right">
                            <div class="flex items-center justify-end gap-1.5">
                                @if (in_array($account->getKey(), $manageableAccountIds, true))
                                    <x-ui.button variant="ghost" size="sm"
                                                 :href="route('admin.users.edit', $account)">
                                        Edit
                                    </x-ui.button>

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

        @if ($users->hasPages())
            <x-slot:footer>
                {{ $users->links() }}
            </x-slot:footer>
        @endif
    </x-ui.card>

    {{-- Shown on the list itself so the role names on each row are not
         opaque to whoever is assigning them. --}}
    <x-ui.card title="What Each Role Can Do"
               subtitle="Permissions are attached to roles, not to individual people.">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (\App\Enums\UserRole::cases() as $role)
                <div class="rounded-md border border-neutral-200 p-4">
                    <div class="flex items-center gap-2">
                        <x-ui.badge :variant="$role->isAdministrator() ? 'primary' : 'neutral'">
                            {{ $role->label() }}
                        </x-ui.badge>
                    </div>
                    <p class="mt-2 text-xs text-neutral-500">{{ $role->description() }}</p>
                    <ul class="mt-3 space-y-1">
                        @foreach ($role->permissions() as $permission)
                            <li class="flex items-start gap-1.5 text-xs text-neutral-600">
                                <x-ui.icon name="check-circle" class="w-3.5 h-3.5 mt-px shrink-0 text-success-600" />
                                {{ $permission->label() }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </x-ui.card>
</x-app-layout>
