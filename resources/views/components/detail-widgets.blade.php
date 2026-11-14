@props([
    'layout' => [],
    'modelId' => null,
])

@php
    $widgets = $layout['widgets'] ?? [];
@endphp

@if ($modelId && ! empty($widgets))
    <div class="flex flex-col gap-6">
        @foreach ($widgets as $widget)
            @php
                $arguments = \Noerd\Support\LayoutArguments::resolve($widget['arguments'] ?? [], $modelId, $__livewire ?? null);
            @endphp

            <x-noerd::detail-widget
                :title="$widget['title'] ?? null"
                :component="$widget['component'] ?? null"
                :showMoreRoute="$widget['showMoreModalRoute'] ?? null"
                :showMoreComponent="$widget['showMoreModalComponent'] ?? null"
                :columns="$widget['columns'] ?? []"
                :arguments="$arguments"
                :wireKey="'detail-widget-' . ($widget['component'] ?? '') . '-' . $modelId"
            />
        @endforeach
    </div>
@endif
