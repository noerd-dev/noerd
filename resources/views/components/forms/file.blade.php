@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'multiple' => false,
    'accept' => '',
    'live' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $multiple = $field['multiple'] ?? $multiple;
    $accept = $field['accept'] ?? $accept;
    $live = $field['live'] ?? $live;
@endphp

<div>
    @if ($label)
        <x-noerd::input-label for="{{ $name }}" :value="__($label)" />
    @endif
    <input
        type="file"
        id="{{ $name }}"
        @if ($live)
            wire:model.live="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        @if ($multiple) multiple @endif
        @if ($accept) accept="{{ $accept }}" @endif
        class="focus:ring-brand-border block h-10 w-full rounded-lg border border-zinc-200 bg-white py-2 ps-3 pe-3 text-base text-zinc-700 file:mr-4 file:rounded file:border-0 file:bg-zinc-100 file:px-4 file:py-1 file:text-sm file:font-medium file:text-zinc-700 focus:ring-2 focus:ring-offset-2 focus:outline-none sm:text-sm"
    />
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
