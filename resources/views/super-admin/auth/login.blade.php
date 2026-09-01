<x-guest-layout title="Super Admin Login" portal="super-admin">
    <x-auth.login-panel
        :action="route('super-admin.login.store')"
        :forgot-password-url="route('super-admin.password.request')"
        variant="super-admin"
        badge="Privileged system access"
        heading="Super Admin Login"
        description="Authenticate to govern system-wide access, security, and administrative controls."
        submit-label="Sign in as Super Admin"
    />
</x-guest-layout>
