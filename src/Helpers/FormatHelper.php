<?php

declare(strict_types=1);

namespace Noerd\Helpers;

use Illuminate\Support\Carbon;

/**
 * Display formats for list cells and exports. A host pins them via
 * config('noerd.format.*'); unset keys derive from the active locale, so a
 * German UI keeps 31.12.2025 while every other locale gets ISO-style output.
 */
final class FormatHelper
{
    public static function dateFormat(): string
    {
        return config('noerd.format.date')
            ?? (app()->getLocale() === 'de' ? 'd.m.Y' : 'Y-m-d');
    }

    public static function dateTimeFormat(): string
    {
        return config('noerd.format.datetime')
            ?? (app()->getLocale() === 'de' ? 'd.m.Y H:i' : 'Y-m-d H:i');
    }

    public static function date(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format(self::dateFormat()) : '';
    }

    public static function dateTime(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format(self::dateTimeFormat()) : '';
    }

    public static function decimal(float $value, int $decimals = 2): string
    {
        $decimalSeparator = config('noerd.format.decimal_separator')
            ?? (app()->getLocale() === 'de' ? ',' : '.');
        $thousandsSeparator = config('noerd.format.thousands_separator')
            ?? (app()->getLocale() === 'de' ? '.' : ',');

        return number_format($value, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    public static function csvDelimiter(): string
    {
        return (string) config('noerd.format.csv_delimiter', ';');
    }
}
