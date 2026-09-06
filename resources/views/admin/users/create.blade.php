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

            <div class="flex items-center justify-end gap-2 pt-1">
                <x-ui.button variant="secondary" :href="route('admin.users.index')">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="plus" data-loading-text="Creating account...">Create Account</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-app-layout>
