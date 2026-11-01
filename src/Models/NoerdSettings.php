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
