<x-app-layout>
    <x-ui.page-header
        title="Access Control"
        subtitle="Which role may reach which module. Generated from the permission definitions the system enforces."
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Access Control' => null]" />

    <x-ui.alert variant="info" title="How to read this">
        A tick means accounts with that role hold the ability, and the routes and
        buttons behind it are open to them. A dash means the screen is hidden from
        the sidebar and the request is refused if typed directly into the address
        bar. Administrators pass every check by design, so their column is full.
    </x-ui.alert>

    <x-ui.card
        title="Permission Matrix"
        subtitle="Rows are abilities grouped by module; columns are the five staff roles.">

        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="border-b border-neutral-200">
                        <th scope="col"
                            class="sticky left-0 z-10 bg-white py-2.5 pr-4 text-left text-xs font-semibold
                                   uppercase tracking-wider text-neutral-500">
                            Module / Ability
                        </th>
                        @foreach ($roles as $role)
                            <th scope="col" class="px-3 py-2.5 text-center align-bottom">
                                <span class="block text-xs font-semibold text-neutral-900">
                                    {{ $role->label() }}
                                </span>
                                <span class="block mt-0.5 text-[11px] font-normal text-neutral-400">
                                    {{ $permissionCounts[$role->value] }} of {{ $totalPermissions }}
                                    &middot;
                                    {{ trans_choice(':count account|:count accounts', $accountCounts[$role->value] ?? 0, ['count' => $accountCounts[$role->value] ?? 0]) }}
                                </span>
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach ($modules as $module => $permissions)
                        <tr class="bg-neutral-50">
                            <th scope="colgroup" colspan="{{ count($roles) + 1 }}"
                                class="sticky left-0 py-1.5 pr-4 text-left text-[11px] font-semibold
                                       uppercase tracking-wider text-neutral-500">
                                {{ $module }}
                            </th>
                        </tr>

                        @foreach ($permissions as $permission)
                            <tr class="border-b border-neutral-100 last:border-0 hover:bg-neutral-50/60">
                                <th scope="row" class="sticky left-0 z-10 bg-white py-2.5 pr-4 text-left font-normal
                                                       hover:bg-neutral-50/60">
                                    <span class="block font-medium text-neutral-900">{{ $permission->label() }}</span>
                                    <span class="block text-xs text-neutral-500">{{ $permission->description() }}</span>
                                </th>

                                @foreach ($roles as $role)
                                    <td class="px-3 py-2.5 text-center">
                                        @if ($role->grants($permission))
                                            <span class="sr-only">Granted</span>
                                            <x-ui.icon name="check-circle" aria-hidden="true"
                                                       class="inline-block w-5 h-5 text-emerald-600" />
                                        @else
                                            <span class="sr-only">Not granted</span>
                                            <x-ui.icon name="minus" aria-hidden="true"
                                                       class="inline-block w-4 h-4 text-neutral-300" />
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <x-ui.card title="What Each Role Is For" subtitle="The job function each set of permissions was mapped against.">
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($roles as $role)
                <div class="p-4 rounded-lg border border-neutral-200">
                    <dt class="flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-neutral-900">{{ $role->label() }}</span>
                        <x-ui.badge :variant="$role->isAdministrator() ? 'warning' : 'neutral'">
                            {{ $permissionCounts[$role->value] }} abilities
                        </x-ui.badge>
                    </dt>
                    <dd class="mt-1 text-sm text-neutral-600">{{ $role->description() }}</dd>
                </div>
            @endforeach
        </dl>
    </x-ui.card>
</x-app-layout>
