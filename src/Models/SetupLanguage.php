<?php

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Database\Factories\SetupLanguageFactory;
use Noerd\Traits\BelongsToTenant;

class SetupLanguage extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get all active languages
     */
    public static function getActive(): Collection
    {
        return static::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get the default language
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /**
     * Get active language codes
     */
    public static function getActiveCodes(): array
    {
        return static::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order')
            ->pluck('code')
            ->toArray();
    }

    /**
     * Get default language code
     */
    public static function getDefaultCode(): string
    {
        $default = static::getDefault();

        return $default?->code ?? 'en';
    }

    /**
     * Ensure default languages exist for a tenant
     */
    public static function ensureDefaultLanguagesForTenant(int $tenantId): void
    {
        if (static::withoutGlobalScopes()->where('tenant_id', $tenantId)->count() === 0) {
            static::create([
                'tenant_id' => $tenantId,
                'code' => 'de',
                'name' => 'Deutsch',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 0,
            ]);
            static::create([
                'tenant_id' => $tenantId,
                'code' => 'en',
                'name' => 'English',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 1,
            ]);
        }
    }

    protected static function newFactory(): SetupLanguageFactory
    {
        return SetupLanguageFactory::new();
    }

    protected static function boot(): void
    {
        parent::boot();

        // After saving, ensure only one default per tenant
        static::saved(function (SetupLanguage $language): void {
            if ($language->is_default) {
                static::withoutGlobalScopes()
                    ->where('tenant_id', $language->tenant_id)
                    ->where('id', '!=', $language->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            // If no default exists for this tenant, set one
            $hasDefault = static::withoutGlobalScopes()
                ->where('tenant_id', $language->tenant_id)
                ->where('is_default', true)
                ->exists();

            if (! $hasDefault) {
                $firstActive = static::withoutGlobalScopes()
                    ->where('tenant_id', $language->tenant_id)
                    ->where('is_active', true)
                    ->first();

                $firstActive?->update(['is_default' => true]);
            }
        });

        // After deleting, ensure there's still a default language for the tenant
        static::deleted(function (SetupLanguage $language): void {
            if ($language->is_default) {
                $newDefault = static::withoutGlobalScopes()
                    ->where('tenant_id', $language->tenant_id)
                    ->where('is_active', true)
                    ->first();

                $newDefault?->update(['is_default' => true]);
            }
        });
    }
}
