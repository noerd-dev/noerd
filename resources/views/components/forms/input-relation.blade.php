@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'modalComponent' => '',
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
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required"/>
    <div class="flex">
        <input
            {{ $readonly ? 'readonly' : '' }}
            class="w-full border rounded-lg block read-only:shadow-none appearance-none text-base sm:text-sm py-2 h-9 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 read-only:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
            type="text"
            readonly
            id="{{ $name }}"
            name="{{ $name }}"
            @if($live)
                @if($relationField)
                    wire:model.live.debounce="{{ $relationField }}"
                @else
                    wire:model.live.debounce="relationTitles.{{ str_replace('model.', '', $name) }}"
                @endif
            @else
                @if($relationField)
                    wire:model="{{ $relationField }}"
                @else
                    wire:model.live.debounce="relationTitles.{{ str_replace('model.', '', $name) }}"
                @endif
            @endif
        >

        <x-noerd::buttons.primary
            wire:click="$dispatch('noerdModal', {component: '{{ $modalComponent }}', arguments: {id: {{ $modelId }}, context: '{{ $name }}'}})"
            class="h-9 rounded !mt-0 !ml-1"
            type="button"
        >
            <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
        </x-noerd::buttons.primary>
    </div>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
