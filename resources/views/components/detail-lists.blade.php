@props([
    'layout' => [],
    'modelId' => null,
])

@php
    $lists = $layout['lists'] ?? [];
@endphp

@if ($modelId && ! empty($lists))
    @foreach ($lists as $list)
        @php
            $arguments = \Noerd\Support\LayoutArguments::resolve($list['arguments'] ?? [], $modelId, $__livewire ?? null);
        @endphp

        <x-noerd::detail-list
            :title="$list['title'] ?? null"
            :description="$list['description'] ?? null"
            :component="$list['component'] ?? null"
            :arguments="$arguments"
            :lazy="$list['lazy'] ?? false"
            :wireKey="'detail-list-' . ($list['component'] ?? '') . '-' . $modelId"
        />
    @endforeach
@endif
