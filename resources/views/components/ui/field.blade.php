@props([
    'name' => null,
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'disabled' => false,
    'placeholder' => null,
    'options' => null,
    'rows' => 3,
    'toggleable' => true,
    'icon' => null,
])

@php
    $id = $attributes->get('id') ?? $name;
    $hasError = $name && $errors->has($name);
    $describedBy = collect([
        $hint ? $id.'-hint' : null,
        $hasError ? $id.'-error' : null,
    ])->filter()->implode(' ');

    $control = 'block min-h-10 min-w-0 max-w-full w-full rounded-md border text-sm shadow-sm transition-colors '
        .'bg-white dark:bg-neutral-800 '
        .'placeholder:text-neutral-400 dark:placeholder:text-neutral-500 '
        .'focus:ring-2 focus:ring-offset-0 '
        .'disabled:bg-neutral-100 disabled:text-neutral-500 disabled:cursor-not-allowed '
        .'dark:disabled:bg-neutral-800 dark:disabled:text-neutral-500 '
        .($type === 'file'
            ? 'p-0 pr-3 leading-10 file:mr-3 file:h-10 file:border-0 file:border-r file:border-neutral-300 file:bg-neutral-50 file:px-3 file:text-sm file:font-medium file:text-neutral-700 dark:file:border-neutral-700 dark:file:bg-neutral-900 dark:file:text-neutral-200 '
            : '')
        .($icon ? 'min-h-11 pl-10 ' : '')
        .($hasError
            ? 'border-danger-500 text-danger-900 dark:text-danger-200 focus:border-danger-500 focus:ring-danger-500/30'
            : 'border-neutral-300 dark:border-neutral-700 text-neutral-900 dark:text-neutral-100 focus:border-primary-500 focus:ring-primary-500/30');

    $shared = $attributes->except(['class'])->merge([
        'id' => $id,
        'name' => $name,
        'class' => $control,
        'aria-invalid' => $hasError ? 'true' : null,
        'aria-describedby' => $describedBy ?: null,
    ]);
@endphp

<div class="min-w-0 max-w-full space-y-1.5">
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
            {{ $label }}
            @if ($required)
                <span class="text-danger-600 dark:text-danger-400" aria-hidden="true">*</span>
                <span class="sr-only">(required)</span>
            @endif
        </label>
    @endif

    @if ($type === 'select')
        <div class="relative min-w-0 max-w-full">
            @if ($icon)
                <span class="pointer-events-none absolute inset-y-0 left-0 z-10 flex w-10 items-center justify-center text-neutral-400 dark:text-neutral-500">
                    <x-ui.icon :name="$icon" class="h-5 w-5" />
                </span>
            @endif
            <select {{ $shared->merge(['class' => 'pr-10']) }} @required($required) @disabled($disabled)>
                @if ($placeholder)
                    <option value="">{{ $placeholder }}</option>
                @endif
                @if ($options)
                    @foreach ($options as $optValue => $optLabel)
                        <option value="{{ $optValue }}" @selected((string) old($name, $value) === (string) $optValue)>
                            {{ $optLabel }}
                        </option>
                    @endforeach
                @else
                    {{ $slot }}
                @endif
            </select>
        </div>
    @elseif ($type === 'textarea')
        <div class="relative min-w-0 max-w-full">
            @if ($icon)
                <span class="pointer-events-none absolute inset-y-0 left-0 z-10 flex w-10 items-center justify-center text-neutral-400 dark:text-neutral-500">
                    <x-ui.icon :name="$icon" class="h-5 w-5" />
                </span>
            @endif
            <textarea {{ $shared }} rows="{{ $rows }}" placeholder="{{ $placeholder }}"
                      @required($required) @disabled($disabled)>{{ old($name, $value) }}</textarea>
        </div>
    @elseif ($type === 'password' && $toggleable)
        <div class="relative min-w-0 max-w-full w-full" x-data="{ showPassword: false }">
            <input type="password" :type="showPassword ? 'text' : 'password'" value="{{ old($name, $value) }}" placeholder="{{ $placeholder }}"
                   {{ $shared->merge(['class' => 'pr-10']) }} @required($required) @disabled($disabled) />
            <button
                type="button"
                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-200 transition-colors focus-visible:outline-none focus:text-neutral-700 dark:focus:text-neutral-200 disabled:opacity-50 disabled:cursor-not-allowed"
                x-on:click="showPassword = !showPassword"
                x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'"
                x-bind:aria-pressed="showPassword.toString()"
                aria-controls="{{ $id }}"
                @disabled($disabled)
            >
                <x-ui.icon name="eye" class="w-4 h-4" x-show="!showPassword" />
                <x-ui.icon name="eye-slash" class="w-4 h-4" x-show="showPassword" x-cloak />
            </button>
        </div>
    @else
        <div class="relative min-w-0 max-w-full">
            @if ($icon)
                <span class="pointer-events-none absolute inset-y-0 left-0 z-10 flex w-10 items-center justify-center text-neutral-400 dark:text-neutral-500">
                    <x-ui.icon :name="$icon" class="h-5 w-5" />
                </span>
            @endif
            <input type="{{ $type }}" value="{{ old($name, $value) }}" placeholder="{{ $placeholder }}"
                   {{ $shared }} @required($required) @disabled($disabled) />
        </div>
    @endif

    @if ($hint && ! $hasError)
        <p id="{{ $id }}-hint" class="text-xs text-neutral-500 dark:text-neutral-400">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p id="{{ $id }}-error" class="flex items-start gap-1 text-xs font-medium text-danger-600 dark:text-danger-400">
            <x-ui.icon name="exclamation-triangle" class="w-3.5 h-3.5 mt-px shrink-0" />
            {{ $errors->first($name) }}
        </p>
    @endif
</div>
