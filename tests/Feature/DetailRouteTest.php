<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Profile;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;
use Noerd\Models\User;
use Noerd\Models\UserRole;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    SetupLanguage::ensureDefaultLanguages();

    $this->tenant = Tenant::factory()->create();
    $adminProfile = Profile::factory()->create([
        'tenant_id' => $this->tenant->id,
        'key' => 'ADMIN',
        'name' => 'Admin',
    ]);

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, ['profile_id' => $adminProfile->id]);

    TenantHelper::setSelectedTenantId($this->tenant->id);
    TenantHelper::setSelectedApp('SETUP');

    $this->actingAs($this->user);
});

it('loads user-detail via direct route', function (): void {
    $this->get('/user/' . $this->user->id)
        ->assertSuccessful()
        ->assertSeeLivewire('user-detail');
});

it('loads user-role-detail via direct route', function (): void {
    $userRole = UserRole::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->get('/user-role/' . $userRole->id)
        ->assertSuccessful()
        ->assertSeeLivewire('user-role-detail');
});

it('loads setup-collection-detail via direct route', function (): void {
    $setupCollection = SetupCollection::factory()->create([
        'tenant_id' => $this->tenant->id,
        'collection_key' => 'test-collection',
    ]);

    $this->get('/setup-collection/' . $setupCollection->id)
        ->assertSuccessful()
        ->assertSeeLivewire('setup-collection-detail');
});

it('loads setup-language-detail via direct route', function (): void {
    $setupLanguage = SetupLanguage::where('code', 'en')->first();

    $this->get('/setup-language/' . $setupLanguage->id)
        ->assertSuccessful()
        ->assertSeeLivewire('setup-language-detail');
});
