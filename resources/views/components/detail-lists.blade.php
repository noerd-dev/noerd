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
            // Resolve the $modelId token in the arguments (same convention as the relation box)
            $arguments = collect($list['arguments'] ?? [])
                ->map(fn ($value) => $value === '$modelId' ? $modelId : $value)
                ->all();
        @endphp

        <x-noerd::detail-list
            :title="$list['title'] ?? null"
            :description="$list['description'] ?? null"
            :component="$list['component'] ?? null"
            :arguments="$arguments"
            :lazy="$list['lazy'] ?? false"
            :wireKey="'detail-list-' . ($list['component'] ?? '') . '-' . $modelId" />
    @endforeach
@endif
