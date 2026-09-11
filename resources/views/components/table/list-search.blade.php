{{--
    The list's search field: the first element of the filter row, OUTSIDE the
    horizontally scrolling filter strip, so it never scrolls out of view and its
    focus ring (`ring-2` + `ring-offset-2`) is never clipped by a scroll container.
    A fixed width keeps the strip's free space predictable.

    Expects: $host (the NoerdList Livewire component).
--}}
@php
    $searchShortcut = \Noerd\Helpers\KeyboardShortcutHelper::parse('search_focus', 's');
@endphp

<div
    wire:key="list-search"
    class="relative w-40 shrink-0 sm:w-56"
    x-data="{ searchFocused: false }"
    @keydown.window="let e = $event; if ((window.noerdTopLayer?.($el) ?? true) && ({{ $searchShortcut['js'] }})) { e.preventDefault(); $refs.searchInput.focus(); }"
>
    <x-noerd::text-input
        x-ref="searchInput"
        @focus="searchFocused = true"
        @blur="searchFocused = false"
        @keydown.escape="$refs.searchInput.blur()"
        placeholder="{{ __('Search') }}"
        wire:model.live.debounce.300ms="search"
        type="text"
        class="mt-0! h-8 w-full pr-8"
    />
    {{-- The shortcut badge only advertises a keyboard affordance, so it stays on
         keyboard widths — on a phone it would just crowd the field. --}}
    <kbd
        x-show="! searchFocused"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="pointer-events-none absolute top-1/2 right-1.5 hidden -translate-y-1/2 rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500 lg:block"
    >{{ $searchShortcut['badge'] }}</kbd>
</div>
