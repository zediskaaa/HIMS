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
])

@php
    $id = $attributes->get('id') ?? $name;
    $hasError = $name && $errors->has($name);
    $describedBy = collect([
        $hint ? $id.'-hint' : null,
        $hasError ? $id.'-error' : null,
    ])->filter()->implode(' ');

    $control = 'block min-h-10 min-w-0 max-w-full w-full rounded-md border text-sm shadow-sm transition-colors '
        .'placeholder:text-neutral-400 '
        .'focus:ring-2 focus:ring-offset-0 '
        .'disabled:bg-neutral-100 disabled:text-neutral-500 disabled:cursor-not-allowed '
        .($hasError
            ? 'border-danger-500 text-danger-900 focus:border-danger-500 focus:ring-danger-500/30'
            : 'border-neutral-300 text-neutral-900 focus:border-primary-500 focus:ring-primary-500/30');

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
        <label for="{{ $id }}" class="block text-sm font-medium text-neutral-700">
            {{ $label }}
            @if ($required)
                <span class="text-danger-600" aria-hidden="true">*</span>
                <span class="sr-only">(required)</span>
            @endif
        </label>
    @endif

    @if ($type === 'select')
        <select {{ $shared }} @required($required) @disabled($disabled)>
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
    @elseif ($type === 'textarea')
        <textarea {{ $shared }} rows="{{ $rows }}" placeholder="{{ $placeholder }}"
                  @required($required) @disabled($disabled)>{{ old($name, $value) }}</textarea>
    @else
        <input type="{{ $type }}" value="{{ old($name, $value) }}" placeholder="{{ $placeholder }}"
               {{ $shared }} @required($required) @disabled($disabled) />
    @endif

    @if ($hint && ! $hasError)
        <p id="{{ $id }}-hint" class="text-xs text-neutral-500">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p id="{{ $id }}-error" class="flex items-start gap-1 text-xs font-medium text-danger-600">
            <x-ui.icon name="exclamation-triangle" class="w-3.5 h-3.5 mt-px shrink-0" />
            {{ $errors->first($name) }}
        </p>
    @endif
</div>
