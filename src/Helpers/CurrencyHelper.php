<?php

namespace Noerd\Helpers;

use Noerd\Models\NoerdSettings;

class CurrencyHelper
{
    public const CURRENCY_PRESETS = [
        'EUR' => [
            'symbol' => '€',
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'symbol_position' => 'after',
        ],
        'USD' => [
            'symbol' => '$',
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_position' => 'before',
        ],
        'GBP' => [
            'symbol' => '£',
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_position' => 'before',
        ],
    ];

    protected static array $cache = [];

    public static function configForTenant(?int $tenantId = null): array
    {
        $tenantId ??= auth()->user()?->selected_tenant_id;

        if ($tenantId === null) {
            return config('noerd.currency', self::CURRENCY_PRESETS['EUR']);
        }

        if (isset(self::$cache[$tenantId])) {
            return self::$cache[$tenantId];
        }

        $settings = NoerdSettings::where('tenant_id', $tenantId)->first();
        $currencyCode = $settings?->currency ?? 'EUR';
        $config = self::CURRENCY_PRESETS[$currencyCode] ?? self::CURRENCY_PRESETS['EUR'];

        self::$cache[$tenantId] = $config;

        return $config;
    }

    public static function format(float $value, ?int $tenantId = null): string
    {
        $config = self::configForTenant($tenantId);
        $symbol = $config['symbol'] ?? '€';
        $dec = $config['decimal_separator'] ?? ',';
        $thou = $config['thousands_separator'] ?? '.';
        $pos = $config['symbol_position'] ?? 'after';

        $formatted = number_format($value, 2, $dec, $thou);

        return $pos === 'before'
            ? $symbol.' '.$formatted
            : $formatted.' '.$symbol;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
