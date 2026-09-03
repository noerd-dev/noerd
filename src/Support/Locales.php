<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Locale;

/**
 * The FIXED list of locales noerd formats numbers, dates and currencies in.
 *
 * A locale is deliberately NOT a language: the language (Setup → Languages,
 * per tenant, extensible) decides which translation strings the UI shows, the
 * locale decides how 1234.56 and the 3rd of September look. A user may run a
 * German UI with `en-US` formatting. The list is closed on purpose — every
 * entry needs ICU data and a Carbon translation set, so neither a project nor
 * a tenant can register additional locales.
 */
final class Locales
{
    public const SUPPORTED = [
        'de-DE',
        'de-AT',
        'de-CH',
        'en-US',
        'en-GB',
        'en-IE',
        'fr-FR',
        'fr-CH',
        'it-IT',
        'it-CH',
        'nl-NL',
        'nl-BE',
        'es-ES',
        'pt-PT',
        'pl-PL',
        'cs-CZ',
        'da-DK',
        'sv-SE',
        'nb-NO',
        'fi-FI',
    ];

    public const DEFAULT = 'en-US';

    /**
     * The locale a bare language code maps to when nothing more specific is
     * configured — the interface language picks the formatting defaults.
     */
    private const LANGUAGE_DEFAULTS = [
        'de' => 'de-DE',
        'en' => 'en-US',
        'fr' => 'fr-FR',
        'it' => 'it-IT',
        'nl' => 'nl-NL',
        'es' => 'es-ES',
        'pt' => 'pt-PT',
        'pl' => 'pl-PL',
        'cs' => 'cs-CZ',
        'da' => 'da-DK',
        'sv' => 'sv-SE',
        'nb' => 'nb-NO',
        'no' => 'nb-NO',
        'fi' => 'fi-FI',
    ];

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array(self::normalize($locale), self::SUPPORTED, true);
    }

    /**
     * `de_de` / `de-de` → `de-DE`; anything without a region stays as given.
     */
    public static function normalize(string $locale): string
    {
        $parts = preg_split('/[-_]/', mb_trim($locale), 2) ?: [];
        $language = mb_strtolower($parts[0] ?? '');

        if (! isset($parts[1]) || $parts[1] === '') {
            return $language;
        }

        return $language . '-' . mb_strtoupper($parts[1]);
    }

    /**
     * The supported locale for a language code (`de` → `de-DE`). A supported
     * full tag is returned unchanged, anything unknown yields the default.
     */
    public static function defaultFor(?string $language): string
    {
        if ($language === null || mb_trim($language) === '') {
            return self::DEFAULT;
        }

        $normalized = self::normalize($language);

        if (in_array($normalized, self::SUPPORTED, true)) {
            return $normalized;
        }

        $languageOnly = explode('-', $normalized, 2)[0];

        return self::LANGUAGE_DEFAULTS[$languageOnly] ?? self::DEFAULT;
    }

    /**
     * @return array<string, string> locale => label, for `optionsMethod` selects
     */
    public static function options(?string $displayLanguage = null): array
    {
        $options = [];
        foreach (self::SUPPORTED as $locale) {
            $options[$locale] = self::label($locale, $displayLanguage);
        }

        return $options;
    }

    /**
     * Human-readable picker label: the locale's display name in the current
     * interface language plus a formatting sample, e.g.
     * "Deutsch (Deutschland) · 1.234,56 · 31.12.2026".
     */
    private static function label(string $locale, ?string $displayLanguage = null): string
    {
        $displayName = Locale::getDisplayName($locale, $displayLanguage ?? app()->getLocale()) ?: $locale;

        return $displayName . ' · ' . self::sample($locale);
    }

    /**
     * What a number and a date look like in the locale ("1.234,56 · 31.12.2026").
     */
    private static function sample(string $locale): string
    {
        $number = (string) Number::format(1234.56, precision: 2, locale: $locale);
        $date = Carbon::create(2026, 12, 31)->locale($locale)->isoFormat('L');

        return $number . ' · ' . $date;
    }
}
