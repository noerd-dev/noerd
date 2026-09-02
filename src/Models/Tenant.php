<?php

declare(strict_types=1);

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(NoerdUser::class, 'users_tenants', 'tenant_id', 'user_id')->withPivot('profile_key');
    }

    public function tenantApps(): BelongsToMany
    {
        return $this->belongsToMany(TenantApp::class, 'tenant_app')
            ->withPivot('is_hidden', 'sort_order')
            ->where('is_active', true)
            ->orderByPivot('sort_order');
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
