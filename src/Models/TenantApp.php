<?php

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TenantApp extends Model
{
    protected $guarded = [];

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Case-insensitive app-name match. `name` is canonically the UPPERCASE
     * module key, but historical rows exist in lowercase — every lookup
     * normalizes both sides through this ONE scope instead of scattering
     * whereRaw('LOWER(name) = ?') across middlewares.
     */
    public function scopeNamed(Builder $query, string $name): Builder
    {
        return $query->whereRaw('LOWER(name) = ?', [mb_strtolower(mb_trim($name))]);
    }

    /**
     * Case-insensitive match against several candidate names in one query.
     *
     * @param  array<int, string>  $names
     */
    public function scopeNamedAny(Builder $query, array $names): Builder
    {
        $normalized = array_map(fn(string $name): string => mb_strtolower(mb_trim($name)), $names);

        return $query->whereIn(
            $query->getQuery()->getConnection()->raw('LOWER(name)'),
            $normalized,
        );
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_app');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }
}
