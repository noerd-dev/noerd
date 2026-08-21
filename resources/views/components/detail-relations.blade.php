@props([
    'layout' => [],
    'modelId' => null,
    'modelClass' => '',
])

@php
    $relations = $layout['relations'] ?? [];
    $hasContributedTiles = $modelClass !== ''
        && app(\Noerd\Services\RelationBoxRegistry::class)->for($modelClass) !== [];
@endphp

@if ($modelId && $modelClass && (! empty($relations) || $hasContributedTiles))
    <livewire:noerd::relation-box
        :modelClass="$modelClass"
        :modelId="$modelId"
        :relations="$relations"
        :key="'relation-box-' . $modelId"
    />
@endif
