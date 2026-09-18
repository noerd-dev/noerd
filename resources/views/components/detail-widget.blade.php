@props([
    'title' => null,
    'component' => null,
    'route' => null,
    'columns' => [],
    'arguments' => [],
    'wireKey' => null,
])

@php
    // Mount the embedded list in minimal mode. showMore* let the "Show more" link
    // re-open the same list as a full (non-minimal) modal, filtered identically.
    $params = array_merge($arguments, [
        'disableModal' => true,
        'minimal' => true,
        'minimalColumns' => $columns,
        'showMoreComponent' => $component,
        'showMoreRoute' => $route,
        'showMoreArguments' => $arguments,
    ]);

    $listKey = $wireKey ?? 'detail-widget-' . $component . '-' . md5(json_encode($arguments));
@endphp

@if ($component)
    {{-- The card has no padding to break out of: reset --noerd-page-inset so the
         embedded page stays inside the card instead of undoing the page body's --}}
    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white [--noerd-page-inset:0px]">
        @if (! empty($title))
            <div class="border-b border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">{{ __($title) }}</div>
        @endif

        @livewire($component, $params, key($listKey))
    </div>
@endif
