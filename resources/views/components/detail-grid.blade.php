@props([
    'layout' => [],
    'modelId' => null,
])

@php
    $widgets = $layout['widgets'] ?? [];
    $hasWidgets = $modelId && ! empty($widgets);
@endphp

@if($hasWidgets)
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 min-w-0">
            {{ $slot }}
        </div>
        <aside class="lg:col-span-1 min-w-0">
            <x-noerd::detail-widgets :layout="$layout" :modelId="$modelId" />
        </aside>
    </div>
@else
    {{ $slot }}
@endif
