@props([
    'title' => null,
    'component' => null,
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
        'showMoreArguments' => $arguments,
    ]);

    $listKey = $wireKey ?? 'detail-widget-' . $component . '-' . md5(json_encode($arguments));
@endphp

@if ($component)
    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
        @if (! empty($title))
            <div class="border-b border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">
                {{ __($title) }}
            </div>
        @endif

        {{-- The embedded page breaks out by -32px when disableModal is set; re-pad like detail-list --}}
        <div class="mx-8">
            @livewire($component, $params, key($listKey))
        </div>
    </div>
@endif
