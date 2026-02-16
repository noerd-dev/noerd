@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'readonly' => false,
    'live' => false,
    'required' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $readonly = $field['readonly'] ?? $readonly;
    $live = $field['live'] ?? $live;
    $required = $field['required'] ?? $required;
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required"/>

    <div class="flex gap-2">
        <input
            {{ $readonly ? 'readonly' : '' }}
            class="flex-1 border rounded-lg block read-only:shadow-none appearance-none text-base sm:text-sm py-2 h-9 leading-[1.375rem] ps-3 pe-3 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 read-only:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2"
            type="text"
            id="{{ $name }}"
            name="{{ $name }}"
            placeholder="#000000"
            maxlength="7"
            @if($live)
                wire:model.live.debounce="{{ $name }}"
            @else
                wire:model="{{ $name }}"
            @endif
        >
        <input
            {{ $readonly ? 'disabled' : '' }}
            type="color"
            class="h-9 w-12 p-0.5 border rounded-lg cursor-pointer disabled:cursor-not-allowed border-zinc-200 border-b-zinc-300/80"
            x-data
            x-bind:value="$wire.{{ $name }} || '#000000'"
            x-on:input="$wire.set('{{ $name }}', $event.target.value)"
        >
    </div>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
