{{-- Translatable single-line input. The light blue frame marks the field as
     language-dependent — the value shown belongs to the active setup language. --}}
@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'type' => 'text',
    'readonly' => false,
    'live' => false,
    'required' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    // The YAML field type is `translatableText` — never a valid HTML input type.
    $type = $field['type'] ?? $type;
    $type = in_array($type, ['text', 'email', 'url', 'tel'], true) ? $type : 'text';
    $readonly = $field['readonly'] ?? $readonly;
    $live = $field['live'] ?? $live;
    $required = $field['required'] ?? $required;
    $selectedLang = session('selectedLanguage') ?? 'de';
    // Filter out non-scalar values that leak from parent scope via @include
    $attributes = $attributes->filter(fn($value) => is_scalar($value) || null === $value);
@endphp

<div wire:key="{{ $name . $selectedLang }}" {{ $attributes->merge(['class' => '']) }}>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" />

    <input
        {{ $readonly ? 'readonly' : '' }}
        autocomplete="off"
        class="focus:ring-sky-300 block h-8 w-full appearance-none rounded-lg border border-sky-300 border-b-sky-400/70 bg-sky-50/30 py-2 ps-3 pe-3 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 shadow-xs read-only:border-b-sky-300/60 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 read-only:shadow-none focus:ring-2 focus:ring-offset-2 focus:outline-none sm:text-sm"
        type="{{ $type }}"
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($live)
            wire:model.live.debounce="{{ $name . '.' . $selectedLang }}"
        @else
            wire:model="{{ $name . '.' . $selectedLang }}"
        @endif
    />
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
