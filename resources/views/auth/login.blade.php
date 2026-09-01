<x-guest-layout title="Staff Sign in">
    <x-auth.login-panel
        :action="route('login')"
        badge="Secure staff portal"
        heading="Staff Sign in"
        description="Access the HIMS modules authorized for your hospital role."
        submit-label="Sign in as Staff"
    />
</x-guest-layout>
