<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);
uses()->group('quick-menu', 'policies');

it('ensures canCms gate is registered', function (): void {
    expect(Gate::has('canCms'))->toBeTrue();
});

it('allows admin users CMS access when tenant has CMS app', function (): void {
    $tenant = Tenant::factory()->create();
    $adminProfile = Profile::create([
        'key' => 'ADMIN',
        'name' => 'Administrator',
        'tenant_id' => $tenant->id,
    ]);

    $cmsApp = TenantApp::create([
        'name' => 'CMS',
        'title' => 'CMS',
        'is_active' => true,
        'icon' => 'cms',
        'route' => 'cms.index',
    ]);

    $tenant->tenantApps()->attach($cmsApp->id);

    $adminUser = User::factory()->create();
    $adminUser->tenants()->attach($tenant->id, ['profile_id' => $adminProfile->id]);
    $adminUser->selected_tenant_id = $tenant->id;

    // Now admin should have CMS access
    expect($adminUser->can('canCms'))->toBeTrue('Admin with CMS app should have CMS access');
});

it('ensures users with CMS app can access CMS functionality', function (): void {
    $tenant = Tenant::factory()->create();
    $userProfile = Profile::create([
        'key' => 'USER',
        'name' => 'User',
        'tenant_id' => $tenant->id,
    ]);

    $cmsApp = TenantApp::create([
        'name' => 'CMS',
        'title' => 'CMS',
        'is_active' => true,
        'icon' => 'cms',
        'route' => 'cms.index',
    ]);

    $tenant->tenantApps()->attach($cmsApp->id);

    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, ['profile_id' => $userProfile->id]);
    $user->selected_tenant_id = $tenant->id;

    expect($user->can('canCms'))->toBeTrue('User with CMS app should have CMS access');
});

it('ensures users with order apps can access orders functionality', function (): void {
    $tenant = Tenant::factory()->create();
    $userProfile = Profile::create([
        'key' => 'USER',
        'name' => 'User',
        'tenant_id' => $tenant->id,
    ]);

    $deliveryApp = TenantApp::create([
        'name' => 'DELIVERY',
        'title' => 'Delivery',
        'is_active' => true,
        'icon' => 'delivery',
        'route' => 'delivery.index',
    ]);

    $tenant->tenantApps()->attach($deliveryApp->id);

    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, ['profile_id' => $userProfile->id]);
    $user->selected_tenant_id = $tenant->id;

    expect($user->can('canOrders'))->toBeTrue('User with delivery app should have orders access');
});

it('denies access to users without appropriate tenant apps', function (): void {
    $tenant = Tenant::factory()->create(); // No apps attached
    $userProfile = Profile::create([
        'key' => 'USER',
        'name' => 'User',
        'tenant_id' => $tenant->id,
    ]);

    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, ['profile_id' => $userProfile->id]);
    $user->selected_tenant_id = $tenant->id;

    expect($user->can('canOrders'))->toBeFalse('User without order apps should NOT have orders access');
    expect($user->can('canCms'))->toBeFalse('User without CMS app should NOT have CMS access');
});

it('handles edge cases gracefully', function (): void {
    $user = User::factory()->create();

    // Null tenant
    $user->selected_tenant_id = null;
    expect($user->can('canOrders'))->toBeFalse();
    expect($user->can('canCms'))->toBeFalse();

    // Non-existent tenant
    $user->selected_tenant_id = 999999;
    expect($user->can('canOrders'))->toBeFalse();
    expect($user->can('canCms'))->toBeFalse();
});

it('quick menu component shows CMS button only when tenant has CMS app', function (): void {
    $tenant = Tenant::factory()->create();

    $adminProfile = Profile::create([
        'key' => 'ADMIN',
        'name' => 'Administrator',
        'tenant_id' => $tenant->id,
    ]);

    $cmsApp = TenantApp::create([
        'name' => 'CMS',
        'title' => 'CMS',
        'is_active' => true,
        'icon' => 'cms',
        'route' => 'cms.index',
    ]);

    $tenant->tenantApps()->attach($cmsApp->id);

    $adminUser = User::factory()->create();
    $adminUser->tenants()->attach($tenant->id, ['profile_id' => $adminProfile->id]);
    $adminUser->selected_tenant_id = $tenant->id;

    $component = Livewire::actingAs($adminUser)
        ->test('layout.quick-menu');

    // Admin should see website button only when tenant has CMS app
    $component->assertSee('Zur Webseite');
});

it('quick menu component hides buttons when user lacks permissions', function (): void {
    $tenant = Tenant::factory()->create(); // No apps
    $userProfile = Profile::create([
        'key' => 'USER',
        'name' => 'User',
        'tenant_id' => $tenant->id,
    ]);

    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, ['profile_id' => $userProfile->id]);
    $user->selected_tenant_id = $tenant->id;

    $component = Livewire::actingAs($user)
        ->test('layout.quick-menu');

    $component->assertDontSee('Open Orders')
        ->assertDontSee('To Shop')
        ->assertDontSee('Zur Webseite');
});

it('generates correct CMS frontend URL when CMS app is available', function (): void {
    $tenant = Tenant::factory()->create([
        'hash' => 'test-tenant-hash',
    ]);

    $adminProfile = Profile::create([
        'key' => 'ADMIN',
        'name' => 'Administrator',
        'tenant_id' => $tenant->id,
    ]);

    $cmsApp = TenantApp::create([
        'name' => 'CMS',
        'title' => 'CMS',
        'is_active' => true,
        'icon' => 'cms',
        'route' => 'cms.index',
    ]);

    $tenant->tenantApps()->attach($cmsApp->id);

    $adminUser = User::factory()->create();
    $adminUser->tenants()->attach($tenant->id, ['profile_id' => $adminProfile->id]);
    $adminUser->selected_tenant_id = $tenant->id;

    $component = Livewire::actingAs($adminUser)
        ->test('layout.quick-menu');

    $expectedUrl = url('/index?hash=test-tenant-hash');
    $component->assertSet('websiteUrl', $expectedUrl);
});

it('does not generate website URL when tenant has empty hash', function (): void {
    $tenant = Tenant::factory()->create([
        'hash' => '', // Empty hash
    ]);


    $adminProfile = Profile::create([
        'key' => 'ADMIN',
        'name' => 'Administrator',
        'tenant_id' => $tenant->id,
    ]);

    $cmsApp = TenantApp::create([
        'name' => 'CMS',
        'title' => 'CMS',
        'is_active' => true,
        'icon' => 'cms',
        'route' => 'cms.index',
    ]);

    $tenant->tenantApps()->attach($cmsApp->id);

    $adminUser = User::factory()->create();
    $adminUser->tenants()->attach($tenant->id, ['profile_id' => $adminProfile->id]);
    $adminUser->selected_tenant_id = $tenant->id;

    $component = Livewire::actingAs($adminUser)
        ->test('layout.quick-menu');

    $component->assertSet('websiteUrl', null);
});

it('validates all order app types provide correct access', function (): void {
    $orderAppNames = ['DELIVERY', 'RESTAURANT', 'STORE', 'CANTEEN'];

    foreach ($orderAppNames as $appName) {
        $tenant = Tenant::factory()->create();
        $userProfile = Profile::create([
            'key' => 'USER',
            'name' => 'User',
            'tenant_id' => $tenant->id,
        ]);

        $app = TenantApp::create([
            'name' => $appName,
            'title' => $appName,
            'is_active' => true,
            'icon' => mb_strtolower($appName),
            'route' => mb_strtolower($appName) . '.index',
        ]);

        $tenant->tenantApps()->attach($app->id);

        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['profile_id' => $userProfile->id]);
        $user->selected_tenant_id = $tenant->id;

        expect($user->can('canOrders'))
            ->toBeTrue("User with {$appName} app should have orders access");
    }
});
