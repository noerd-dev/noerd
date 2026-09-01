@props([
    'align' => 'right',
    'width' => 'w-56',
    'label' => null,
    'anchor' => null,
    'wrapperClass' => 'relative inline-block text-left',
    'panelClass' => null,
])

{{--
    The dropdown menu primitive: an Alpine `open` scope, a trigger and a
    role="menu" panel. Every menu in the framework and in the modules builds on
    this instead of repeating the markup — the panel chrome, the escape key and
    the click-outside behaviour live here once.

    The `trigger` slot replaces the default kebab button. It renders INSIDE the
    Alpine scope, so a custom trigger toggles the menu with `@click="open = ! open"`.

    `anchor` takes an x-anchor reference expression (e.g. `$refs.sortButton`) for
    menus inside a scrolling or overflow-hidden container, where the default
    absolute positioning would be clipped.
--}}

@php
    $menuOrigin = $align === 'left' ? 'left-0 origin-top-left' : 'right-0 origin-top-right';

    $menuPanelClasses = 'z-90 rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-hidden ' . $width;

    if (! $anchor) {
        $menuPanelClasses .= ' absolute mt-2 ' . $menuOrigin;
    }

    if ($panelClass) {
        $menuPanelClasses .= ' ' . $panelClass;
    }
@endphp

<div
    x-data="{ open: false }"
    @click.outside="open = false"
    @keydown.escape.stop="open = false"
    {{ $attributes->merge(['class' => $wrapperClass]) }}
>
    @isset($trigger)
        {{ $trigger }}
    @else
        <button
            type="button"
            @click="open = ! open"
            :aria-expanded="open"
            aria-haspopup="true"
            class="inline-flex cursor-pointer items-center justify-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset hover:bg-gray-50"
        >
            <span class="sr-only">{{ $label ?? __('Actions') }}</span>
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm9 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm9 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
            </svg>
        </button>
    @endisset

    <div
        x-show="open"
        x-cloak
        x-transition
        @if ($anchor) x-anchor.bottom-end="{{ $anchor }}" @endif
        role="menu"
        aria-orientation="vertical"
        tabindex="-1"
        class="{{ $menuPanelClasses }}"
    >
        {{ $slot }}
    </div>
</div>
