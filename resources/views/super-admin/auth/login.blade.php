<x-guest-layout title="Super Admin Sign in">
    <x-auth.login-panel
        :action="route('super-admin.login.store')"
        :forgot-password-url="route('super-admin.password.request')"
        badge="Restricted administration portal"
        heading="Super Admin Sign in"
        description="Authenticate to manage HIMS with full administrative access."
        submit-label="Sign in as Super Admin"
    />
</x-guest-layout>
