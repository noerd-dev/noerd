<?php

namespace Noerd\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Noerd\Database\Factories\NoerdUserFactory;
use Noerd\Helpers\TenantHelper;
use Noerd\Notifications\NoerdResetPassword;

class NoerdUser extends Authenticatable implements HasLocalePreference
{
    use HasFactory;
    use Notifiable;

    protected $table = 'noerd_users';

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_owner' => 'boolean',
            'super_admin' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * The framework notification links to route('password.reset') — a name
     * noerd does not claim. Send noerd's own notification instead, which
     * builds the link from the noerd.password.reset route.
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
            ->withPivot('profile_id');
    }

    public function adminTenants(): BelongsToMany
    {
        // The admin-profile constraint is a subquery, NOT a query executed while
        // the relation is being DEFINED — defining a relation must never hit the
        // database (it runs on every with()/whereHas() constraint build).
        return $this->belongsToMany(Tenant::class, 'users_tenants', 'user_id')
            ->withPivot('profile_id')
            ->wherePivotIn('profile_id', Profile::query()->select('id')->where('key', 'ADMIN'))
            ->with('profiles');
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
     * @return array{badge: string, text: string}
     */
    public function getProfileForTenantAttribute(): array
    {
        $selectedTenantId = TenantHelper::getSelectedTenantId();

        if (!$selectedTenantId) {
            return ['badge' => '', 'text' => ''];
        }

        // One joined query instead of loading the full tenants collection plus a
        // separate Profile::find() — same tenant-profile semantics as currentProfile().
        $profileName = (string) ($this->profiles()
            ->where('noerd_profiles.tenant_id', $selectedTenantId)
            ->value('name') ?? '');

        return ['badge' => $profileName, 'text' => ''];
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'users_tenants', 'user_id')
            ->withPivot('profile_id');
    }

    public function currentProfile(): ?string
    {
        $selectedTenantId = TenantHelper::getSelectedTenantId();

        return $this->profiles->where('tenant_id', $selectedTenantId)->first()->key ?? null;
    }

    public function isAdmin(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $adminProfilesCount = $this->profiles->where('key', 'ADMIN')->count();

        return (bool) $adminProfilesCount > 0;
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->super_admin;
    }

    public function userSetting(): HasOne
    {
        return $this->hasOne(UserSetting::class, 'user_id', 'id');
    }

    /**
     * Get or create the user's settings.
     */
    public function getSettingAttribute(): UserSetting
    {
        if (!$this->relationLoaded('userSetting') || $this->userSetting === null) {
            $setting = $this->userSetting()->firstOrCreate(
                ['user_id' => $this->id],
                ['locale' => 'en'],
            );
            $this->setRelation('userSetting', $setting);
        }

        return $this->userSetting;
    }

    // Backward compatibility accessors/mutators using session (via TenantSessionHelper)

    public function getSelectedTenantIdAttribute(): ?int
    {
        return TenantHelper::getSelectedTenantId();
    }

    public function setSelectedTenantIdAttribute(?int $value): void
    {
        TenantHelper::setSelectedTenantId($value);
    }

    public function getSelectedAppAttribute(): ?string
    {
        return TenantHelper::getSelectedApp();
    }

    public function setSelectedAppAttribute(?string $value): void
    {
        TenantHelper::setSelectedApp($value);
    }

    public function getLocaleAttribute(): string
    {
        // Read-only: SetUserLocale reads this on EVERY web request — a missing
        // settings row must not trigger the write path ($this->setting would
        // firstOrCreate). Writers keep going through getSettingAttribute().
        if (!$this->relationLoaded('userSetting')) {
            $this->setRelation('userSetting', $this->userSetting()->first());
        }

        return $this->userSetting?->locale ?? 'en';
    }

    public function setLocaleAttribute(string $value): void
    {
        $this->setting->update(['locale' => $value]);
    }

    public function preferredLocale(): string
    {
        return $this->locale;
    }

    protected static function newFactory(): NoerdUserFactory
    {
        return NoerdUserFactory::new();
    }
}
