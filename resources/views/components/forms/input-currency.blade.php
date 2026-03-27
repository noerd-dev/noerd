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

    $currencyConfig = config('noerd.currency', []);
    $symbol = $currencyConfig['symbol'] ?? '€';
    $decSep = $currencyConfig['decimal_separator'] ?? ',';
    $thousSep = $currencyConfig['thousands_separator'] ?? '.';
    $symbolPosition = $currencyConfig['symbol_position'] ?? 'after';
@endphp

<div>
    <x-noerd::input-label for="{{ $name }}" :value="__($label)" :required="$required"/>

    <div class="relative" x-data="{
        focused: false,
        rawValue: $wire.get('{{ $name }}'),
        decSep: '{{ $decSep }}',
        thousSep: '{{ $thousSep }}',
        formatValue(val) {
            let num = parseFloat(val);
            if (isNaN(num)) num = 0;
            let parts = num.toFixed(2).split('.');
            let intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.thousSep);
            return intPart + this.decSep + parts[1];
        },
        get displayValue() {
            if (this.focused) {
                let num = parseFloat(this.rawValue);
                if (isNaN(num)) return '';
                return num.toFixed(2);
            }
            return this.formatValue(this.rawValue);
        },
        onFocus(e) {
            this.focused = true;
            this.$nextTick(() => e.target.select());
        },
        onBlur(e) {
            let parsed = parseFloat(e.target.value);
            if (isNaN(parsed)) parsed = 0;
            this.rawValue = parsed;
            $wire.set('{{ $name }}', parsed);
            this.focused = false;
        },
        onInput(e) {
            this.rawValue = e.target.value;
        }
    }" x-init="rawValue = $wire.get('{{ $name }}')">
        @if($symbolPosition === 'before')
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-400 text-sm pointer-events-none">{{ $symbol }}</span>
        @endif

        <input
            {{ $readonly ? 'readonly' : '' }}
            autocomplete="off"
            class="w-full border rounded-lg block read-only:shadow-none appearance-none text-base sm:text-sm py-2 h-9 leading-[1.375rem] bg-white text-zinc-700 read-only:text-zinc-500 placeholder-zinc-400 read-only:placeholder-zinc-400/70 shadow-xs border-zinc-200 border-b-zinc-300/80 read-only:border-b-zinc-200 focus:outline-none focus:ring-2 focus:ring-brand-border focus:ring-offset-2 text-right {{ $symbolPosition === 'before' ? 'ps-8 pe-3' : 'ps-3 pe-8' }}"
            type="text"
            inputmode="decimal"
            id="{{ $name }}"
            name="{{ $name }}"
            :value="displayValue"
            x-on:focus="onFocus($event)"
            x-on:blur="onBlur($event)"
            x-on:input="onInput($event)"
        >

        @if($symbolPosition === 'after')
            <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400 text-sm pointer-events-none">{{ $symbol }}</span>
        @endif
    </div>

    <x-noerd::input-error :messages="$errors->get($name)" class="mt-2"/>
</div>
