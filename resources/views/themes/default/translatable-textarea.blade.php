{{-- Translatable textarea. The light blue frame marks the field as
     language-dependent — the value shown belongs to the active setup language. --}}
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
    $selectedLang = session('selectedLanguage') ?? 'de';
    // Filter out non-scalar values that leak from parent scope via @include
    $attributes = $attributes->filter(fn($value) => is_scalar($value) || null === $value);
@endphp

<div wire:key="{{ $name . $selectedLang }}" {{ $attributes->merge(['class' => '']) }}>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" />

    <textarea
        wire:model="{{ $name . '.' . $selectedLang }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        class="focus:ring-sky-300 block w-full appearance-none rounded-lg border border-sky-300 border-b-sky-400/70 bg-sky-50/30 py-2 ps-3 pe-3 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 shadow-xs read-only:border-b-sky-300/60 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 read-only:shadow-none focus:ring-2 focus:ring-offset-2 focus:outline-none sm:text-sm"
        {{ $readonly ? 'readonly' : '' }}
    ></textarea>

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
