<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Models\Tenant;
use Noerd\Support\DefaultCountries;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function countriesCollectionFor(int $tenantId): ?SetupCollection
{
    return SetupCollection::withoutGlobalScopes()
        ->where('tenant_id', $tenantId)
        ->where('collection_key', 'COUNTRIES')
        ->first();
}

it('seeds the default countries with iso codes for a tenant', function (): void {
    $tenant = Tenant::factory()->create();

    $collection = countriesCollectionFor($tenant->id);
    expect($collection)->not->toBeNull();

    $entries = SetupCollectionEntry::withoutGlobalScopes()
        ->where('setup_collection_id', $collection->id)
        ->get();

    expect($entries)->toHaveCount(count(DefaultCountries::COUNTRIES));

    $germany = $entries->first(fn(SetupCollectionEntry $entry): bool => ($entry->data['code'] ?? null) === 'DE');
    expect($germany)->not->toBeNull();
    expect($germany->data['name']['de'])->toBe('Deutschland');
    expect($germany->data['name']['en'])->toBe('Germany');
});

it('is idempotent and preserves tenant edits', function (): void {
    $tenant = Tenant::factory()->create();

    $collection = countriesCollectionFor($tenant->id);
    $germany = SetupCollectionEntry::withoutGlobalScopes()
        ->where('setup_collection_id', $collection->id)
        ->get()
        ->first(fn(SetupCollectionEntry $entry): bool => ($entry->data['code'] ?? null) === 'DE');

    $germany->update(['data' => array_merge($germany->data, ['name' => ['de' => 'BRD', 'en' => 'Germany']])]);

    DefaultCountries::ensureForTenant($tenant->id);

    $entries = SetupCollectionEntry::withoutGlobalScopes()
        ->where('setup_collection_id', $collection->id)
        ->get();

    expect($entries)->toHaveCount(count(DefaultCountries::COUNTRIES));
    expect($germany->refresh()->data['name']['de'])->toBe('BRD');
});

it('backfills the code onto existing name-only entries instead of duplicating them', function (): void {
    $tenant = Tenant::factory()->create();

    $collection = countriesCollectionFor($tenant->id);

    // Simulate a legacy entry (seeded name-only, e.g. by the crm module).
    SetupCollectionEntry::withoutGlobalScopes()
        ->where('setup_collection_id', $collection->id)
        ->get()
        ->each(fn(SetupCollectionEntry $entry) => $entry->update([
            'data' => ['name' => $entry->data['name']],
        ]));

    DefaultCountries::ensureForTenant($tenant->id);

    $entries = SetupCollectionEntry::withoutGlobalScopes()
        ->where('setup_collection_id', $collection->id)
        ->get();

    expect($entries)->toHaveCount(count(DefaultCountries::COUNTRIES));
    expect($entries->every(fn(SetupCollectionEntry $entry): bool => ($entry->data['code'] ?? '') !== ''))->toBeTrue();
});
