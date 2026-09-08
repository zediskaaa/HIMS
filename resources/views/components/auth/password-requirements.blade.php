<div class="rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3">
    <p class="text-xs leading-5 text-neutral-600">
        {{ \App\Rules\PasswordStandard::REQUIREMENTS }}
    </p>

    <ul class="mt-2 grid gap-1 text-xs sm:grid-cols-2" aria-label="Password requirements">
        <li class="flex items-center gap-2" :class="password.length >= 8 ? 'text-success-700' : 'text-neutral-500'">
            <span aria-hidden="true" x-text="password.length >= 8 ? '✓' : '•'"></span>
            At least 8 characters
        </li>
        <li class="flex items-center gap-2" :class="/[A-Z]/.test(password) ? 'text-success-700' : 'text-neutral-500'">
            <span aria-hidden="true" x-text="/[A-Z]/.test(password) ? '✓' : '•'"></span>
            One uppercase letter
        </li>
        <li class="flex items-center gap-2" :class="/[a-z]/.test(password) ? 'text-success-700' : 'text-neutral-500'">
            <span aria-hidden="true" x-text="/[a-z]/.test(password) ? '✓' : '•'"></span>
            One lowercase letter
        </li>
        <li class="flex items-center gap-2" :class="/[0-9]/.test(password) ? 'text-success-700' : 'text-neutral-500'">
            <span aria-hidden="true" x-text="/[0-9]/.test(password) ? '✓' : '•'"></span>
            One number
        </li>
        <li class="flex items-center gap-2" :class="/[^A-Za-z0-9\s]/.test(password) ? 'text-success-700' : 'text-neutral-500'">
            <span aria-hidden="true" x-text="/[^A-Za-z0-9\s]/.test(password) ? '✓' : '•'"></span>
            One special character
        </li>
    </ul>

    <p
        class="mt-2 text-xs font-medium text-danger-600"
        x-show="passwordConfirmation.length > 0 && password !== passwordConfirmation"
        x-cloak
        role="status"
    >
        {{ \App\Rules\PasswordStandard::CONFIRMATION_MESSAGE }}
    </p>
</div>
