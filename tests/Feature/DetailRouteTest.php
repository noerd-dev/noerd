<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Enums\Profile;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    SetupLanguage::ensureDefaultLanguagesForTenant($this->tenant->id);

    $this->user = NoerdUser::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, ['profile_key' => Profile::Admin->value]);

    TenantHelper::setSelectedTenantId($this->tenant->id);
    TenantHelper::setSelectedApp('SETUP');

    $this->actingAs($this->user);
});

it('loads noerd-user-detail via direct route', function (): void {
    $this->get('/setup/noerd-user/' . $this->user->id)
        ->assertSuccessful()
        ->assertSeeLivewire('noerd::noerd-user-detail');
});

it('loads setup-collection-detail via direct route', function (): void {
    $setupCollection = SetupCollection::factory()->create([
        'tenant_id' => $this->tenant->id,
        'collection_key' => 'test-collection',
    ]);

    $this->get('/setup/collection/' . $setupCollection->id)
        ->assertSuccessful()
        ->assertSeeLivewire('noerd::setup-collection-detail');
});

it('loads setup-language-detail via direct route', function (): void {
    $setupLanguage = SetupLanguage::where('code', 'en')->first();

    $this->get('/setup/language/' . $setupLanguage->id)
        ->assertSuccessful()
        ->assertSeeLivewire('noerd::setup-language-detail');
});
