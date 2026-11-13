<?php

declare(strict_types=1);

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Contracts\TenantSingletonContract;
use Noerd\Database\Factories\NoerdSettingsFactory;
use Noerd\Helpers\FormatHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Support\ThemeHelper;
use Noerd\Traits\TenantSingleton;

class NoerdSettings extends Model implements TenantSingletonContract
{
    use HasFactory;
    use TenantSingleton {
        forTenant as forTenantOrDefaults;
    }

    protected $table = 'noerd_settings';

    protected $guarded = ['id'];

    /**
     * The settings of a tenant (defaults to the selected tenant). Unlike the
     * trait's lookup this one never throws: the formatting and theme helpers
     * run for guests too, and a page without any tenant gets an empty instance
     * whose null attributes fall back to the config.
     */
    public static function forTenant(?int $tenantId = null): static
    {
        $tenantId ??= TenantHelper::getSelectedTenantId();

        if ($tenantId === null) {
            return static::query()->newModelInstance();
        }

        return static::forTenantOrDefaults($tenantId);
    }

    public static function clearCache(): void
    {
        static::forgetTenantSingletons();
    }

    /**
     * The interface languages a user can be given: the active setup languages of
     * the selected tenant, default first. A tenant without any falls back to the
     * default code, so the select and its validation never run empty.
     *
     * @return array<string, string> code => name
     */
    public static function userLanguageOptions(): array
    {
        $options = SetupLanguage::active()->pluck('name', 'code')->toArray();

        if ($options === []) {
            $code = SetupLanguage::defaultCode();
            $options = [$code => $code];
        }

        return $options;
    }

    /**
     * The interface language a new user starts with: System Settings → Default
     * Language while it is an active language, otherwise the tenant's default
     * setup language. Both read the selected tenant.
     */
    public static function defaultUserLanguage(): string
    {
        $configured = static::forTenant()->default_language;
        $options = static::userLanguageOptions();

        if (is_string($configured) && array_key_exists($configured, $options)) {
            return $configured;
        }

        $fallback = SetupLanguage::defaultCode();

        return array_key_exists($fallback, $options) ? $fallback : (string) array_key_first($options);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    protected static function newFactory(): NoerdSettingsFactory
    {
        return NoerdSettingsFactory::new();
    }

    protected static function booted(): void
    {
        // A saved or deleted row invalidates the memo AND every memo derived
        // from it, so a settings save is visible to formatting and theming in
        // the same request (and the same test) — no matter who wrote the row.
        static::saved(static function (): void {
            self::flushDerivedCaches();
        });
        static::deleted(static function (): void {
            self::flushDerivedCaches();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'detail_theme_enforced' => 'boolean',
        ];
    }

    /**
     * The memos that read this row: the settings memo itself plus the locale
     * and theme resolutions derived from it.
     */
    private static function flushDerivedCaches(): void
    {
        self::clearCache();
        FormatHelper::clearCache();
        ThemeHelper::clearCache();
    }
}
