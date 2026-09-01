@props([
    'href' => null,
    'navigate' => false,
    'active' => false,
])

{{--
    One entry of an x-noerd::action-menu. Renders a link when `href` is given and
    a button otherwise, and closes the menu on click — the enclosing menu owns the
    `open` scope. Pass wire:click / wire:confirm through as normal attributes.
--}}

@php
    $menuItemClasses = 'flex w-full cursor-pointer items-center gap-2 px-4 py-2 text-left text-sm hover:bg-gray-50 '
        . ($active ? 'font-semibold text-gray-900' : 'font-normal text-gray-700');
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        @if ($navigate) wire:navigate @endif
        role="menuitem"
        tabindex="-1"
        x-on:click="open = false"
        {{ $attributes->merge(['class' => $menuItemClasses]) }}
    >{{ $slot }}</a>
@else
    <button
        type="button"
        role="menuitem"
        tabindex="-1"
        x-on:click="open = false"
        {{ $attributes->merge(['class' => $menuItemClasses]) }}
    >{{ $slot }}</button>
@endif
