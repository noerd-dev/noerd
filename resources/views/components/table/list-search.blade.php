{{--
    The list header's search field. Rendered ONCE: below `xl` it sits in the filter
    drawer, from `xl` on the header row — the same element, re-laid-out by CSS, so
    there is no second copy whose wire:key, Alpine state and keyboard shortcut would
    have to be kept apart from this one.

    Expects: $host (the NoerdList Livewire component).
--}}
@php
    $searchShortcut = \Noerd\Helpers\KeyboardShortcutHelper::parse('search_focus', 's');
@endphp

<div
    wire:key="list-search"
    class="relative max-xl:w-full xl:shrink-0"
    x-data="{ searchFocused: false }"
    @keydown.window="let e = $event; if ({{ $searchShortcut['js'] }}) { e.preventDefault(); $refs.searchInput.focus(); }"
>
    <x-noerd::text-input
        x-ref="searchInput"
        @focus="searchFocused = true"
        @blur="searchFocused = false"
        @keydown.escape="$refs.searchInput.blur()"
        placeholder="{{ __('Search') }}"
        wire:model.live.debounce.300ms="search"
        type="text"
        class="mt-0! h-8 w-full xl:min-w-[200px] xl:pr-8"
    />
    {{-- The shortcut badge only advertises a keyboard affordance, so it stays on
         keyboard widths — in the drawer it would just crowd the field. --}}
    <kbd
        x-show="! searchFocused"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="pointer-events-none absolute top-1/2 right-1.5 hidden -translate-y-1/2 rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500 xl:block"
    >{{ $searchShortcut['badge'] }}</kbd>
</div>
