{{-- Compact variant of forms.translatable-textarea: label sits to the LEFT, top-aligned.
     The light blue frame marks the field as language-dependent. --}}
@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'readonly' => false,
    'required' => false,
    'rows' => 8,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $readonly = $field['readonly'] ?? $readonly;
    $required = $field['required'] ?? $required;
    $rows = $field['rows'] ?? $rows;
    $selectedLang = \Noerd\Models\SetupLanguage::selectedCode();
    // Filter out non-scalar values that leak from parent scope via @include
    $attributes = $attributes->filter(fn($value) => is_scalar($value) || null === $value);
@endphp

<div wire:key="{{ $name . $selectedLang }}" class="flex items-start gap-2">
    <x-noerd::input-label
        for="{{ $name }}"
        :value="__($label)"
        :required="$required"
        :title="__($label)"
        class="w-36 shrink-0 truncate pt-1 pb-0!"
    />

    <div class="min-w-0 flex-1">
        <textarea
            wire:model="{{ $name . '.' . $selectedLang }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            class="focus:ring-sky-300 block w-full appearance-none rounded-sm border border-sky-300 bg-sky-50/30 py-1 ps-2 pe-2 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 focus:ring-1 focus:outline-none sm:text-sm"
            {{ $readonly ? 'readonly' : '' }}
        ></textarea>

        <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
    </div>
</div>
