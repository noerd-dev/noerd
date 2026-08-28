<?php

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Noerd\Database\Factories\TenantFactory;

/**
 * Extends Authenticatable ON PURPOSE: optional frontend modules authenticate a
 * tenant directly (e.g. liefertool registers an auth provider with
 * `model => Tenant::class` for the restaurant login), so the model must
 * satisfy the guard contract even though the core itself never logs a tenant in.
 */
class Tenant extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the UUID attribute with fallback to hash for backward compatibility.
     */
    public function getUuidAttribute(): ?string
    {
        // New projects use 'uuid' column, old projects use 'hash' column
        return $this->attributes['uuid'] ?? $this->attributes['hash'] ?? null;
    }

    /**
     * Set the UUID attribute with fallback to hash for backward compatibility.
     */
    public function setUuidAttribute(string $value): void
    {
        // For backward compatibility, always use 'hash' column for existing databases
        // The accessor will still return it as 'uuid'
        $this->attributes['hash'] = $value;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(NoerdUser::class, 'users_tenants', 'tenant_id', 'user_id')->withPivot('profile_id');
    }

    public function tenantApps(): BelongsToMany
    {
        return $this->belongsToMany(TenantApp::class, 'tenant_app')
            ->withPivot('is_hidden', 'sort_order')
            ->where('is_active', true)
            ->orderByPivot('sort_order');
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'tenant_id', 'id');
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }
}
