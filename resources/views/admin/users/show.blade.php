<x-app-layout>
    @php($nameComponents = $user->nameComponents())
    <x-ui.page-header
        :title="$user->name"
        :subtitle="$user->role->label().' · '.$user->status->label()"
        :breadcrumbs="[
            'Home' => route(\App\Support\AuthenticationContext::dashboardRoute()),
            'User Management' => route('admin.users.index'),
            $user->name => null,
        ]">
        @if ($canManage)
            <x-slot:actions>
                <x-ui.button variant="secondary" :href="route('admin.users.edit', $user)" icon="pencil-square">
                    Edit
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-1 space-y-6">
            <x-ui.card title="Account">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center w-12 h-12 rounded-full shrink-0 text-sm font-semibold
                                 {{ $user->isActive() ? 'bg-primary-50 text-primary-700' : 'bg-neutral-100 text-neutral-400' }}">
                        {{ $user->initials() }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-neutral-900 truncate">{{ $user->name }}</p>
                        <p class="text-xs text-neutral-500 truncate">{{ $user->email }}</p>
                    </div>
                </div>

                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-neutral-500">Surname</dt>
                        <dd class="text-neutral-800">{{ $nameComponents['surname'] ?? '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-neutral-500">First Name</dt>
                        <dd class="text-neutral-800">{{ $nameComponents['first_name'] ?? '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-neutral-500">Middle Name</dt>
                        <dd class="text-neutral-800">{{ $nameComponents['middle_name'] ?? '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-neutral-500">Role</dt>
                        <dd>
                            <x-ui.badge :variant="$user->isAdministrator() ? 'primary' : 'neutral'">
                                {{ $user->role->label() }}
                            </x-ui.badge>
                            @if ($user->isProtected())
                                <x-ui.badge variant="warning">Protected</x-ui.badge>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-neutral-500">Status</dt>
                        <dd>
                            <x-ui.badge :status="$user->status->value" dot>{{ $user->status->label() }}</x-ui.badge>
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-neutral-500">Employee ID</dt>
                        <dd class="font-mono text-xs text-neutral-800">{{ $user->employee_id ?? '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-neutral-500">Department</dt>
                        <dd class="text-neutral-800">{{ $user->department ?? '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-neutral-500">Contact</dt>
                        <dd class="text-neutral-800">{{ $user->phone ?? '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-neutral-500">Last sign-in</dt>
                        <dd class="text-neutral-800">{{ $user->last_login_at?->format('M d, Y g:i A') ?? 'Never' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-neutral-500">Added</dt>
                        <dd class="text-neutral-800">{{ $user->created_at?->format('M d, Y') ?? '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card title="Permissions" :subtitle="$user->role->description()">
                @if ($user->isActive())
                    <ul class="space-y-2">
                        @foreach ($user->permissions() as $permission)
                            <li class="flex items-start gap-2">
                                <x-ui.icon name="check-circle" class="w-4 h-4 mt-0.5 shrink-0 text-success-600" />
                                <div class="min-w-0">
                                    <p class="text-sm text-neutral-800">{{ $permission->label() }}</p>
                                    <p class="text-xs text-neutral-500">{{ $permission->description() }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    {{-- An inactive account holds no permissions at all, so listing
                         the role's abilities here would be misleading. --}}
                    <p class="text-sm text-neutral-500">
                        This account is inactive and currently holds no permissions. Reactivate it to restore
                        {{ $user->role->label() }} access.
                    </p>
                @endif
            </x-ui.card>
        </div>

        <div class="lg:col-span-2">
            {{-- This history is the reason accounts are deactivated rather than
                 deleted: every row below names this person. --}}
            <x-ui.card
                title="Recent Stock Movements"
                subtitle="The 10 most recent movements recorded by this account."
                :padding="false">
                <x-ui.table :sticky-header="false">
                    <x-ui.table.head>
                        <x-ui.table.th>Item</x-ui.table.th>
                        <x-ui.table.th>Type</x-ui.table.th>
                        <x-ui.table.th numeric>Qty</x-ui.table.th>
                        <x-ui.table.th>From</x-ui.table.th>
                        <x-ui.table.th>To</x-ui.table.th>
                        <x-ui.table.th>Recorded</x-ui.table.th>
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($recentMovements as $movement)
                            <x-ui.table.row>
                                <x-ui.table.td>
                                    <span class="font-medium text-neutral-900">{{ $movement->item?->name ?? '—' }}</span>
                                    @if ($movement->item?->sku)
                                        <span class="block text-xs text-neutral-500">{{ $movement->item->sku }}</span>
                                    @endif
                                </x-ui.table.td>
                                <x-ui.table.td>
                                    <x-ui.badge :status="$movement->movement_type->value">
                                        {{ $movement->movement_type->label() }}
                                    </x-ui.badge>
                                </x-ui.table.td>
                                <x-ui.table.td numeric>{{ number_format($movement->quantity) }}</x-ui.table.td>
                                <x-ui.table.td muted>{{ $movement->fromLocation?->name ?? '—' }}</x-ui.table.td>
                                <x-ui.table.td muted>{{ $movement->toLocation?->name ?? '—' }}</x-ui.table.td>
                                <x-ui.table.td muted>{{ $movement->moved_at?->format('M d, Y g:i A') ?? '—' }}</x-ui.table.td>
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty
                                :colspan="6"
                                icon="arrows-right-left"
                                title="No movements recorded"
                                message="Stock this person records will be listed here." />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
