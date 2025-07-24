@props(['active'])

@php
    $classes = ($active ?? false)
                ? 'bg-gray-500 hover:bg-gray-50 block rounded-md py-2 pr-2 pl-10 text-sm leading-6 font-semibold text-gray-700'
                : 'hover:bg-gray-50 block rounded-md py-2 pr-2 pl-10 text-sm leading-6 font-semibold text-gray-700';
@endphp

<a wire:navigate {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
