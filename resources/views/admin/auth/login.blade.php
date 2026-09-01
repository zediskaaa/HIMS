<x-guest-layout title="Admin Sign in">
    <x-auth.login-panel
        :action="route('admin.login.store')"
        :forgot-password-url="route('admin.password.request')"
        badge="Restricted administration portal"
        heading="Admin Sign in"
        description="Authenticate to manage authorized HIMS administration and operations modules."
        submit-label="Sign in as Admin"
    />
</x-guest-layout>
