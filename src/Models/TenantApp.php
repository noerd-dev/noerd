<?php

declare(strict_types=1);

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Noerd\Database\Factories\TenantAppFactory;
use Noerd\Helpers\StaticConfigHelper;

class TenantApp extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Case-insensitive match against several candidate names in one query.
     * `name` is canonically the UPPERCASE module key, but historical rows exist
     * in lowercase — every lookup normalizes both sides through this ONE scope
     * instead of scattering LOWER(name) comparisons across middlewares.
     *
     * @param  array<int, string>  $names
     */
    public function scopeNamedAny(Builder $query, array $names): Builder
    {
        return $query->whereIn(
            $query->getQuery()->getConnection()->raw('LOWER(name)'),
            array_map(self::normalizeName(...), $names),
        );
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_app');
    }

    /**
     * The config search roots memoise the active/allowed app folders — a
     * tenant-app mutation (install commands, setup screens) invalidates them.
     */
    protected static function booted(): void
    {
        static::saved(fn() => StaticConfigHelper::flushRuntimeCaches());
        static::deleted(fn() => StaticConfigHelper::flushRuntimeCaches());
    }

    protected static function newFactory(): TenantAppFactory
    {
        return TenantAppFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    private static function normalizeName(string $name): string
    {
        return mb_strtolower(mb_trim($name));
    }
}
