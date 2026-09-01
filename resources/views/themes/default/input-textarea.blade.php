@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'placeholder' => null,
    'live' => false,
    'readonly' => false,
    'required' => false,
    'rows' => 8,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $placeholder = $field['placeholder'] ?? $placeholder;
    $live = $field['live'] ?? $live;
    $readonly = $field['readonly'] ?? $readonly;
    $required = $field['required'] ?? $required;
    $rows = $field['rows'] ?? $rows;
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" />

    <textarea
        @if ($placeholder) placeholder="{{ __($placeholder) }}" @endif
        @if ($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        class="focus:ring-brand-border block w-full appearance-none rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white py-2 ps-3 pe-3 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 shadow-xs read-only:border-b-zinc-200 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 read-only:shadow-none focus:ring-2 focus:ring-offset-2 focus:outline-none sm:text-sm"
        {{ $readonly ? 'readonly' : '' }}
    ></textarea>

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
