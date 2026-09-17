<x-app-layout>
    <x-ui.page-header
        title="Add User"
        :breadcrumbs="[
            'Home' => route(\App\Support\AuthenticationContext::dashboardRoute()),
            'User Management' => route('admin.users.index'),
            'Add User' => null,
        ]" />

    @if ($errors->any())
        <x-ui.alert variant="danger" title="This account was not created">
            <ul class="space-y-0.5 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <x-ui.card title="Account Details" subtitle="Fields marked with an asterisk are required.">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5" autocomplete="off"
              @if (auth()->user()?->isSuperAdministrator())
                  data-super-admin-password="create"
              @else
                  data-confirm-title="Create staff user"
                  data-confirm-message="Are you sure you want to create this user account and issue an initial temporary password?"
                  data-confirm-label="Create User"
              @endif>
            @csrf

            @include('admin.users.partials.form', ['user' => null, 'roles' => $roles])

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2 border-t border-neutral-100">
                <p class="text-xs text-neutral-500">
                    Employee accounts are provisioned for hospital operations. Activity is recorded in accordance with the <a href="{{ route('privacy.notice', ['return' => url()->current()]) }}" target="_blank" rel="opener" class="text-primary-600 underline hover:text-primary-700">Privacy Notice</a>.
                </p>
                <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-end gap-2 shrink-0 w-full sm:w-auto">
                    <x-ui.button variant="secondary" :href="route('admin.users.index')">Cancel</x-ui.button>
                    <x-ui.button type="submit" icon="plus" data-loading-text="Creating account...">Create Account</x-ui.button>
                </div>
            </div>
        </form>
    </x-ui.card>
</x-app-layout>
