<?php

namespace Noerd\Noerd\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
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
        'from_email',
        'created_at',
        'updated_at',
        'demo_until',
        'last_invoice',
        'lost',
        'reply_email',
        'module_gastrofix',
        'order_counter',
        'mollie_customer_id',
        'mollie_mandate_id',
        'tax_percentage',
        'trial_ends_at',
        'extra_billing_information',
        'period',
        'supplat_tenant_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function setting(): HasOne
    {
        return $this->hasOne(Setting::class, 'tenant_id', 'id');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(Setting::class, 'tenant_id', 'id');
    }

    public function dueInvoices(): HasMany
    {
        return $this->hasMany(TenantInvoice::class, 'tenant_id', 'id')
            ->where('paid', 0)
            ->whereDate('due_date', '<', today());
    }

    public function getDomainAttribute(?string $value): string
    {
        return env('APP_ENV') !== 'production'
            ? env('APP_MENU_URL') . '?hash=' . $this->getAttribute('hash')
            : $value ?? env('APP_MENU_URL') . '?hash=' . $this->getAttribute('hash');
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

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            if (empty($tenant->api_token)) {
                $tenant->api_token = Str::uuid()->toString();
            }
        });
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
