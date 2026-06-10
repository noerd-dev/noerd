{{-- Compact variant of forms.input-currency: label sits to the LEFT of the input. --}}
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

<div class="flex items-center gap-2">
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required" :title="__($label)" class="!pb-0 w-36 shrink-0 truncate"/>

    <div class="flex-1 min-w-0">
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
                <span class="absolute inset-y-0 left-0 flex items-center pl-2 text-zinc-400 text-sm pointer-events-none">{{ $symbol }}</span>
            @endif

            <input
                x-ref="input"
                {{ $readonly ? 'readonly' : '' }}
                autocomplete="off"
                class="w-full border border-zinc-200 rounded-sm block read-only:shadow-none appearance-none text-base sm:text-sm py-1 h-7 bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 focus:outline-none focus:ring-1 focus:ring-brand-border text-right {{ $symbolPosition === 'before' ? 'ps-7 pe-2' : 'ps-2 pe-7' }}"
                type="text"
                inputmode="decimal"
                id="{{ $name }}"
                name="{{ $name }}"
                x-on:focus="onFocus($event)"
                x-on:blur="onBlur($event)"
            >

            @if($symbolPosition === 'after')
                <span class="absolute inset-y-0 right-0 flex items-center pr-2 text-zinc-400 text-sm pointer-events-none">{{ $symbol }}</span>
            @endif
        </div>

        <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
    </div>
</div>
