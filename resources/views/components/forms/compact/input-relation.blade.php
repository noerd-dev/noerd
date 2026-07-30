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
            \Illuminate\Support\Str::before($modalComponent, '-list'),
        ) . '-detail';
    }

    $wireModel = $relationField ?: 'relationTitles.' . str_replace('detailData.', '', $name);
@endphp

<div class="flex items-center gap-2">
    <x-noerd::input-label
        for="{{ $name }}"
        :value="__($label)"
        :required="$required"
        :title="__($label)"
        class="w-36 shrink-0 truncate !pb-0"
    />
    <div class="min-w-0 flex-1">
        <div class="flex">
            <input
                {{ $readonly ? 'readonly' : '' }}
                class="focus:ring-brand-border block h-7 w-full cursor-pointer appearance-none rounded-sm border border-zinc-200 bg-white py-1 ps-2 pe-2 text-base text-zinc-700 placeholder-zinc-400 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 focus:ring-1 focus:outline-none sm:text-sm"
                type="text"
                readonly
                id="{{ $name }}"
                name="{{ $name }}"
                @click="$wire.{{ $wireModel }} ? $wire.openRelationDetail('{{ $detailComponent }}', '{{ $name }}') : $modal('{{ $modalComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $name }}', listActionMethod: 'selectAction'})"
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
                class="!mt-0 !ml-1 inline-flex h-7 items-center px-2 text-zinc-400 hover:text-zinc-600"
                type="button"
            >
                <x-noerd::icons.x-mark class="h-5 w-5"></x-noerd::icons.x-mark>
            </button>

            <x-noerd::button
                @click="$modal('{{ $modalComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $name }}', listActionMethod: 'selectAction'})"
                class="!mt-0 !ml-1 !h-7 rounded-sm !px-2"
                type="button"
            >
                <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
            </x-noerd::button>
        </div>
        <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
    </div>
</div>
