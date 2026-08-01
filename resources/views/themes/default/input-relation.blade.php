@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'modalComponent' => '',
    'detailComponent' => '',
    'detailRoute' => null,
    'relationField' => null,
    'modelId' => 0,
    'readonly' => false,
    'live' => false,
    'required' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $modalComponent = $field['modalComponent'] ?? $modalComponent;
    $relationField = $field['relationField'] ?? $relationField;
    $modelId = $field['modelId'] ?? $modelId;
    $readonly = $field['readonly'] ?? $readonly;
    $live = $field['live'] ?? $live;
    $required = $field['required'] ?? $required;

    $detailComponent = $field['detailComponent'] ?? $detailComponent;
    // Preferred jump target for the already-selected record; the component stays
    // as the fallback. The magnifier below opens a PICKER and stays component-based.
    $detailRoute = $field['detailRoute'] ?? $detailRoute;
    if (empty($detailComponent) && !empty($modalComponent)) {
        $detailComponent = \Illuminate\Support\Str::singular(
            \Illuminate\Support\Str::before($modalComponent, '-list'),
        ) . '-detail';
    }

    $wireModel = $relationField ?: 'relationTitles.' . str_replace('detailData.', '', $name);
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" />
    <div class="flex">
        <input
            {{ $readonly ? 'readonly' : '' }}
            class="focus:ring-brand-border block h-8 w-full cursor-pointer appearance-none rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white py-2 ps-3 pe-3 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 shadow-xs read-only:border-b-zinc-200 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 read-only:shadow-none focus:ring-2 focus:ring-offset-2 focus:outline-none sm:text-sm"
            type="text"
            readonly
            id="{{ $name }}"
            name="{{ $name }}"
            @click="$wire.{{ $wireModel }} ? $wire.openRelationDetail('{{ $detailComponent }}', '{{ $name }}', {{ \Illuminate\Support\Js::from($detailRoute ?: null) }}) : $modal('{{ $modalComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $name }}', listActionMethod: 'selectAction'})"
            @if ($live)
                wire:model.live.debounce="{{ $wireModel }}"
            @else
                wire:model="{{ $wireModel }}"
            @endif
        />

        <button
            x-show="$wire.{{ $wireModel }}"
            x-cloak
            @click="$wire.clearRelation('{{ $name }}')"
            class="!mt-0 !ml-1 inline-flex h-8 items-center px-2 text-zinc-400 hover:text-zinc-600"
            type="button"
        >
            <x-noerd::icons.x-mark class="h-5 w-5"></x-noerd::icons.x-mark>
        </button>

        <x-noerd::button
            @click="$modal('{{ $modalComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $name }}', listActionMethod: 'selectAction'})"
            class="!mt-0 !ml-1 h-8 rounded"
            type="button"
        >
            <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
        </x-noerd::button>
    </div>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
