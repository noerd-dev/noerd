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

    $currencyConfig = \Noerd\Helpers\CurrencyHelper::configForTenant();
    $symbol = $currencyConfig['symbol'] ?? '€';
    $decSep = $currencyConfig['decimal_separator'] ?? ',';
    $thousSep = $currencyConfig['thousands_separator'] ?? '.';
    $symbolPosition = $currencyConfig['symbol_position'] ?? 'after';
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" />

    <div
        class="relative"
        wire:ignore.self
        x-data="noerdCurrency({ name: @js($name), decSep: @js($decSep), thousSep: @js($thousSep) })"
    >
        @if ($symbolPosition === 'before')
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-zinc-400">{{ $symbol }}</span>
        @endif

        <input
            x-ref="input"
            {{ $readonly ? 'readonly' : '' }}
            autocomplete="off"
            @if ($placeholder) placeholder="{{ __($placeholder) }}" @endif
            class="w-full border rounded-lg block read-only:shadow-none appearance-none text-base sm:text-sm py-2 h-8 leading-[1.375rem] bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 read-only:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2 text-right {{ $symbolPosition === 'before' ? 'ps-8 pe-3' : 'ps-3 pe-8' }}"
            type="text"
            inputmode="decimal"
            id="{{ $name }}"
            name="{{ $name }}"
            x-on:focus="onFocus($event)"
            x-on:blur="onBlur($event)"
        />

        @if ($symbolPosition === 'after')
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-zinc-400">{{ $symbol }}</span>
        @endif
    </div>

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2" />
</div>
