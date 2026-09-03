<?php

declare(strict_types=1);

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Database\Factories\NoerdSettingsFactory;
use Noerd\Helpers\FormatHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Helpers\ThemeHelper;

class NoerdSettings extends Model
{
    use HasFactory;

    protected $table = 'noerd_settings';

    protected $guarded = ['id'];

    /**
     * Request-scoped memo of the tenant singleton rows: the currency and the
     * document locale are read for every currency cell of a list, so the row
     * is fetched once per tenant (a missing row is memoized as null too).
     *
     * @var array<int, self|null>
     */
    protected static array $cache = [];

    /**
     * The settings row of a tenant (defaults to the selected tenant), or null
     * when the tenant has none yet or no tenant is selected.
     */
    public static function forTenant(?int $tenantId = null): ?self
    {
        $tenantId ??= TenantHelper::getSelectedTenantId();

        if ($tenantId === null) {
            return null;
        }

        if (array_key_exists($tenantId, self::$cache)) {
            return self::$cache[$tenantId];
        }

        return self::$cache[$tenantId] = static::query()->where('tenant_id', $tenantId)->first();
    }

    public static function clearCache(): void
    {
        self::$cache = [];
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
