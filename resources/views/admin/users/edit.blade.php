<x-app-layout>
    <x-ui.page-header
        title="Edit {{ $user->name }}"
        subtitle="Changes take effect the next time this person loads a page."
        :breadcrumbs="[
            'Home' => route(\App\Support\AuthenticationContext::dashboardRoute()),
            'User Management' => route('admin.users.index'),
            $user->name => null,
        ]">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('admin.users.show', $user)">View Activity</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert variant="danger" title="This account was not updated">
            <ul class="space-y-0.5 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    @if ($user->is(auth()->user()))
        <x-ui.alert variant="warning" title="This is your own account">
            You cannot remove your own administrator access or deactivate yourself — that would lock you out
            with no way back in from this screen.
        </x-ui.alert>
    @endif

    <x-ui.card title="Account Details" subtitle="Leave the password fields blank to keep the current password.">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5"
              data-confirm-title="Confirm account changes"
              data-confirm-message="Are you sure you want to save these account changes?"
              data-confirm-label="Save Changes">
            @csrf
            @method('PUT')

            @include('admin.users.partials.form', ['user' => $user, 'roles' => $roles])

            <div class="flex items-center justify-end gap-2 pt-1">
                <x-ui.button variant="secondary" :href="route('admin.users.index')">Cancel</x-ui.button>
                <x-ui.button type="submit" data-loading-text="Saving changes...">Save Changes</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-app-layout>
