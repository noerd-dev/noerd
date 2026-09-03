<?php

declare(strict_types=1);

namespace Noerd\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Noerd\Database\Factories\NoerdUserFactory;
use Noerd\Enums\Profile;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Notifications\NoerdResetPassword;
use Noerd\Services\ProfileRegistry;
use Noerd\Support\Locales;

class NoerdUser extends Authenticatable implements HasLocalePreference
{
    use HasFactory;
    use Notifiable;

    protected $table = 'noerd_users';

    protected $guarded = ['id', 'super_admin', 'api_token'];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    /**
     * @var array{0: int|null}|null Selection assigned before the first save.
     */
    private ?array $pendingSelectedTenantId = null;

    /**
     * The framework notification links to route('password.reset') — a name
     * noerd does not claim. Send noerd's own notification instead, which
     * builds the link from the noerd.password.reset route.
     *
     * @param  string  $token  (untyped to stay compatible with the framework signature)
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new NoerdResetPassword($token));
    }

    public function selectedTenant(): ?Tenant
    {
        return TenantHelper::getSelectedTenant();
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'users_tenants', 'user_id')
            ->withPivot('profile_key');
    }

    public function logins(): HasMany
    {
        return $this->hasMany(NoerdLogin::class, 'user_id');
    }

    public function latestLogin(): HasOne
    {
        return $this->hasOne(NoerdLogin::class, 'user_id')->latestOfMany();
    }

    public function adminTenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'users_tenants', 'user_id')
            ->withPivot('profile_key')
            ->wherePivot('profile_key', Profile::Admin->value);
    }

    public function initials(): string
    {
        if ($this->name) {
            $name = explode(' ', $this->name);
            $initials = mb_substr($name[0], 0, 1);
            if (isset($name[1])) {
                $initials .= mb_substr($name[1], 0, 1);
            } else {
                $initials .= mb_substr($name[0], 1, 1);
            }

            return mb_strtoupper($initials);
        }

        return mb_strtoupper(mb_substr($this->email, 0, 2));
    }

    /**
     * The tenants the user may WORK IN: a super admin administers the whole
     * installation and may enter every tenant, everybody else the tenants of
     * their membership. The single source for the tenant switcher, the login
     * session seed and the per-request membership check — never compare
     * against `$user->tenants` directly for that question. Always read fresh
     * (never the memoized relation): a membership revoked mid-request must be
     * seen by the check that follows it.
     *
     * @return Collection<int, Tenant>
     */
    public function accessibleTenants(): Collection
    {
        return $this->isSuperAdmin()
            ? Tenant::query()->orderBy('id')->get()
            : $this->tenants()->get();
    }

    public function canAccessTenant(int $tenantId): bool
    {
        return $this->isSuperAdmin()
            ? Tenant::query()->whereKey($tenantId)->exists()
            : $this->tenants()->whereKey($tenantId)->exists();
    }

    /**
     * The tenants the user ADMINISTERS (Setup → Users/Tenants scope): every
     * tenant for a super admin, the ADMIN-profile memberships otherwise.
     *
     * @return Collection<int, Tenant>
     */
    public function administeredTenants(): Collection
    {
        return $this->isSuperAdmin()
            ? Tenant::query()->orderBy('id')->get()
            : $this->adminTenants()->get();
    }

    /**
     * The RAW profile key of the user in the given tenant (default: the
     * selected tenant of the current request), read from the users_tenants
     * pivot. Null without a tenant or assignment. Modules interpreting
     * registered profiles read this key; the core's own baseline goes through
     * currentProfile().
     */
    public function currentProfileKey(?int $tenantId = null): ?string
    {
        $tenantId ??= TenantHelper::getSelectedTenantId();

        if (! $tenantId) {
            return null;
        }

        $key = $this->tenants->firstWhere('id', $tenantId)?->pivot?->profile_key;

        return $key === null ? null : (string) $key;
    }

    /**
     * The user's built-in profile in the given tenant. Keys outside the
     * Profile enum (module-registered profiles) resolve to null and behave
     * like the USER default in the core's baseline.
     */
    public function currentProfile(?int $tenantId = null): ?Profile
    {
        $key = $this->currentProfileKey($tenantId);

        return $key === null ? null : Profile::tryFrom($key);
    }

    /**
     * Whether the user administers the tenant of the CURRENT request.
     *
     * Deliberately tenant-scoped (like currentProfile()): the ADMIN profile is
     * assigned per tenant, so counting admin assignments across all tenants
     * made an admin of one tenant an admin of every tenant they are merely a
     * member of — and that is the check behind SetupMiddleware and
     * ComponentAccessGuard. Without a resolved tenant it fails closed; a super
     * admin is unaffected.
     */
    public function isAdmin(?int $tenantId = null): bool
    {
        return $this->isSuperAdmin() || $this->currentProfile($tenantId) === Profile::Admin;
    }

    /**
     * Whether the user administers ANY tenant. Exclusively for contexts that
     * have no tenant request scope — console commands and cross-tenant
     * reporting. NEVER use it for authorization: that is isAdmin(), which is
     * scoped to the tenant of the current request.
     */
    public function isAdminOfAnyTenant(): bool
    {
        // Read fresh (never the memoized relation): an assignment made earlier
        // in the same request must be visible to the check that follows it.
        return $this->isSuperAdmin() || $this->adminTenants()->exists();
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->super_admin;
    }

    public function userSetting(): HasOne
    {
        return $this->hasOne(UserSetting::class, 'user_id', 'id');
    }

    public function preferredLocale(): string
    {
        return $this->locale;
    }

    protected static function booted(): void
    {
        static::saved(function (self $user): void {
            if ($user->pendingSelectedTenantId !== null) {
                [$value] = $user->pendingSelectedTenantId;
                $user->pendingSelectedTenantId = null;
                $user->selected_tenant_id = $value;
            }
        });
    }

    protected static function newFactory(): NoerdUserFactory
    {
        return NoerdUserFactory::new();
    }

    /**
     * The profile badge of the user in the current tenant.
     *
     * @return Attribute<array{badge: string, text: string}, never>
     */
    protected function profileForTenant(): Attribute
    {
        return Attribute::make(get: function (): array {
            $key = $this->currentProfileKey();

            // Registered profiles (ProfileRegistry) get their translated label;
            // an unregistered key falls back to the raw key rather than hiding.
            $label = $key === null ? '' : (app(ProfileRegistry::class)->label($key) ?? $key);

            // The installation-level role is visible wherever the profile is: the
            // badge names it, the tenant profile (if any) follows as plain text.
            if ($this->isSuperAdmin()) {
                return ['badge' => __('Super Admin'), 'text' => $label];
            }

            return ['badge' => $label, 'text' => ''];
        });
    }

    /**
     * The user's settings row, created on first access — the WRITE path. Readers
     * that must not create a row use the userSetting() relation directly.
     *
     * @return Attribute<UserSetting, never>
     */
    protected function setting(): Attribute
    {
        return Attribute::make(get: function (): UserSetting {
            if (! $this->relationLoaded('userSetting') || $this->userSetting === null) {
                $setting = $this->userSetting()->firstOrCreate(
                    ['user_id' => $this->id],
                    ['locale' => 'en'],
                );
                $this->setRelation('userSetting', $setting);
            }

            return $this->userSetting;
        })->withoutObjectCaching();
    }

    /**
     * The selected tenant is NOT a column on noerd_users: the live selection is
     * session state and its persisted counterpart is
     * noerd_user_settings.selected_tenant_id, both owned by TenantHelper. The
     * accessor/mutator pair keeps $user->selected_tenant_id working as the
     * public read/write API without a second, drifting copy of the value.
     */
    /**
     * @return Attribute<int|null, array{}>
     */
    protected function selectedTenantId(): Attribute
    {
        return Attribute::make(
            get: function (): ?int {
                // The live (session) selection belongs to the authenticated user; any
                // other user instance reads its persisted setting.
                if ($this->ownsTenantSession()) {
                    return TenantHelper::getSelectedTenantId();
                }

                if (! $this->relationLoaded('userSetting')) {
                    $this->setRelation('userSetting', $this->userSetting()->first());
                }

                return $this->userSetting?->selected_tenant_id;
            },
            // The value lives in the settings row and the session, never in a
            // column — the empty array writes nothing back onto the model.
            set: function (?int $value): array {
                // The settings row needs the user's id — an unsaved user (factory
                // attributes, mass assignment before save) persists it on `saved`.
                if (! $this->exists) {
                    $this->pendingSelectedTenantId = [$value];

                    return [];
                }

                $this->setting->update(['selected_tenant_id' => $value]);

                if ($this->ownsTenantSession()) {
                    TenantHelper::setSelectedTenantId($value);
                }

                return [];
            },
        );
    }

    /**
     * @return Attribute<string, array{}>
     */
    protected function locale(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                // Read-only: SetUserLocale reads this on EVERY web request — a missing
                // settings row must not trigger the write path ($this->setting would
                // firstOrCreate). Writers keep going through the setting attribute.
                if (! $this->relationLoaded('userSetting')) {
                    $this->setRelation('userSetting', $this->userSetting()->first());
                }

                return $this->userSetting?->locale ?? 'en';
            },
            set: function (string $value): array {
                $this->setting->update(['locale' => $value]);

                return [];
            },
        );
    }

    /**
     * The user's FORMATTING locale (`en-US`): how numbers, dates and amounts
     * are written in the backend UI. Separate from `locale`, which is the
     * interface LANGUAGE. Null (or an unsupported value) means "use the tenant
     * locale" — see FormatHelper::locale().
     */
    /**
     * @return Attribute<string|null, array{}>
     */
    protected function formatLocale(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (! $this->relationLoaded('userSetting')) {
                    $this->setRelation('userSetting', $this->userSetting()->first());
                }

                $formatLocale = $this->userSetting?->format_locale;

                return Locales::isSupported($formatLocale) ? Locales::normalize($formatLocale) : null;
            },
            set: function (?string $value): array {
                $this->setting->update(['format_locale' => $value]);

                return [];
            },
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'super_admin' => 'boolean',
        ];
    }

    /**
     * Whether the tenant session is this user's: true for the authenticated
     * user and while nobody is authenticated (console, a test preparing the
     * user it is about to act as) — never for another user's instance.
     */
    private function ownsTenantSession(): bool
    {
        $authenticated = NoerdAuth::user();

        return $authenticated === null || $this->is($authenticated);
    }
}
