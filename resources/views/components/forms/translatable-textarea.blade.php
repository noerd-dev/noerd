@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'readonly' => false,
    'required' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $readonly = $field['readonly'] ?? $readonly;
    $required = $field['required'] ?? $required;
    // Filter out non-scalar values that leak from parent scope via @include
    $attributes = $attributes->filter(fn($value) => is_scalar($value) || null === $value);
@endphp

<div wire:key="{{ $name . (session('selectedLanguage') ?? 'de') }}" {{ $attributes->merge(['class' => '']) }}>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" />

    <textarea
        wire:model="{{ $name . '.' . (session('selectedLanguage') ?? 'de') }}"
        name="{{ $name }}"
        rows="8"
        class="focus:ring-brand-border block w-full appearance-none rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white py-2 ps-3 pe-3 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 shadow-xs read-only:border-b-zinc-200 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 read-only:shadow-none focus:ring-2 focus:ring-offset-2 focus:outline-none sm:text-sm"
        {{ $readonly ? 'readonly' : '' }}
    ></textarea>

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
