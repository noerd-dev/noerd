<?php

declare(strict_types=1);

namespace Noerd\Helpers;

use Illuminate\Support\Number;
use Noerd\Models\NoerdSettings;
use NumberFormatter;

/**
 * Money formatting. The CURRENCY is a tenant setting (Setup → System Settings,
 * `noerd_settings.currency`): every amount in the system is an amount in that
 * currency. HOW it is written depends on the reader: `format()` uses the
 * current user's locale (backend UI), `formatForDocument()` the tenant's
 * document locale (PDFs, receipts, customer e-mails). Both go through ICU, so
 * `1234.56` in USD reads `$1,234.56` for an `en-US` reader and `1.234,56 $`
 * for a `de-DE` reader.
 */
final class CurrencyHelper
{
    /**
     * The currencies a tenant may choose from (ISO 4217 code => English name;
     * the name is a translation key).
     */
    public const CURRENCIES = [
        'EUR' => 'Euro',
        'USD' => 'US Dollar',
        'GBP' => 'British Pound',
        'CHF' => 'Swiss Franc',
        'CZK' => 'Czech Koruna',
        'DKK' => 'Danish Krone',
    ];

    /** The currency used when neither the tenant nor the config names a valid one. */
    public const DEFAULT_CURRENCY = 'EUR';

    /** @var array<string, array{code: string, symbol: string, decimal_separator: string, thousands_separator: string, symbol_position: string}> */
    protected static array $configCache = [];

    /**
     * The ISO currency code of a tenant (defaults to the selected tenant);
     * `config('noerd.currency.default')` when the tenant has no valid setting.
     */
    public static function codeForTenant(?int $tenantId = null): string
    {
        $code = NoerdSettings::forTenant($tenantId)?->currency;

        if (is_string($code) && isset(self::CURRENCIES[$code])) {
            return $code;
        }

        // The configured default is the ONE fallback — validated against the
        // supported list so a typo cannot produce an unformattable currency.
        $configured = (string) config('noerd.currency.default', self::DEFAULT_CURRENCY);

        return isset(self::CURRENCIES[$configured]) ? $configured : self::DEFAULT_CURRENCY;
    }

    /**
     * An amount for the backend UI: tenant currency, reader's locale.
     */
    public static function format(float $value, ?int $tenantId = null, ?string $locale = null): string
    {
        return self::formatIn($value, self::codeForTenant($tenantId), $locale ?? FormatHelper::locale($tenantId));
    }

    /**
     * An amount on a document (PDF, receipt, customer e-mail): tenant
     * currency, tenant locale — independent of the acting user.
     */
    public static function formatForDocument(float $value, ?int $tenantId = null): string
    {
        return self::formatIn($value, self::codeForTenant($tenantId), FormatHelper::tenantLocale($tenantId));
    }

    /**
     * An amount in an EXPLICIT currency (e.g. the stored currency of an
     * imported bank transaction), in the reader's locale unless given.
     */
    public static function formatIn(float $value, string $currency, ?string $locale = null): string
    {
        return (string) Number::currency($value, in: $currency, locale: $locale ?? FormatHelper::locale());
    }

    /**
     * The currency symbol as the given locale writes it (`€`, `$`, `CHF`).
     */
    public static function symbol(?int $tenantId = null, ?string $locale = null): string
    {
        return self::configForTenant($tenantId, $locale)['symbol'];
    }

    /**
     * The tenant currency's rendering rules in a locale — consumed by the
     * `input-currency` field (Alpine formats the typed value client-side).
     *
     * @return array{code: string, symbol: string, decimal_separator: string, thousands_separator: string, symbol_position: 'before'|'after'}
     */
    public static function configForTenant(?int $tenantId = null, ?string $locale = null): array
    {
        $code = self::codeForTenant($tenantId);
        $locale ??= FormatHelper::locale($tenantId);

        return self::$configCache[$code . '|' . $locale] ??= self::icuConfig($code, $locale);
    }

    /**
     * Select options for the tenant setting, sample in the reader's locale:
     * `EUR - Euro (1.234,56 €)`.
     *
     * @return array<string, string> code => label
     */
    public static function options(?string $locale = null): array
    {
        $locale ??= FormatHelper::locale();
        $options = [];

        foreach (self::CURRENCIES as $code => $name) {
            $options[$code] = $code . ' - ' . __($name) . ' (' . self::formatIn(1234.56, $code, $locale) . ')';
        }

        return $options;
    }

    public static function clearCache(): void
    {
        self::$configCache = [];
        NoerdSettings::clearCache();
    }

    /**
     * @return array{code: string, symbol: string, decimal_separator: string, thousands_separator: string, symbol_position: 'before'|'after'}
     */
    private static function icuConfig(string $code, string $locale): array
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $code);

        $decimalSeparator = (string) $formatter->getSymbol(NumberFormatter::MONETARY_SEPARATOR_SYMBOL);
        $thousandsSeparator = (string) $formatter->getSymbol(NumberFormatter::MONETARY_GROUPING_SEPARATOR_SYMBOL);

        // The symbol is whatever ICU prints around the digits: strip digits,
        // separators and every kind of space from a formatted sample.
        $sample = (string) $formatter->formatCurrency(1234.56, $code);
        $symbol = mb_trim((string) preg_replace('/[\p{N}\p{Z}\s.,\x{2019}\x{27}]+/u', '', $sample));

        return [
            'code' => $code,
            'symbol' => $symbol !== '' ? $symbol : $code,
            'decimal_separator' => $decimalSeparator !== '' ? $decimalSeparator : '.',
            'thousands_separator' => $thousandsSeparator,
            'symbol_position' => preg_match('/^\p{N}/u', mb_trim($sample)) === 1 ? 'after' : 'before',
        ];
    }
}
