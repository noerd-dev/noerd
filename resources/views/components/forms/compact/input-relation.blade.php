{{-- Compact variant of forms.input-relation: label sits to the LEFT of the relation control. --}}
@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'modalComponent' => '',
    'detailComponent' => '',
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
    if (empty($detailComponent) && !empty($modalComponent)) {
        $detailComponent = \Illuminate\Support\Str::singular(
            \Illuminate\Support\Str::before($modalComponent, '-list')
        ) . '-detail';
    }

    $wireModel = $relationField ?: 'relationTitles.' . str_replace('detailData.', '', $name);
@endphp

<div class="flex items-center gap-2">
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" :title="__($label)" class="!pb-0 w-36 shrink-0 truncate"/>
    <div class="flex-1 min-w-0">
        <div class="flex">
            <input
                {{ $readonly ? 'readonly' : '' }}
                class="w-full cursor-pointer border border-zinc-200 rounded-sm block appearance-none text-base sm:text-sm py-1 h-7 ps-2 pe-2 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 focus:outline-none focus:ring-1 focus:ring-brand-border"
                type="text"
                readonly
                id="{{ $name }}"
                name="{{ $name }}"
                @click="$wire.{{ $wireModel }} ? $wire.openRelationDetail('{{ $detailComponent }}', '{{ $name }}') : $modal('{{ $modalComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $name }}', listActionMethod: 'selectAction'})"
                @if($live)
                    wire:model.live.debounce="{{ $wireModel }}"
                @else
                    wire:model="{{ $wireModel }}"
                @endif
            >

            <button
                x-show="$wire.{{ $wireModel }}"
                x-cloak
                @click="$wire.clearRelation('{{ $name }}')"
                class="h-7 inline-flex items-center px-2 !mt-0 !ml-1 text-zinc-400 hover:text-zinc-600"
                type="button"
            >
                <x-noerd::icons.x-mark class="w-5 h-5"></x-noerd::icons.x-mark>
            </button>

            <x-noerd::button
                @click="$modal('{{ $modalComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $name }}', listActionMethod: 'selectAction'})"
                class="!h-7 !px-2 rounded-sm !mt-0 !ml-1"
                type="button"
            >
                <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
            </x-noerd::button>
        </div>
        <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
    </div>
</div>
