<?php

declare(strict_types=1);

namespace Noerd\Helpers;

use Illuminate\Database\QueryException;
use Noerd\Models\NoerdSettings;
use Noerd\Services\ThemeRegistry;

/**
 * Resolves the SYSTEM-WIDE default form theme configured by an admin under
 * Setup → System Settings, plus the "enforce" flag that lets the system theme
 * win over a deviating `theme:` in a detail YAML.
 *
 * Read on every detail render through StaticConfigHelper, so the per-tenant
 * lookup is memoized for the request (same shape as CurrencyHelper).
 */
class ThemeHelper
{
    public const FALLBACK_THEME = 'default';

    /** @var array<int, array{theme: string, enforced: bool}> */
    protected static array $cache = [];

    /**
     * @return array{theme: string, enforced: bool}
     */
    public static function forTenant(?int $tenantId = null): array
    {
        $tenantId ??= TenantHelper::getSelectedTenantId();

        if ($tenantId === null) {
            return self::configFallback();
        }

        if (isset(self::$cache[$tenantId])) {
            return self::$cache[$tenantId];
        }

        try {
            $settings = NoerdSettings::where('tenant_id', $tenantId)->first();
        } catch (QueryException) {
            // Read from the config layer, which every detail render passes through:
            // a not-yet-migrated settings table must never break a page.
            return self::configFallback();
        }

        $fallback = self::configFallback();

        return self::$cache[$tenantId] = [
            'theme' => self::normalizeTheme($settings?->detail_theme ?? $fallback['theme']),
            'enforced' => $settings !== null
                ? (bool) $settings->detail_theme_enforced
                : $fallback['enforced'],
        ];
    }

    public static function clearCache(): void
    {
        self::$cache = [];
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
