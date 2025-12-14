<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;
use Noerd\Noerd\Models\User;
use Noerd\Noerd\Models\UserRole;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

$testSettings = [
    'componentName' => 'setup.create-new-tenant',
];

it('renders the create-new-tenant component', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->assertViewIs('volt-livewire::setup.create-new-tenant')
        ->assertSeeText('Neuen Mandanten erstellen');
});

it('validates required name field', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->call('createTenant')
        ->assertHasErrors(['name' => 'required']);
});

it('validates name field minimum length', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->set('name', 'AB') // Only 2 characters, min is 3
        ->call('createTenant')
        ->assertHasErrors(['name' => 'min']);
});

it('validates name field maximum length', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->set('name', str_repeat('A', 51)) // 51 characters, max is 50
        ->call('createTenant')
        ->assertHasErrors(['name' => 'max']);
});

it('accepts valid name with minimum length', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->set('name', 'ABC') // Exactly 3 characters (minimum)
        ->call('createTenant')
        ->assertHasNoErrors();
});

it('successfully creates a new tenant', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenantName = 'Test Tenant';

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->set('name', $tenantName)
        ->call('createTenant')
        ->assertHasNoErrors();

    // Verify tenant was created
    expect(Tenant::where('name', $tenantName)->exists())->toBeTrue();

    $createdTenant = Tenant::where('name', $tenantName)->first();
    expect($createdTenant->name)->toBe($tenantName);
    expect($createdTenant->hash)->not()->toBeNull();
});

it('creates default USER and ADMIN profiles for new tenant', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenantName = 'Test Tenant';

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->set('name', $tenantName)
        ->call('createTenant')
        ->assertHasNoErrors();

    $createdTenant = Tenant::where('name', $tenantName)->first();

    // Verify USER profile was created
    $userProfile = Profile::where('tenant_id', $createdTenant->id)
        ->where('key', 'USER')
        ->first();
    expect($userProfile)->not()->toBeNull();
    expect($userProfile->name)->toBe('Benutzer');

    // Verify ADMIN profile was created
    $adminProfile = Profile::where('tenant_id', $createdTenant->id)
        ->where('key', 'ADMIN')
        ->first();
    expect($adminProfile)->not()->toBeNull();
    expect($adminProfile->name)->toBe('Administrator');
});

it('attaches current user to new tenant as admin', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenantName = 'Test Tenant';

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->set('name', $tenantName)
        ->call('createTenant')
        ->assertHasNoErrors();

    $createdTenant = Tenant::where('name', $tenantName)->first();
    $adminProfile = Profile::where('tenant_id', $createdTenant->id)
        ->where('key', 'ADMIN')
        ->first();

    // Verify user is attached to tenant with admin profile
    expect($createdTenant->users->contains($admin->id))->toBeTrue();
    expect($admin->tenants->contains($createdTenant->id))->toBeTrue();

    // Verify the pivot table has the correct profile_id
    $pivot = $admin->tenants()->wherePivot('tenant_id', $createdTenant->id)->first();
    expect($pivot->pivot->profile_id)->toBe($adminProfile->id);
});

it('copies tenant apps from current tenant to new tenant', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
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

    Volt::test($testSettings['componentName'])
        ->set('name', $tenantName)
        ->call('createTenant')
        ->assertHasNoErrors();

    $createdTenant = Tenant::where('name', $tenantName)->first();

    // Verify apps were copied to new tenant
    expect($createdTenant->tenantApps->contains($app1->id))->toBeTrue();
    expect($createdTenant->tenantApps->contains($app2->id))->toBeTrue();
});

it('copies user roles from current tenant to new tenant', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $currentTenant = $admin->tenants->first();

    // Create some user roles for current tenant
    $role1 = UserRole::factory()->create([
        'key' => 'TEST_ROLE_1',
        'name' => 'Test Role 1',
        'description' => 'First test role',
        'tenant_id' => $currentTenant->id,
    ]);

    $role2 = UserRole::factory()->create([
        'key' => 'TEST_ROLE_2',
        'name' => 'Test Role 2',
        'description' => 'Second test role',
        'tenant_id' => $currentTenant->id,
    ]);

    $tenantName = 'Test Tenant';

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->set('name', $tenantName)
        ->call('createTenant')
        ->assertHasNoErrors();

    $createdTenant = Tenant::where('name', $tenantName)->first();

    // Verify user roles were copied to new tenant
    $copiedRole1 = UserRole::where('tenant_id', $createdTenant->id)
        ->where('key', 'TEST_ROLE_1')
        ->first();
    expect($copiedRole1)->not()->toBeNull();
    expect($copiedRole1->name)->toBe('Test Role 1');
    expect($copiedRole1->description)->toBe('First test role');

    $copiedRole2 = UserRole::where('tenant_id', $createdTenant->id)
        ->where('key', 'TEST_ROLE_2')
        ->first();
    expect($copiedRole2)->not()->toBeNull();
    expect($copiedRole2->name)->toBe('Test Role 2');
    expect($copiedRole2->description)->toBe('Second test role');
});

it('updates user selected_tenant_id to new tenant', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $originalTenantId = $admin->selected_tenant_id;
    $tenantName = 'Test Tenant';

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
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
    $admin = User::factory()->adminUser()->create();
    $currentTenant = $admin->tenants->first();

    // Ensure current tenant has no apps
    $currentTenant->tenantApps()->detach();

    $tenantName = 'Test Tenant';

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->set('name', $tenantName)
        ->call('createTenant')
        ->assertHasNoErrors();

    $createdTenant = Tenant::where('name', $tenantName)->first();

    // Verify new tenant also has no apps
    expect($createdTenant->tenantApps)->toHaveCount(0);
});

it('handles case when current tenant has no user roles', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $currentTenant = $admin->tenants->first();

    // Ensure current tenant has no user roles
    UserRole::where('tenant_id', $currentTenant->id)->delete();

    $tenantName = 'Test Tenant';

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->set('name', $tenantName)
        ->call('createTenant')
        ->assertHasNoErrors();

    $createdTenant = Tenant::where('name', $tenantName)->first();

    // Verify new tenant also has no user roles (except the default ones created by the component)
    $userRoles = UserRole::where('tenant_id', $createdTenant->id)->get();
    expect($userRoles)->toHaveCount(0);
});

it('sets name property correctly in component', function () use ($testSettings): void {
    $admin = User::factory()->adminUser()->create();
    $tenantName = 'Test Tenant';

    $this->actingAs($admin);

    Volt::test($testSettings['componentName'])
        ->set('name', $tenantName)
        ->assertSet('name', $tenantName);
});
