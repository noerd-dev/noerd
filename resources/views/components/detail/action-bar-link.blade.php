@props(['active'])

@php
    $classes = ($active ?? false)
                ? 'border-black p-0 py-3 rounded-sm text-sm  group inline-flex items-center border-b-2 text-sm'
                : '';
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</div>

