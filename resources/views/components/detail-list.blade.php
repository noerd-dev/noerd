@props([
    'title' => null,
    'description' => null,
    'component' => null,
    'arguments' => [],
    'lazy' => false,
    'wireKey' => null,
])

@php
    // Force the embedded list into its compact, full-width variant
    $params = array_merge($arguments, ['disableModal' => true, 'compact' => true]);

    // Livewire reads "lazy" straight from the params array (SupportLazyLoading::mount)
    if ($lazy) {
        $params['lazy'] = true;
    }

    $listKey = $wireKey ?? 'detail-list-' . $component . '-' . md5(json_encode($arguments));
@endphp

@if ($component)
    <div class="my-6">
        @if (! empty($title))
            @include('noerd::components.detail.block-head', [
                'title' => __($title),
                'description' => __($description ?? ''),
            ])
        @endif

        {{-- The list breaks out to the page edge on its own (--noerd-page-inset) --}}
        @livewire($component, $params, key($listKey))
    </div>
@endif
