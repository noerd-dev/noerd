@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex bg-[#009ab1] items-center px-1 pt-1 border-b-0 border-transparent text-sm font-medium leading-5 text-gray-900 focus:outline-hidden focus:border-indigo-700 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-0 border-transparent text-sm font-medium leading-5 text-gray-900 hover:text-gray-300 hover:border-gray-300 focus:outline-hidden focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out';
@endphp

<a wire:navigate {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
