@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'picklistField' => '',
    'live' => false,
    'readonly' => false,
    'required' => false,
    'placeholder' => null,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $picklistField = $field['picklistField'] ?? $picklistField;
    $live = $field['live'] ?? $live;
    $readonly = $field['readonly'] ?? $readonly;
    $required = $field['required'] ?? $required;
    $placeholder = $field['placeholder'] ?? $placeholder;
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" />
    <select
        @if ($live)
            wire:model.live.debounce="{{ $name }}"
        @else
            wire:model="{{ $name }}"
        @endif
        class="focus:ring-brand-border block h-8 w-full appearance-none rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white py-1 ps-3 pe-3 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 shadow-xs focus:ring-2 focus:ring-offset-2 focus:outline-none disabled:border-b-zinc-200 disabled:text-zinc-500 disabled:placeholder-zinc-400/70 disabled:shadow-none sm:text-sm"
        @if ($readonly) disabled @endif
        id="{{ $name }}"
    >
        @if ($placeholder)
            <option value="">{{ __($placeholder) }}</option>
        @endif
        @foreach ($this->resolvePicklistOptions($picklistField) as $key => $value)
            <option value="{{ $key }}">{{ $value }}</option>
        @endforeach
    </select>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
