@props(['href' => null])

{{--
    A clickable row keeps <tr> semantics; the link lives in a cell so keyboard
    users can still tab to it. Pass :href to get the hover affordance.
--}}
<tr {{ $attributes->merge([
    'class' => 'border-b border-neutral-100 dark:border-neutral-800/80 last:border-0 transition-colors '
        .'odd:bg-white odd:dark:bg-neutral-900 even:bg-neutral-50/40 even:dark:bg-neutral-800/30 hover:bg-primary-50/50 dark:hover:bg-primary-950/30'
        .($href ? ' cursor-pointer' : ''),
]) }}
    @if ($href) onclick="window.location='{{ $href }}'" @endif
>
    {{ $slot }}
</tr>
