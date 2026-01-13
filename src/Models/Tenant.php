<?php

namespace Noerd\Noerd\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Noerd\Noerd\Database\Factories\TenantFactory;

class Tenant extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $appends = [
        'frontendSession',
    ];

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
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

    public function getDomainAttribute(?string $value): string
    {
        return env('APP_ENV') !== 'production'
            ? env('APP_MENU_URL') . '?uuid=' . $this->uuid
            : $value ?? env('APP_MENU_URL') . '?uuid=' . $this->uuid;
    }

    public function getInvoiceInformation(): array
    {
        return [$this->name, $this->email];
    }

    public function getFrontendSessionAttribute(): int
    {
        return (int) Carbon::now()->addMinutes(60)->timestamp;
    }

    public function taxPercentage(): int
    {
        return 19;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_tenants')->withPivot('profile_id');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function tenantApps(): BelongsToMany
    {
        return $this->belongsToMany(TenantApp::class, 'tenant_app')->where('is_active', true);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'tenant_id', 'id');
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
