@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'type' => 'text',
    'readonly' => false,
    'live' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $type = $field['type'] ?? $type;
    $readonly = $field['readonly'] ?? $readonly;
    $live = $field['live'] ?? $live;
    // Filter out non-scalar values that leak from parent scope via @include
    $attributes = $attributes->filter(fn($value) => is_scalar($value) || null === $value);
@endphp

<div wire:key="{{ $name . (session('selectedLanguage') ?? 'de') }}" {{ $attributes->merge(['class' => '']) }}>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" />

    <input
        {{ $readonly ? 'readonly' : '' }}
        class="focus:ring-brand-border block h-10 w-full appearance-none rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white py-2 ps-3 pe-3 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 shadow-xs read-only:border-b-zinc-200 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 read-only:shadow-none focus:ring-2 focus:ring-offset-2 focus:outline-none sm:text-sm"
        type="{{ $type }}"
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($live)
            wire:model.live.debounce="{{ $name . '.' . (session('selectedLanguage') ?? 'de') }}"
        @else
            wire:model="{{ $name . '.' . (session('selectedLanguage') ?? 'de') }}"
        @endif
    />
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
