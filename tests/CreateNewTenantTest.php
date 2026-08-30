<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

$testSettings = [
    'componentName' => 'noerd::create-new-tenant',
];

it('renders the create-new-tenant component', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->create();

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->assertStatus(200)
        ->assertSeeHtml('wire:submit="createTenant"');
});

it('validates required name field', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->create();

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->call('createTenant')
        ->assertHasErrors(['name' => 'required']);
});

it('validates name field minimum length', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->create();

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->set('name', 'AB') // Only 2 characters, min is 3
        ->call('createTenant')
        ->assertHasErrors(['name' => 'min']);
});

it('validates name field maximum length', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->create();

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->set('name', str_repeat('A', 51)) // 51 characters, max is 50
        ->call('createTenant')
        ->assertHasErrors(['name' => 'max']);
});

it('accepts valid name with minimum length', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->create();

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->set('name', 'ABC') // Exactly 3 characters (minimum)
        ->call('createTenant')
        ->assertHasNoErrors();
});

it('successfully creates a new tenant', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->create();
    $tenantName = 'Test Tenant';

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->set('name', $tenantName)
        ->call('createTenant')
        ->assertHasNoErrors();

    // Verify tenant was created
    expect(Tenant::where('name', $tenantName)->exists())->toBeTrue();

    $createdTenant = Tenant::where('name', $tenantName)->first();
    expect($createdTenant->name)->toBe($tenantName);
    expect($createdTenant->uuid)->not()->toBeNull();
});

it('attaches current user to new tenant as admin', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->create();
    $tenantName = 'Test Tenant';

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->set('name', $tenantName)
        ->call('createTenant')
        ->assertHasNoErrors();

    $createdTenant = Tenant::where('name', $tenantName)->first();

    // Verify user is attached to tenant with admin profile (fresh copy — the
    // acting user's tenants relation was memoized before the attach)
    expect($createdTenant->users->contains($admin->id))->toBeTrue();
    expect($admin->fresh()->tenants->contains($createdTenant->id))->toBeTrue();

    // Verify the pivot table carries the admin profile key
    $pivot = $admin->tenants()->wherePivot('tenant_id', $createdTenant->id)->first();
    expect($pivot->pivot->profile_key)->toBe(Profile::Admin->value);
});

it('copies tenant apps from current tenant to new tenant', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->create();
    $currentTenant = $admin->tenants->first();

    // Create some tenant apps and attach them to current tenant
    $app1 = TenantApp::create([
        'title' => 'App 1',
        'name' => 'APP1',
        'icon' => 'icon-1',
        'route' => 'app1',
        'is_active' => true,
    ]);
    $app2 = TenantApp::create([
        'title' => 'App 2',
        'name' => 'APP2',
        'icon' => 'icon-2',
        'route' => 'app2',
        'is_active' => true,
    ]);
    $currentTenant->tenantApps()->attach([$app1->id, $app2->id]);

    $tenantName = 'Test Tenant';

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->set('name', $tenantName)
        ->call('createTenant')
        ->assertHasNoErrors();

    $createdTenant = Tenant::where('name', $tenantName)->first();

    // Verify apps were copied to new tenant
    expect($createdTenant->tenantApps->contains($app1->id))->toBeTrue();
    expect($createdTenant->tenantApps->contains($app2->id))->toBeTrue();
});

it('updates user selected_tenant_id to new tenant', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->create();
    $originalTenantId = $admin->selected_tenant_id;
    $tenantName = 'Test Tenant';

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->set('name', $tenantName)
        ->call('createTenant')
        ->assertHasNoErrors();

    $createdTenant = Tenant::where('name', $tenantName)->first();

    // Refresh user from database
    $admin->refresh();

    // Verify user's selected_tenant_id was updated
    expect($admin->selected_tenant_id)->toBe($createdTenant->id);
    expect($admin->selected_tenant_id)->not()->toBe($originalTenantId);
});

it('handles case when current tenant has no apps', function () use ($testSettings): void {
    $admin = NoerdUser::factory()->adminUser()->create();
    $currentTenant = $admin->tenants->first();

    // Ensure current tenant has no apps
    $currentTenant->tenantApps()->detach();

    $tenantName = 'Test Tenant';

    $this->actingAs($admin);

    Livewire::test($testSettings['componentName'])
        ->set('name', $tenantName)
        ->call('createTenant')
        ->assertHasNoErrors();

    $createdTenant = Tenant::where('name', $tenantName)->first();

    // Verify new tenant also has no apps
    expect($createdTenant->tenantApps)->toHaveCount(0);
});
