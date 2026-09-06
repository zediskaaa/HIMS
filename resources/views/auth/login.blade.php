<x-guest-layout title="Staff Sign in">
    <x-auth.login-panel
        :action="route('login')"
        :forgot-password-url="route('password.request')"
        badge="Secure staff portal"
        heading="Staff Sign in"
        description="Access the HIMS modules authorized for your hospital role."
        submit-label="Sign in as Staff"
        :login-restriction="$loginRestriction"
    />
</x-guest-layout>
