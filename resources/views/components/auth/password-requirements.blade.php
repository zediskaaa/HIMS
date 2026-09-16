<div class="rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-800 dark:bg-neutral-800/60">
    <p class="text-xs leading-5 text-neutral-600 dark:text-neutral-400">
        {{ \App\Rules\PasswordStandard::REQUIREMENTS }}
    </p>

    <ul class="mt-2 grid gap-1 text-xs sm:grid-cols-2" aria-label="Password requirements">
        <li class="flex items-center gap-2" :class="password.length >= 8 ? 'text-success-700 dark:text-success-300' : 'text-neutral-500 dark:text-neutral-400'">
            <span aria-hidden="true" class="inline-block h-2 w-2 rounded-full border" :class="password.length >= 8 ? 'border-success-600 bg-success-600 dark:border-success-500 dark:bg-success-500' : 'border-neutral-400 dark:border-neutral-600'"></span>
            At least 8 characters
        </li>
        <li class="flex items-center gap-2" :class="/[A-Z]/.test(password) ? 'text-success-700 dark:text-success-300' : 'text-neutral-500 dark:text-neutral-400'">
            <span aria-hidden="true" class="inline-block h-2 w-2 rounded-full border" :class="/[A-Z]/.test(password) ? 'border-success-600 bg-success-600 dark:border-success-500 dark:bg-success-500' : 'border-neutral-400 dark:border-neutral-600'"></span>
            One uppercase letter
        </li>
        <li class="flex items-center gap-2" :class="/[a-z]/.test(password) ? 'text-success-700 dark:text-success-300' : 'text-neutral-500 dark:text-neutral-400'">
            <span aria-hidden="true" class="inline-block h-2 w-2 rounded-full border" :class="/[a-z]/.test(password) ? 'border-success-600 bg-success-600 dark:border-success-500 dark:bg-success-500' : 'border-neutral-400 dark:border-neutral-600'"></span>
            One lowercase letter
        </li>
        <li class="flex items-center gap-2" :class="/[0-9]/.test(password) ? 'text-success-700 dark:text-success-300' : 'text-neutral-500 dark:text-neutral-400'">
            <span aria-hidden="true" class="inline-block h-2 w-2 rounded-full border" :class="/[0-9]/.test(password) ? 'border-success-600 bg-success-600 dark:border-success-500 dark:bg-success-500' : 'border-neutral-400 dark:border-neutral-600'"></span>
            One number
        </li>
        <li class="flex items-center gap-2" :class="/[^A-Za-z0-9\s]/.test(password) ? 'text-success-700 dark:text-success-300' : 'text-neutral-500 dark:text-neutral-400'">
            <span aria-hidden="true" class="inline-block h-2 w-2 rounded-full border" :class="/[^A-Za-z0-9\s]/.test(password) ? 'border-success-600 bg-success-600 dark:border-success-500 dark:bg-success-500' : 'border-neutral-400 dark:border-neutral-600'"></span>
            One special character
        </li>
    </ul>

    <p
        class="mt-2 text-xs font-medium text-danger-600 dark:text-danger-400"
        x-show="passwordConfirmation.length > 0 && password !== passwordConfirmation"
        x-cloak
        role="status"
    >
        {{ \App\Rules\PasswordStandard::CONFIRMATION_MESSAGE }}
    </p>
</div>
