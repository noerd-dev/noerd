<?php

namespace Noerd\Helpers;

class CurrencyHelper
{
    public static function format(float $value): string
    {
        $config = config('noerd.currency', []);
        $symbol = $config['symbol'] ?? '€';
        $dec = $config['decimal_separator'] ?? ',';
        $thou = $config['thousands_separator'] ?? '.';
        $pos = $config['symbol_position'] ?? 'after';

        $formatted = number_format($value, 2, $dec, $thou);

        return $pos === 'before'
            ? $symbol.' '.$formatted
            : $formatted.' '.$symbol;
    }
}
