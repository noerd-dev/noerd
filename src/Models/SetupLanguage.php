<?php

namespace Noerd\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Noerd\Traits\HasListScopes;

class SetupLanguage extends Model
{
    use HasListScopes;

    protected $guarded = [];

    protected array $searchable = [
        'name',
        'code',
    ];

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
     * Ensure default languages exist
     */
    public static function ensureDefaultLanguages(): void
    {
        if (static::count() === 0) {
            static::create([
                'code' => 'en',
                'name' => 'English',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 0,
            ]);
        }
    }

    protected static function boot(): void
    {
        parent::boot();

        // Ensure only one default language exists
        static::saving(function (SetupLanguage $language): void {
            if ($language->is_default) {
                static::where('id', '!=', $language->id ?? 0)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        // After deleting, ensure there's still a default language
        static::deleted(function (SetupLanguage $language): void {
            if ($language->is_default) {
                $newDefault = static::where('is_active', true)->first();
                $newDefault?->update(['is_default' => true]);
            }
        });
    }
}
