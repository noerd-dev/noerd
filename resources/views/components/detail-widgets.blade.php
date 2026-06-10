@props([
    'layout' => [],
    'modelId' => null,
])

@php
    $widgets = $layout['widgets'] ?? [];
@endphp

@if ($modelId && ! empty($widgets))
    <div class="flex flex-col gap-6 first:pt-6">
        @foreach ($widgets as $widget)
            @php
                // Resolve the $modelId token in the arguments (same convention as detail-lists)
                $arguments = collect($widget['arguments'] ?? [])
                    ->map(fn ($value) => $value === '$modelId' ? $modelId : $value)
                    ->all();
            @endphp

            <x-noerd::detail-widget
                :title="$widget['title'] ?? null"
                :component="$widget['component'] ?? null"
                :columns="$widget['columns'] ?? []"
                :arguments="$arguments"
                :wireKey="'detail-widget-' . ($widget['component'] ?? '') . '-' . $modelId" />
        @endforeach
    </div>
@endif
