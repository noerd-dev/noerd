<?php

declare(strict_types=1);

namespace Noerd\Helpers;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Noerd\Models\NoerdSettings;
use Noerd\Support\Locales;
use NumberFormatter;

/**
 * Locale-aware display formatting of numbers, dates and times (ICU).
 *
 * Two locales exist: the USER locale (`noerd_user_settings.format_locale`)
 * formats everything a user looks at in the backend — lists, details,
 * dashboards, exports — and the TENANT locale (`noerd_settings.locale`)
 * formats DOCUMENTS: PDFs, receipts, customer e-mails, everything that leaves
 * the system and must not depend on who happened to generate it. The
 * `document*()` methods and `tenantLocale()` serve the latter.
 *
 * Resolution: user locale → tenant locale → `config('noerd.format.locale')` →
 * the default locale of the interface language (`Locales::defaultFor()`).
 * A host may still pin `config('noerd.format.date|datetime|decimal_separator|
 * thousands_separator')`; a pinned format always wins over the locale.
 */
final class FormatHelper
{
    private static ?string $override = null;

    /** @var array<string, string> */
    private static array $localeCache = [];

    /**
     * The locale of the current user (falls back to the tenant locale).
     */
    public static function locale(?int $tenantId = null): string
    {
        if (self::$override !== null) {
            return self::$override;
        }

        $user = NoerdAuth::user();
        $cacheKey = ($user?->id ?? 0) . '|' . ($tenantId ?? TenantHelper::getSelectedTenantId() ?? 0);

        return self::$localeCache[$cacheKey] ??= (function () use ($user, $tenantId): string {
            $userLocale = $user?->format_locale;

            return Locales::isSupported($userLocale)
                ? Locales::normalize($userLocale)
                : self::tenantLocale($tenantId);
        })();
    }

    /**
     * The document locale of a tenant — independent of the acting user.
     */
    public static function tenantLocale(?int $tenantId = null): string
    {
        if (self::$override !== null) {
            return self::$override;
        }

        $settingsLocale = NoerdSettings::forTenant($tenantId)?->locale;
        if (Locales::isSupported($settingsLocale)) {
            return Locales::normalize($settingsLocale);
        }

        $configured = config('noerd.format.locale');
        if (is_string($configured) && Locales::isSupported($configured)) {
            return Locales::normalize($configured);
        }

        return Locales::defaultFor(app()->getLocale());
    }

    public static function clearCache(): void
    {
        self::$localeCache = [];
    }

    /**
     * A decimal with a fixed number of fraction digits (amounts without a
     * currency symbol, CSV cells).
     */
    public static function decimal(float $value, int $decimals = 2, ?string $locale = null): string
    {
        $locale ??= self::locale();
        $decimalSeparator = config('noerd.format.decimal_separator');
        $thousandsSeparator = config('noerd.format.thousands_separator');

        if ($decimalSeparator !== null || $thousandsSeparator !== null) {
            $symbols = self::numberSymbols($locale);

            return number_format(
                $value,
                $decimals,
                (string) ($decimalSeparator ?? $symbols['decimal']),
                (string) ($thousandsSeparator ?? $symbols['group']),
            );
        }

        return (string) Number::format($value, precision: $decimals, locale: $locale);
    }

    /**
     * A quantity: trailing zeros are dropped up to `$maxDecimals` fraction digits.
     */
    public static function number(float $value, ?int $maxDecimals = 2, ?string $locale = null): string
    {
        return (string) Number::format($value, maxPrecision: $maxDecimals, locale: $locale ?? self::locale());
    }

    public static function percent(float $value, int $decimals = 0, ?string $locale = null): string
    {
        return (string) Number::percentage($value, precision: $decimals, locale: $locale ?? self::locale());
    }

    public static function date(mixed $value, ?string $locale = null): string
    {
        $carbon = self::carbon($value);
        if ($carbon === null) {
            return '';
        }

        $pinned = config('noerd.format.date');
        if (is_string($pinned) && $pinned !== '') {
            return $carbon->format($pinned);
        }

        return $carbon->locale($locale ?? self::locale())->isoFormat('L');
    }

    public static function dateTime(mixed $value, ?string $locale = null): string
    {
        $carbon = self::carbon($value);
        if ($carbon === null) {
            return '';
        }

        $pinned = config('noerd.format.datetime');
        if (is_string($pinned) && $pinned !== '') {
            return $carbon->format($pinned);
        }

        return $carbon->locale($locale ?? self::locale())->isoFormat('L LT');
    }

    public static function time(mixed $value, ?string $locale = null): string
    {
        $carbon = self::carbon($value);

        return $carbon === null ? '' : $carbon->locale($locale ?? self::locale())->isoFormat('LT');
    }

    /**
     * A date on a document (PDF, receipt, customer e-mail): tenant locale.
     */
    public static function documentDate(mixed $value, ?int $tenantId = null): string
    {
        return self::date($value, self::tenantLocale($tenantId));
    }

    public static function documentDateTime(mixed $value, ?int $tenantId = null): string
    {
        return self::dateTime($value, self::tenantLocale($tenantId));
    }

    public static function documentDecimal(float $value, int $decimals = 2, ?int $tenantId = null): string
    {
        return self::decimal($value, $decimals, self::tenantLocale($tenantId));
    }

    public static function csvDelimiter(): string
    {
        return (string) config('noerd.format.csv_delimiter', ';');
    }

    /**
     * The decimal and grouping separators ICU uses for a locale.
     *
     * @return array{decimal: string, group: string}
     */
    public static function numberSymbols(string $locale): array
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);

        return [
            'decimal' => (string) $formatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL),
            'group' => (string) $formatter->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL),
        ];
    }

    private static function carbon(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse($value);
    }
}
