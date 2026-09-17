<x-app-layout>
    <x-ui.page-header
        title="Access Control"
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'User Management' => route('admin.users.index'), 'Access Control' => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('admin.users.index')" icon="arrow-left">Back to User Management</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card
        title="Permission Matrix"
        subtitle="Rows are abilities grouped by module; columns are the configured account roles.">

        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="border-b border-neutral-200 dark:border-neutral-800">
                        <th scope="col"
                            class="sticky left-0 z-10 bg-white dark:bg-neutral-900 py-3 pr-4 text-left text-xs font-semibold
                                   uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            Module / Ability
                        </th>
                        @foreach ($roles as $role)
                            <th scope="col" class="px-3 py-3 text-center align-top min-w-[105px]">
                                <div class="flex flex-col items-center">
                                    <div class="h-9 flex items-center justify-center text-center">
                                        <span class="text-xs font-semibold text-neutral-900 dark:text-neutral-100 leading-tight">
                                            {{ $role->label() }}
                                        </span>
                                    </div>
                                    <span class="mt-1 block text-[11px] text-neutral-500 dark:text-neutral-400 tabular-nums whitespace-nowrap">
                                        <span class="font-semibold text-neutral-800 dark:text-neutral-200">{{ $permissionCounts[$role->value] }}</span> of {{ $totalPermissions }} abilities
                                    </span>
                                    <span class="mt-0.5 block text-[11px] text-neutral-400 dark:text-neutral-500 tabular-nums whitespace-nowrap">
                                        {{ trans_choice(':count account|:count accounts', $accountCounts[$role->value] ?? 0, ['count' => $accountCounts[$role->value] ?? 0]) }}
                                    </span>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach ($modules as $module => $permissions)
                        <tr class="bg-neutral-50 dark:bg-neutral-800/50">
                            <th scope="colgroup" colspan="{{ count($roles) + 1 }}"
                                class="sticky left-0 py-1.5 pr-4 text-left text-[11px] font-semibold
                                       uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                                {{ $module }}
                            </th>
                        </tr>

                        @foreach ($permissions as $permission)
                            <tr class="border-b border-neutral-100 dark:border-neutral-800/60 last:border-0 hover:bg-neutral-50/60 dark:hover:bg-neutral-800/40">
                                <th scope="row" class="sticky left-0 z-10 bg-white dark:bg-neutral-900 py-2.5 pr-4 text-left font-normal
                                                       hover:bg-neutral-50/60 dark:hover:bg-neutral-800/40">
                                    <span class="block font-medium text-neutral-900 dark:text-neutral-100">{{ $permission->label() }}</span>
                                    <span class="block text-xs text-neutral-500 dark:text-neutral-400">{{ $permission->description() }}</span>
                                </th>

                                @foreach ($roles as $role)
                                    <td class="px-3 py-2.5 text-center">
                                        @if ($role->grants($permission))
                                            <span class="sr-only">Granted</span>
                                            <x-ui.icon name="check-circle" aria-hidden="true"
                                                       class="inline-block w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                                        @else
                                            <span class="sr-only">Not granted</span>
                                            <x-ui.icon name="minus" aria-hidden="true"
                                                       class="inline-block w-4 h-4 text-neutral-300 dark:text-neutral-600" />
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
</x-app-layout>
