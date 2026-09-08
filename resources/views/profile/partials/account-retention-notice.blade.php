<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Account Retention') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Employee accounts are retained to preserve inventory ownership and audit history.') }}
        </p>
    </header>

    <x-ui.alert variant="info" title="Account deactivation">
        {{ __('Your account cannot be permanently deleted. Contact an authorized administrator if your access should be deactivated during offboarding.') }}
    </x-ui.alert>
</section>
