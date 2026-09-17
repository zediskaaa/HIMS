<dialog
    data-super-admin-password-modal
    data-confirm-url="{{ route('admin.users.confirm-password') }}"
    class="m-auto fixed inset-0 z-50 w-[calc(100%-2rem)] max-w-md overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 p-0 text-neutral-800 dark:text-neutral-200 shadow-2xl backdrop:bg-neutral-950/60 backdrop:backdrop-blur-xs"
    role="dialog"
    aria-modal="true"
    aria-labelledby="super-admin-password-title"
    aria-describedby="super-admin-password-description"
>
    <div class="p-5 sm:p-6">
        <div>
            <h2 id="super-admin-password-title" class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Confirm Your Password') }}
            </h2>
            <p id="super-admin-password-description" class="mt-1 text-xs sm:text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                {{ __('This action requires Super Admin confirmation. Please enter your current password to proceed.') }}
            </p>
        </div>

        <form data-super-admin-password-form class="mt-5 space-y-4" autocomplete="off">
            @csrf
            <div>
                <label for="super_admin_confirm_password" class="block text-xs sm:text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    {{ __('Current Password') }} <span class="text-danger-600 dark:text-danger-400" aria-hidden="true">*</span>
                </label>
                <div class="relative mt-1.5" x-data="{ showPassword: false }">
                    <input
                        id="super_admin_confirm_password"
                        name="current_password"
                        type="password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        autocomplete="current-password"
                        class="block h-10 w-full rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-3 pr-10 text-sm text-neutral-900 dark:text-neutral-100 placeholder:text-neutral-400 shadow-xs focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20"
                        placeholder="Enter your current password"
                        data-super-admin-password-input
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors focus-visible:outline-none"
                        @click="showPassword = !showPassword"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        tabindex="-1"
                    >
                        <x-ui.icon name="eye" class="h-4 w-4" x-show="!showPassword" />
                        <x-ui.icon name="eye-slash" class="h-4 w-4" x-show="showPassword" x-cloak />
                    </button>
                </div>
                <p data-super-admin-password-error class="mt-1.5 hidden text-xs font-medium text-danger-600 dark:text-danger-400" role="alert"></p>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-ui.button type="button" variant="secondary" data-super-admin-password-cancel>
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button type="submit" data-super-admin-password-confirm data-loading-text="{{ __('Verifying...') }}">
                    {{ __('Confirm') }}
                </x-ui.button>
            </div>
        </form>
    </div>
</dialog>
