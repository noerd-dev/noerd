<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['name' => 'My Tenant']);

    $this->admin = NoerdUser::factory()->create(['selected_tenant_id' => $this->tenant->id]);
    $this->admin->tenants()->attach($this->tenant->id, ['profile_key' => Profile::Admin->value]);

    TenantHelper::setSelectedTenantId($this->tenant->id);
    TenantHelper::setSelectedApp('SETUP');
});

it('renders the tenants list page', function (): void {
    $this->actingAs($this->admin);

    $this->get('/setup/tenants')
        ->assertSuccessful()
        ->assertSeeLivewire('noerd::tenants-list');
});

it('lists only tenants the user administers', function (): void {
    $foreignTenant = Tenant::factory()->create(['name' => 'Foreign Tenant']);

    $this->actingAs($this->admin);

    Livewire::test('noerd::tenants-list')
        ->assertSee('My Tenant')
        ->assertDontSee($foreignTenant->name);
});

it('lists all tenants for super admins', function (): void {
    $otherTenant = Tenant::factory()->create(['name' => 'Other Tenant']);
    $superAdmin = NoerdUser::factory()->create([
        'super_admin' => true,
        'selected_tenant_id' => $this->tenant->id,
    ]);
    $superAdmin->tenants()->attach($this->tenant->id, ['profile_key' => Profile::Admin->value]);

    $this->actingAs($superAdmin);

    Livewire::test('noerd::tenants-list')
        ->assertSee('My Tenant')
        ->assertSee('Other Tenant');
});

it('updates the tenant name through the detail', function (): void {
    $this->actingAs($this->admin);

    Livewire::test('noerd::tenant-detail', ['modelId' => (string) $this->tenant->id])
        ->set('detailData.name', 'Renamed Tenant')
        ->call('store')
        ->assertHasNoErrors();

    expect($this->tenant->fresh()->name)->toBe('Renamed Tenant');
});

it('rejects a too short tenant name', function (): void {
    $this->actingAs($this->admin);

    Livewire::test('noerd::tenant-detail', ['modelId' => (string) $this->tenant->id])
        ->set('detailData.name', 'ab')
        ->call('store')
        ->assertHasErrors(['detailData.name']);
});

it('denies the detail for a tenant the user does not administer', function (): void {
    $foreignTenant = Tenant::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test('noerd::tenant-detail', ['modelId' => (string) $foreignTenant->id])
        ->assertForbidden();
});

it('falls back to the selected tenant when no model id is given', function (): void {
    $this->actingAs($this->admin);

    Livewire::test('noerd::tenant-detail')
        ->assertSet('detailData.name', 'My Tenant');
});
