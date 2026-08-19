<?php

declare(strict_types=1);

namespace Noerd\Support;

use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionEntry;

/**
 * Ships the default COUNTRIES setup collection every tenant starts with:
 * translated names plus the ISO 3166-1 alpha-2 code (used as the stored value
 * by `setupCollectionSelect` fields with `valueField: code`, e.g. the customer
 * address country). Idempotent — existing entries are matched by code or name
 * and only backfilled with a missing code, so tenant edits survive.
 */
class DefaultCountries
{
    public const COUNTRIES = [
        ['code' => 'DE', 'de' => 'Deutschland', 'en' => 'Germany'],
        ['code' => 'AT', 'de' => 'Oesterreich', 'en' => 'Austria'],
        ['code' => 'CH', 'de' => 'Schweiz', 'en' => 'Switzerland'],
        ['code' => 'NL', 'de' => 'Niederlande', 'en' => 'Netherlands'],
        ['code' => 'BE', 'de' => 'Belgien', 'en' => 'Belgium'],
        ['code' => 'LU', 'de' => 'Luxemburg', 'en' => 'Luxembourg'],
        ['code' => 'FR', 'de' => 'Frankreich', 'en' => 'France'],
        ['code' => 'IT', 'de' => 'Italien', 'en' => 'Italy'],
        ['code' => 'ES', 'de' => 'Spanien', 'en' => 'Spain'],
        ['code' => 'PL', 'de' => 'Polen', 'en' => 'Poland'],
        ['code' => 'CZ', 'de' => 'Tschechien', 'en' => 'Czech Republic'],
        ['code' => 'GB', 'de' => 'Vereinigtes Koenigreich', 'en' => 'United Kingdom'],
        ['code' => 'US', 'de' => 'Vereinigte Staaten', 'en' => 'United States'],
    ];

    public static function ensureForTenant(int $tenantId): void
    {
        $collection = SetupCollection::withoutGlobalScopes()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'collection_key' => 'COUNTRIES',
            ],
            [
                'name' => 'Countries',
                'sort' => 0,
            ],
        );

        $entries = SetupCollectionEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('setup_collection_id', $collection->id)
            ->get();

        foreach (self::COUNTRIES as $sort => $country) {
            $existing = $entries->first(function (SetupCollectionEntry $entry) use ($country): bool {
                if (($entry->data['code'] ?? null) === $country['code']) {
                    return true;
                }

                $names = (array) ($entry->data['name'] ?? []);

                return in_array($country['en'], $names, true) || in_array($country['de'], $names, true);
            });

            if ($existing) {
                if (($existing->data['code'] ?? null) !== $country['code']) {
                    $existing->update(['data' => array_merge($existing->data ?? [], ['code' => $country['code']])]);
                }

                continue;
            }

            SetupCollectionEntry::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'setup_collection_id' => $collection->id,
                'data' => [
                    'name' => ['de' => $country['de'], 'en' => $country['en']],
                    'code' => $country['code'],
                ],
                'sort' => $sort,
            ]);
        }
    }
}
