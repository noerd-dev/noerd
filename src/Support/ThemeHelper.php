<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Database\QueryException;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Services\ThemeRegistry;

/**
 * Resolves the SYSTEM-WIDE default form theme configured by an admin under
 * Setup → System Settings, plus the "enforce" flag that lets the system theme
 * win over a deviating `theme:` in a detail YAML.
 *
 * Read on every detail render through StaticConfigHelper, so the per-tenant
 * lookup is memoized for the request (same shape as CurrencyHelper).
 *
 * @internal
 */
final class ThemeHelper
{
    public const FALLBACK_THEME = 'default';

    /**
     * @return array{theme: string, enforced: bool}
     */
    public static function forTenant(?int $tenantId = null): array
    {
        $tenantId ??= TenantHelper::getSelectedTenantId();

        if ($tenantId === null) {
            return self::configFallback();
        }

        $memo = &RequestState::bucket('noerd.theme');

        if (isset($memo[$tenantId])) {
            return $memo[$tenantId];
        }

        try {
            // ONE settings memo for the whole package (currency, locale, theme).
            $settings = NoerdSettings::forTenant($tenantId);
        } catch (QueryException) {
            // Read from the config layer, which every detail render passes through:
            // a not-yet-migrated settings table must never break a page.
            return self::configFallback();
        }

        $fallback = self::configFallback();

        // A tenant without a settings row answers an unsaved instance: the
        // config decides both values then.
        return $memo[$tenantId] = [
            'theme' => self::normalizeTheme($settings->detail_theme ?? $fallback['theme']),
            'enforced' => $settings->exists
                ? (bool) $settings->detail_theme_enforced
                : $fallback['enforced'],
        ];
    }

    public static function clearCache(): void
    {
        RequestState::forget('noerd.theme');
    }

    /**
     * Normalizes the theme of a resolved detail/page layout: a theme whose
     * registration is gone falls back to the built-in default.
     *
     * @param  array<string, mixed>  $layout
     */
    public static function fromLayout(array $layout): string
    {
        $theme = $layout['theme'] ?? self::FALLBACK_THEME;

        return self::normalizeTheme(is_string($theme) ? $theme : self::FALLBACK_THEME);
    }

    /**
     * @return array{theme: string, enforced: bool}
     */
    private static function configFallback(): array
    {
        return [
            'theme' => self::normalizeTheme(config('noerd.theme.default', self::FALLBACK_THEME)),
            'enforced' => (bool) config('noerd.theme.enforced', false),
        ];
    }

    /**
     * A theme whose registration is gone (folder removed, YAML typo) must
     * never break a detail page — it falls back to the built-in default.
     */
    private static function normalizeTheme(?string $theme): string
    {
        if ($theme === null || $theme === '') {
            return self::FALLBACK_THEME;
        }

        return app(ThemeRegistry::class)->has($theme) ? $theme : self::FALLBACK_THEME;
    }
}
