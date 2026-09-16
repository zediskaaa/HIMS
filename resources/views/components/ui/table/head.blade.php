@props(['sticky' => true])

<thead {{ $attributes->merge([
    'class' => 'bg-neutral-50 dark:bg-neutral-800/90 '.($sticky ? 'sticky top-0 z-10' : ''),
]) }}>
    <tr class="border-b border-neutral-200 dark:border-neutral-800">
        {{ $slot }}
    </tr>
</thead>
