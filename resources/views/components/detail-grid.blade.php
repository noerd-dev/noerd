@props([
    'layout' => [],
    'modelId' => null,
])

@php
    $widgets = $layout['widgets'] ?? [];
    $hasWidgets = $modelId && ! empty($widgets);
@endphp

@if ($hasWidgets)
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 lg:col-span-2">{{ $slot }}</div>
        <aside class="min-w-0 lg:col-span-1">
            <x-noerd::detail-widgets :layout="$layout" :modelId="$modelId" />
        </aside>
    </div>
@else
    {{ $slot }}
@endif
