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
    <x-noerd::input-label
        for="{{ $name }}"
        :value="__($label)"
        :required="$required"
        :title="__($label)"
        class="w-36 shrink-0 truncate pt-1 !pb-0"
    />

    <div class="min-w-0 flex-1">
        <textarea
            placeholder="{{ $value }}"
            wire:model="{{ $name }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            class="focus:ring-brand-border block w-full appearance-none rounded-sm border border-zinc-200 bg-white py-1 ps-2 pe-2 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 focus:ring-1 focus:outline-none sm:text-sm"
            {{ $readonly ? 'readonly' : '' }}
            {{ $attributes->merge(['class' => '']) }}
        ></textarea>

        <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
    </div>
</div>
