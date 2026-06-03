{{-- Compact variant of forms.input-textarea: label sits to the LEFT, top-aligned with the textarea. --}}
@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'value' => '',
    'readonly' => false,
    'required' => false,
    'rows' => 8,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $value = $field['value'] ?? $value;
    $readonly = $field['readonly'] ?? $readonly;
    $required = $field['required'] ?? $required;
    $rows = $field['rows'] ?? $rows;
@endphp

<div class="flex items-start gap-2">
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" :title="__($label)" class="!pb-0 pt-1 w-36 shrink-0 truncate"/>

    <div class="flex-1 min-w-0">
        <textarea
            placeholder="{{ $value }}"
            wire:model="{{ $name }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            class="w-full border border-zinc-200 rounded-sm block appearance-none text-base sm:text-sm py-1 leading-[1.375rem] ps-2 pe-2 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 focus:outline-none focus:ring-1 focus:ring-brand-border"
            {{ $readonly ? 'readonly' : '' }}
            {{ $attributes->merge(['class' => '']) }}
        ></textarea>

        <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
    </div>
</div>
