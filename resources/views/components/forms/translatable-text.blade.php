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
@endphp

<div wire:key="{{ $name . (session('selectedLanguage') ?? 'de') }}" {{ $attributes->merge(['class' => '']) }}>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)"/>

    <input
        {{ $readonly ? 'readonly' : '' }}
        class="w-full border rounded-lg block read-only:shadow-none appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 read-only:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
        type="{{ $type }}"
        id="{{ $name }}"
        name="{{ $name }}"
        @if($live)
            wire:model.live.debounce="{{ $name . '.' . (session('selectedLanguage') ?? 'de') }}"
        @else
            wire:model="{{ $name . '.' . (session('selectedLanguage') ?? 'de') }}"
        @endif
    >
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
