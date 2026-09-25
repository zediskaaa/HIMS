@props([
    'name' => 'otp',
    'length' => 6,
    'value' => '',
    'error' => '',
    'id' => 'otp',
])

<div class="space-y-2.5">
    <div
        class="otp-code-grid grid grid-cols-6 gap-1 sm:gap-3"
        role="group"
        aria-label="{{ __('Verification code') }}"
        aria-describedby="{{ $id }}-feedback"
        x-bind:class="{ 'otp-code-grid--shake': shaking }"
        x-on:animationend="shaking = false"
    >
        <template x-for="(_, index) in digits" :key="index">
            <input
                data-otp-digit
                type="text"
                x-bind:id="'{{ $id }}-' + index"
                class="h-12 min-w-0 w-full rounded-lg border bg-white text-center font-mono text-xl font-semibold tabular-nums text-neutral-900 shadow-sm outline-none transition-[border-color,background-color,color,box-shadow,transform] duration-150 focus-visible:border-primary-500 focus-visible:ring-2 focus-visible:ring-primary-500/30 disabled:cursor-wait disabled:opacity-70 motion-reduce:transition-none dark:bg-neutral-800 dark:text-neutral-100"
                x-model="digits[index]"
                x-bind:class="digitClasses(index)"
                x-bind:aria-label="'Digit ' + (index + 1) + ' of ' + length"
                x-bind:aria-invalid="state === 'error' ? 'true' : 'false'"
                x-bind:autocomplete="index === 0 ? 'one-time-code' : 'off'"
                x-bind:disabled="validating || state === 'success' || (typeof isExpired !== 'undefined' && isAuthenticator && isExpired)"
                x-on:input="handleInput(index, $event)"
                x-on:keydown="handleKeydown(index, $event)"
                x-on:paste="handlePaste(index, $event)"
                x-on:focus="$event.target.select()"
                inputmode="numeric"
                pattern="[0-9]"
                maxlength="1"
                required
            >
        </template>
    </div>

    <input type="hidden" name="{{ $name }}" x-bind:value="code">

    <p
        id="{{ $id }}-feedback"
        class="min-h-5 text-sm"
        x-bind:class="state === 'error' ? 'text-danger-600 dark:text-danger-400' : (state === 'success' ? 'text-success-700 dark:text-success-400' : 'text-neutral-500 dark:text-neutral-400')"
        aria-live="assertive"
        aria-atomic="true"
        x-text="message"
    ></p>

    <noscript>
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ $value }}"
            type="text"
            class="block h-12 w-full rounded-lg border-neutral-300 bg-white px-3.5 text-center font-mono text-xl text-neutral-900 shadow-sm focus:border-primary-500 focus:ring-primary-500"
            inputmode="numeric"
            pattern="[0-9]{6}"
            maxlength="{{ $length }}"
            autocomplete="one-time-code"
            required
        >
        @if ($error)
            <p class="mt-2 text-sm text-danger-600">{{ $error }}</p>
        @endif
    </noscript>
</div>
