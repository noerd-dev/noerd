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

    $currencyConfig = \Noerd\Helpers\CurrencyHelper::configForTenant(auth()->user()?->selected_tenant_id);
    $symbol = $currencyConfig['symbol'] ?? '€';
    $decSep = $currencyConfig['decimal_separator'] ?? ',';
    $thousSep = $currencyConfig['thousands_separator'] ?? '.';
    $symbolPosition = $currencyConfig['symbol_position'] ?? 'after';
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required"/>

    <div class="relative" wire:ignore.self x-data="{
        rawValue: $wire.get('{{ $name }}'),
        decSep: '{{ $decSep }}',
        thousSep: '{{ $thousSep }}',
        formatDisplay(val) {
            let num = parseFloat(val);
            if (isNaN(num)) num = 0;
            let parts = num.toFixed(2).split('.');
            let intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.thousSep);
            return intPart + this.decSep + parts[1];
        },
        parseInput(val) {
            if (typeof val === 'number') return val;
            let cleaned = String(val).replace(/\s/g, '');
            if (this.decSep === ',') {
                cleaned = cleaned.replace(/\./g, '').replace(',', '.');
            } else {
                cleaned = cleaned.replace(/,/g, '');
            }
            let num = parseFloat(cleaned);
            return isNaN(num) ? 0 : num;
        },
        showFormatted() {
            this.$refs.input.value = this.formatDisplay(this.rawValue);
        },
        onFocus(e) {
            let num = parseFloat(this.rawValue);
            e.target.value = isNaN(num) ? '' : num.toFixed(2).replace('.', this.decSep);
            this.$nextTick(() => e.target.select());
        },
        onBlur(e) {
            let parsed = this.parseInput(e.target.value);
            this.rawValue = parsed;
            $wire.set('{{ $name }}', parsed);
            this.showFormatted();
        }
    }" x-init="$nextTick(() => showFormatted())">
        @if($symbolPosition === 'before')
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-400 text-sm pointer-events-none">{{ $symbol }}</span>
        @endif

        <input
            x-ref="input"
            {{ $readonly ? 'readonly' : '' }}
            autocomplete="off"
            class="w-full border rounded-lg block read-only:shadow-none appearance-none text-base sm:text-sm py-2 h-8 leading-[1.375rem] bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 read-only:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2 text-right {{ $symbolPosition === 'before' ? 'ps-8 pe-3' : 'ps-3 pe-8' }}"
            type="text"
            inputmode="decimal"
            id="{{ $name }}"
            name="{{ $name }}"
            x-on:focus="onFocus($event)"
            x-on:blur="onBlur($event)"
        >

        @if($symbolPosition === 'after')
            <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400 text-sm pointer-events-none">{{ $symbol }}</span>
        @endif
    </div>

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
