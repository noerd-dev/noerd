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

    <div class="flex" x-data="{ v: $wire.entangle('{{ $name }}') }">
        <input
            {{ $readonly ? 'readonly' : '' }}
            autocomplete="off"
            @if ($placeholder) placeholder="{{ __($placeholder) }}" @endif
            class="focus:ring-brand-border block h-8 w-full appearance-none rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white py-2 ps-3 pe-3 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 shadow-xs read-only:border-b-zinc-200 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 read-only:shadow-none focus:ring-2 focus:ring-offset-2 focus:outline-none sm:text-sm"
            type="tel"
            id="{{ $name }}"
            name="{{ $name }}"
            @if ($live)
                wire:model.live.debounce="{{ $name }}"
            @else
                wire:model="{{ $name }}"
            @endif
        />

        <a
            x-cloak
            x-show="String(v ?? '').trim() !== ''"
            x-bind:href="'tel:' + (String(v ?? '').trim().startsWith('+') ? '+' : '') + String(v ?? '').replace(/\D/g, '')"
            class="mt-0! ml-1! inline-flex h-8 shrink-0 items-center rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white px-2 text-zinc-500 shadow-xs hover:text-zinc-700"
            title="{{ __('Call') }}"
        >
            <x-icon name="phone" class="h-4 w-4" />
        </a>
    </div>
    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
