<?php

declare(strict_types=1);

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

    /**
     * Session key of the language the setup screens currently edit. Deliberately
     * not namespaced under `noerd.`: frontend modules (e.g. a website language
     * switcher) read and write the same key so backend and frontend stay in sync.
     */
    public const SESSION_KEY = 'selectedLanguage';

    protected $guarded = ['id'];

    /**
     * All active languages of the tenant, default first.
     *
     * @return Collection<int, self>
     */
    public static function active(): Collection
    {
        return static::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * The tenant's default language (`default` is a reserved word).
     */
    public static function defaultLanguage(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /**
     * @return array<int, string>
     */
    public static function activeCodes(): array
    {
        return static::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order')
            ->pluck('code')
            ->toArray();
    }

    public static function defaultCode(): string
    {
        $default = static::defaultLanguage();

        return $default?->code ?? 'en';
    }

    /**
     * The language the setup screens currently edit: the session choice made
     * in the language switcher, otherwise the tenant's default language.
     */
    public static function selectedCode(): string
    {
        return session(self::SESSION_KEY) ?? static::defaultCode();
    }

    /**
     * Ensure default languages exist for a tenant
     */
    public static function ensureDefaultLanguagesForTenant(int $tenantId): void
    {
        if (static::withoutGlobalScopes()->where('tenant_id', $tenantId)->count() === 0) {
            static::create([
                'tenant_id' => $tenantId,
                'code' => 'en',
                'name' => 'English',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 0,
            ]);
            static::create([
                'tenant_id' => $tenantId,
                'code' => 'de',
                'name' => 'Deutsch',
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

    protected static function booted(): void
    {
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
