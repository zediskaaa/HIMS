<x-guest-layout title="Admin Login" portal="admin">
    <x-auth.login-panel
        :action="route('admin.login.store')"
        :forgot-password-url="route('admin.password.request')"
        variant="admin"
        badge="Administrative access"
        heading="Admin Login"
        description="Sign in to manage authorized HIMS operations, users, and inventory workflows."
        submit-label="Sign in as Admin"
        :login-restriction="$loginRestriction"
    />
</x-guest-layout>
