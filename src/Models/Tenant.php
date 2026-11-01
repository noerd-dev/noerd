<?php

declare(strict_types=1);

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;
use Noerd\Database\Factories\TenantFactory;
use Noerd\Events\TenantAppAssigned;
use Noerd\Events\TenantMemberAdded;
use Noerd\Events\TenantMemberRemoved;
use Noerd\Services\ProfileRegistry;
use Noerd\Support\DefaultCountries;
use Noerd\Support\SetupCollectionDefinitionImport;
use Noerd\Support\TenantContext;

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

    /**
     * Make the account a member of this tenant with the given profile. The ONE
     * write path of a membership — the user screen, tenant creation, the
     * console commands — so every module listening for TenantMemberAdded hears
     * every new membership. A repeated call changes nothing and returns false
     * (setMemberProfile() changes the profile of a member).
     *
     * @param  string|null  $profileKey  a key of the ProfileRegistry; null = no profile (behaves like User)
     *
     * @throws InvalidArgumentException for a profile key nobody registered
     */
    public function addMember(NoerdUser $user, ?string $profileKey): bool
    {
        $this->assertProfileKey($profileKey);

        $relation = $this->users();

        if ($relation->newPivotStatementForId($user->getKey())->exists()) {
            return false;
        }

        $relation->attach($user->getKey(), ['profile_key' => $profileKey]);
        $this->forgetMemberships($user);

        TenantMemberAdded::dispatch((int) $this->getKey(), (int) $user->getKey());

        return true;
    }

    /**
     * Change the profile of a member. False when the account is no member.
     *
     * @throws InvalidArgumentException for a profile key nobody registered
     */
    public function setMemberProfile(NoerdUser $user, ?string $profileKey): bool
    {
        $this->assertProfileKey($profileKey);

        $relation = $this->users();

        if (! $relation->newPivotStatementForId($user->getKey())->exists()) {
            return false;
        }

        $relation->updateExistingPivot($user->getKey(), ['profile_key' => $profileKey]);
        $this->forgetMemberships($user);

        return true;
    }

    /**
     * End the account's membership in this tenant. The account itself stays —
     * it is an identity of the whole installation, deleted only by a super
     * admin or by the person. False when the account was no member.
     */
    public function removeMember(NoerdUser $user): bool
    {
        if ($this->users()->detach($user->getKey()) === 0) {
            return false;
        }

        $this->forgetMemberships($user);

        TenantMemberRemoved::dispatch((int) $this->getKey(), (int) $user->getKey());

        return true;
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

    /**
     * A new tenant is seeded with its default languages, the default countries
     * and — in database mode — the setup collection definitions, inside its
     * own tenant context: the creator (a console command, a sign-up, another
     * tenant's admin) is not in it, and the defaults' own model hooks query
     * tenant-scoped relations.
     */
    protected static function booted(): void
    {
        static::created(function (Tenant $tenant): void {
            TenantContext::run($tenant->id, function () use ($tenant): void {
                SetupLanguage::ensureDefaultLanguagesForTenant($tenant->id);
                DefaultCountries::ensureForTenant($tenant->id);

                // In database mode a tenant without definition rows has NO usable
                // collections (empty sidebar, no layout for the country entries the
                // line above just created), so a fresh tenant is seeded from the
                // same YAML source the import command migrates from.
                if (SetupCollectionDefinitionImport::isDatabaseMode()) {
                    SetupCollectionDefinitionImport::forTenant($tenant->id);
                }
            });
        });
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

    private function assertProfileKey(?string $profileKey): void
    {
        if ($profileKey !== null && ! app(ProfileRegistry::class)->has($profileKey)) {
            throw new InvalidArgumentException("Unknown profile [{$profileKey}].");
        }
    }

    private function forgetMemberships(NoerdUser $user): void
    {
        $this->unsetRelation('users');
        $user->unsetRelation('tenants');
        $user->unsetRelation('adminTenants');
    }
}
