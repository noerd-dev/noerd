{{-- Totals footer of a position block (net, one row per tax rate, gross); the
     vertical rhythm follows the active theme.

     `taxes` accepts both shapes used across the modules: a rate => amount map
     (['19' => 4.2]) and a list of rows ([['tax_rate' => 19, 'tax_total' => 4.2]]). --}}
@props([
    'theme' => 'default',
    'net' => 0,
    'gross' => 0,
    'taxes' => [],
    'currency' => 'EUR',
    'locale' => 'de',
])

@php
    $themeDefinition = app(\Noerd\Services\ThemeRegistry::class)->get($theme);
    $padding = $themeDefinition->totalsPadding;

    $taxRows = [];
    foreach ($taxes ?? [] as $taxKey => $taxValue) {
        $taxRows[] = is_array($taxValue)
            ? ['rate' => $taxValue['tax_rate'] ?? $taxKey, 'total' => $taxValue['tax_total'] ?? 0]
            : ['rate' => $taxKey, 'total' => $taxValue];
    }
@endphp

<div class="flex">
    <div class="ml-auto pl-4 pr-3 {{ $padding }} text-right text-sm font-normal text-gray-500 sm:pl-0">
        {{ __('Total Net') }}:
    </div>
    <div class="pl-3 pr-4 {{ $padding }} text-right text-sm text-gray-500 sm:pr-0">
        {{ \Illuminate\Support\Number::currency($net ?? 0, in: $currency, locale: $locale) }}
    </div>
</div>

@foreach ($taxRows as $taxRow)
    <div class="flex">
        <div class="ml-auto pl-4 pr-3 {{ $padding }} text-right text-sm font-normal text-gray-500 sm:pl-0">
            {{ __('Tax') }} {{ \Illuminate\Support\Number::percentage((float) $taxRow['rate'], precision: 2, locale: $locale) }}:
        </div>
        <div class="pl-3 pr-4 {{ $padding }} text-right text-sm text-gray-500 sm:pr-0">
            {{ \Illuminate\Support\Number::currency($taxRow['total'] ?? 0, in: $currency, locale: $locale) }}
        </div>
    </div>
@endforeach

<div class="flex">
    <div class="ml-auto pl-4 pr-3 {{ $padding }} text-right text-sm font-semibold text-black sm:pl-0">
        {{ __('Total Gross') }}:
    </div>
    <div class="pl-3 pr-4 {{ $padding }} text-right text-sm text-black font-semibold sm:pr-0">
        {{ \Illuminate\Support\Number::currency($gross ?? 0, in: $currency, locale: $locale) }}
    </div>
</div>
