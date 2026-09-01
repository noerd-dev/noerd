@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'readonly' => false,
    'placeholder' => null,
    'live' => false,
    'required' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $readonly = $field['readonly'] ?? $readonly;
    $placeholder = $field['placeholder'] ?? $placeholder;
    $live = $field['live'] ?? $live;
    $required = $field['required'] ?? $required;
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" />

    <div class="flex gap-2">
        <input
            {{ $readonly ? 'readonly' : '' }}
            class="focus:ring-brand-border block h-8 flex-1 appearance-none rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white py-2 ps-3 pe-3 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 shadow-xs read-only:border-b-zinc-200 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 read-only:shadow-none focus:ring-2 focus:ring-offset-2 focus:outline-none sm:text-sm"
            type="text"
            id="{{ $name }}"
            name="{{ $name }}"
            placeholder="{{ $placeholder ? __($placeholder) : '#000000' }}"
            maxlength="7"
            @if ($live)
                wire:model.live.debounce="{{ $name }}"
            @else
                wire:model="{{ $name }}"
            @endif
        />
        <input
            {{ $readonly ? 'disabled' : '' }}
            type="color"
            class="h-9 w-12 cursor-pointer rounded-lg border border-zinc-200 border-b-zinc-300/80 p-0.5 disabled:cursor-not-allowed"
            x-data
            x-bind:value="$wire.{{ $name }} || '#000000'"
            x-on:input="$wire.set('{{ $name }}', $event.target.value)"
        />
    </div>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
