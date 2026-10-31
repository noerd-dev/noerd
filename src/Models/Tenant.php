<?php

declare(strict_types=1);

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Noerd\Database\Factories\TenantFactory;
use Noerd\Events\TenantAppAssigned;

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

    /**
     * Give this tenant an app. The ONE write path of an assignment — the apps
     * screen, the install commands, the demo, provisioning — so every module
     * listening for TenantAppAssigned (an app folder, a hidden companion app)
     * hears every assignment. The event fires only for an app the tenant did
     * not hold before; a repeated call changes nothing and returns false.
     *
     * @param  TenantApp|int|string  $app  the app row, its id or its name as stored
     * @param  array{is_hidden?: bool, sort_order?: int}  $pivot  pivot values; the sort order defaults to the end of the tenant's app bar
     */
    public function assignApp(TenantApp|int|string $app, array $pivot = []): bool
    {
        $tenantApp = match (true) {
            $app instanceof TenantApp => $app,
            is_int($app) => TenantApp::query()->find($app),
            default => TenantApp::query()->where('name', $app)->first(),
        };

        if (! $tenantApp instanceof TenantApp) {
            return false;
        }

        $relation = $this->tenantApps();

        if ($relation->newPivotStatementForId($tenantApp->getKey())->exists()) {
            return false;
        }

        if (! array_key_exists('sort_order', $pivot)) {
            $pivot['sort_order'] = ((int) $relation->newPivotStatement()->where('tenant_id', $this->getKey())->max('sort_order')) + 1;
        }

        $relation->attach($tenantApp->getKey(), $pivot);
        $this->unsetRelation('tenantApps');

        TenantAppAssigned::dispatch((int) $this->getKey(), (string) $tenantApp->name);

        return true;
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }
}
