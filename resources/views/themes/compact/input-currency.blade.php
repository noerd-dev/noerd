{{-- Compact variant of forms.input-currency: label sits to the LEFT of the input. --}}
@props([
    'field' => null,
    'name' => '',
    'label' => '',
    'readonly' => false,
    'placeholder' => null,
    'required' => false,
])

@php
    $name = $field['name'] ?? $name;
    $label = $field['label'] ?? $label;
    $readonly = $field['readonly'] ?? $readonly;
    $placeholder = $field['placeholder'] ?? $placeholder;
    $required = $field['required'] ?? $required;

    $currencyConfig = \Noerd\Helpers\CurrencyHelper::configForTenant();
    // configForTenant() always returns every key — no fallbacks needed.
    $symbol = $currencyConfig['symbol'];
    $decSep = $currencyConfig['decimal_separator'];
    $thousSep = $currencyConfig['thousands_separator'];
    $symbolPosition = $currencyConfig['symbol_position'];
@endphp

<div class="flex items-center gap-2">
    <x-noerd::input-label
        for="{{ $name }}"
        :value="__($label)"
        :required="$required"
        :title="__($label)"
        class="w-36 shrink-0 truncate pb-0!"
    />

    <div class="min-w-0 flex-1">
        <div
            class="relative"
            x-data="noerdCurrency({ name: @js($name), decSep: @js($decSep), thousSep: @js($thousSep) })"
        >
            @if ($symbolPosition === 'before')
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2 text-sm text-zinc-400">{{ $symbol }}</span>
            @endif

            {{-- wire:ignore on the INPUT: its displayed value is written imperatively
                 by Alpine and a morph of the input would drop the formatting. The
                 wrapper stays morphable so symbol/label/errors keep updating. --}}
            <input
                wire:ignore
                x-ref="input"
                {{ $readonly ? 'readonly' : '' }}
                autocomplete="off"
                @if ($placeholder) placeholder="{{ __($placeholder) }}" @endif
                class="w-full border border-zinc-200 rounded-sm block read-only:shadow-none appearance-none text-base sm:text-sm py-1 h-7 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 focus:outline-none focus:ring-1 focus:ring-brand-border text-right {{ $symbolPosition === 'before' ? 'ps-7 pe-2' : 'ps-2 pe-7' }}"
                type="text"
                inputmode="decimal"
                id="{{ $name }}"
                name="{{ $name }}"
                x-on:focus="onFocus($event)"
                x-on:blur="onBlur($event)"
            />

            @if ($symbolPosition === 'after')
                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2 text-sm text-zinc-400">{{ $symbol }}</span>
            @endif
        </div>

        <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
    </div>
</div>
