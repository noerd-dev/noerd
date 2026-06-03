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

        {{-- Break the compact list out to the full detail width, then re-pad so it aligns cleanly --}}
        <div class="-ml-6 -mr-6">
            <div class="mx-8">
                @livewire($component, $params, key($listKey))
            </div>
        </div>
    </div>
@endif
