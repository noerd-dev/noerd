{{-- Compact variant of forms.phone: label sits to the LEFT of the input. --}}
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

<div class="flex items-center gap-2">
    <x-noerd::input-label
        for="{{ $name }}"
        :value="__($label)"
        :required="$required"
        :title="__($label)"
        class="w-36 shrink-0 truncate !pb-0"
    />

    <div class="min-w-0 flex-1">
        <div class="flex" x-data="{ v: $wire.entangle('{{ $name }}') }">
            <input
                {{ $readonly ? 'readonly' : '' }}
                autocomplete="off"
                @if ($placeholder) placeholder="{{ __($placeholder) }}" @endif
                class="focus:ring-brand-border block h-7 w-full appearance-none rounded-sm border border-zinc-200 bg-white py-1 ps-2 pe-2 text-base text-zinc-700 placeholder-zinc-400 read-only:text-zinc-500 read-only:placeholder-zinc-400/70 focus:ring-1 focus:outline-none sm:text-sm"
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
                class="!mt-0 !ml-1 inline-flex h-7 shrink-0 items-center rounded-sm border border-zinc-200 bg-white px-1.5 text-zinc-500 hover:text-zinc-700"
                title="{{ __('Call') }}"
            >
                <x-icon name="phone" class="h-4 w-4" />
            </a>
        </div>
        <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
    </div>
</div>
