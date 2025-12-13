<?php

namespace Noerd\Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Noerd\Noerd\Database\Factories\UserFactory;
use Nywerk\LegalRegister\Models\Standort;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $guarded = ['id'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'selected_app',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_owner' => 'boolean',
    ];

    public function selectedTenant(): ?Tenant
    {
        $selectedClient = $this->selected_tenant_id;

        $tenant = Tenant::find($selectedClient);
        return $tenant;
    }

    public function selectedClientDemo(): ?bool
    {
        $selectedClientId = $this->selected_tenant_id;

        $freeModules = ['MENU'];

        if ($selectedClientId) {
            $selectedClient = Tenant::find($selectedClientId);

            if (in_array($selectedClient->module, $freeModules)) {
                return false;
            }

            return Tenant::find($selectedClientId)->demo_user;
        }

        return null;
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'users_tenants')
            ->withPivot('profile_id');
    }

    public function adminTenants(): BelongsToMany
    {
        // TODO
        $adminIds = Profile::where('key', 'ADMIN')->pluck('id');
        return $this->belongsToMany(Tenant::class, 'users_tenants')
            ->withPivot('profile_id')
            ->wherePivotIn('profile_id', $adminIds)->with('profiles');
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

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(UserRole::class, 'user_role');
    }

    public function getRolesForTenantAttribute(): array
    {
        $selectedTenantId = Auth::user()?->selected_tenant_id;

        if (!$selectedTenantId) {
            return ['badge' => '', 'text' => ''];
        }

        $profileName = '';
        $tenant = $this->tenants->where('id', $selectedTenantId)->first();
        if ($tenant && $tenant->pivot->profile_id) {
            $profile = Profile::find($tenant->pivot->profile_id);
            $profileName = $profile?->name ?? '';
        }

        $rolesText = $this->roles
            ->where('tenant_id', $selectedTenantId)
            ->pluck('name')
            ->implode(', ');

        return ['badge' => $profileName, 'text' => $rolesText];
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'users_tenants')
            ->withPivot('profile_id');
    }

    public function currentProfile(): ?string
    {
        return $this->profiles->where('tenant_id', $this->selected_tenant_id)->first()->key ?? null;
    }

    public function isAdmin(): bool
    {
        $adminProfilesCount = $this->profiles->where('key', 'ADMIN')->count();

        return (bool) $adminProfilesCount > 0;
    }

    // Belongs to many Sites
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Standort::class, 'standort_user');
    }

    public function toArray()
    {
        $tenants = $this->clients?->pluck('id') ?? [];
        foreach ($tenants as $tenant) {
            $array[$tenant] = true;
        }

        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'selectedTenants' => $array ?? [],
            'tenants' => $this->tenants,
            'roles' => $this->roles,
            'is_owner' => $this->is_owner,
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
