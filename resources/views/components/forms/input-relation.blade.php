@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'modalComponent' => '',
    'relationField' => null,
    'modelId' => 0,
    'disabled' => false,
    'live' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $modalComponent = $field['modalComponent'] ?? $modalComponent;
    $relationField = $field['relationField'] ?? $relationField;
    $modelId = $field['modelId'] ?? $modelId;
    $disabled = $field['disabled'] ?? $disabled;
    $live = $field['live'] ?? $live;
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)"/>
    <div class="flex">
        <input
            {{ $disabled ? 'disabled' : '' }}
            class="w-full border rounded-lg block disabled:shadow-none dark:shadow-none appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white dark:bg-white/10 dark:disabled:bg-white/[7%] text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 dark:text-zinc-300 dark:disabled:text-zinc-400 dark:placeholder-zinc-400 dark:disabled:placeholder-zinc-500 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 dark:border-white/10 dark:disabled:border-white/5 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2"
            type="text"
            disabled
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
            class="h-9rounded !mt-0 !ml-1"
            type="button"
        >
            <x-noerd::icons.magnifying-glass></x-noerd::icons.magnifying-glass>
        </x-noerd::buttons.primary>
    </div>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
