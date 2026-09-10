<x-app-layout>
    <x-ui.page-header
        title="Add User"
        subtitle="New accounts are active immediately and can sign in with the password you set."
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
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5">
            @csrf

            @include('admin.users.partials.form', ['user' => null, 'roles' => $roles])

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2 border-t border-neutral-100">
                <p class="text-xs text-neutral-500">
                    Employee accounts are provisioned for hospital operations. Activity is recorded in accordance with the <a href="{{ route('privacy.notice') }}" target="_blank" class="text-primary-600 underline hover:text-primary-700">Privacy Notice</a>.
                </p>
                <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-end gap-2 shrink-0 w-full sm:w-auto">
                    <x-ui.button variant="secondary" :href="route('admin.users.index')">Cancel</x-ui.button>
                    <x-ui.button type="submit" icon="plus" data-loading-text="Creating account...">Create Account</x-ui.button>
                </div>
            </div>
        </form>
    </x-ui.card>
</x-app-layout>
