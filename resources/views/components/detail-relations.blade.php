@props([
    'layout' => [],
    'modelId' => null,
    'modelClass' => '',
])

@php
    $relations = $layout['relations'] ?? [];
@endphp

@if ($modelId && ! empty($relations) && $modelClass)
    <livewire:noerd::relation-box
        :modelClass="$modelClass"
        :modelId="$modelId"
        :relations="$relations"
        :key="'relation-box-'.$modelId" />
@endif
