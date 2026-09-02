<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->withExampleTenant()->create());

    $tenantId = TenantHelper::getSelectedTenantId();

    $collection = SetupCollection::firstOrCreate(
        ['tenant_id' => $tenantId, 'collection_key' => 'ZZ_OPTIONS_TEST'],
        ['name' => 'Options Test', 'sort' => 0],
    );

    SetupCollectionEntry::create([
        'tenant_id' => $tenantId,
        'setup_collection_id' => $collection->id,
        'data' => ['name' => ['de' => 'Deutschland', 'en' => 'Germany'], 'code' => 'DE'],
        'sort' => 0,
    ]);
});

it('builds value-field options with translated labels', function (): void {
    session(['selectedLanguage' => 'de']);

    $options = SetupCollectionHelper::selectOptions('ZZ_OPTIONS_TEST', 'name', 'code');

    expect($options)->toHaveCount(1);
    expect($options[0]['value'])->toBe('DE');
    expect($options[0]['label'])->toBe('Deutschland');
});

it('falls back to the entry id without a value field', function (): void {
    $options = SetupCollectionHelper::selectOptions('ZZ_OPTIONS_TEST');

    expect($options[0]['value'])->toBeInt();
});

it('returns an empty set for an unknown collection', function (): void {
    expect(SetupCollectionHelper::selectOptions('ZZ_DOES_NOT_EXIST', 'name', 'code'))->toBe([]);
});
