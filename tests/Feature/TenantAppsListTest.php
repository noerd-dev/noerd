<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Profile;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Models\User;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $adminProfile = Profile::factory()->create([
        'tenant_id' => $this->tenant->id,
        'key' => 'ADMIN',
        'name' => 'Admin',
    ]);

    $this->admin = User::factory()->create(['super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['profile_id' => $adminProfile->id]);

    TenantHelper::setSelectedTenantId($this->tenant->id);
    TenantHelper::setSelectedApp('SETUP');

    $this->appA = TenantApp::create(['title' => 'App A', 'name' => 'APP_A', 'icon' => 'heroicon:outline:squares-2x2', 'route' => 'app-a.index', 'is_active' => true]);
    $this->appB = TenantApp::create(['title' => 'App B', 'name' => 'APP_B', 'icon' => 'heroicon:outline:cube', 'route' => 'app-b.index', 'is_active' => true]);
    $this->appC = TenantApp::create(['title' => 'App C', 'name' => 'APP_C', 'icon' => 'heroicon:outline:cog-6-tooth', 'route' => 'app-c.index', 'is_active' => true]);
});

it('renders the tenant-apps page for super admins', function (): void {
    $this->actingAs($this->admin);

    $this->get('/tenant-apps')
        ->assertSuccessful()
        ->assertSeeLivewire('setup.tenant-apps-list');
});

it('denies access to regular admins', function (): void {
    $regularAdmin = User::factory()->create();
    $regularAdmin->tenants()->attach($this->tenant->id, [
        'profile_id' => Profile::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'ADMIN',
            'name' => 'Admin2',
        ])->id,
    ]);

    $this->actingAs($regularAdmin);

    Livewire::test('setup.tenant-apps-list')
        ->assertForbidden();
});

it('denies access to non-admin users', function (): void {
    $nonAdmin = User::factory()->create();

    $this->actingAs($nonAdmin);

    $this->get('/tenant-apps')
        ->assertUnauthorized();
});

it('shows assigned and available apps', function (): void {
    $this->tenant->tenantApps()->attach($this->appA->id, ['sort_order' => 0]);

    $this->actingAs($this->admin);

    Livewire::test('setup.tenant-apps-list')
        ->assertSee('App A')
        ->assertSee('App B')
        ->assertSee('App C');
});

it('toggleApp attaches an unassigned app', function (): void {
    $this->actingAs($this->admin);

    Livewire::test('setup.tenant-apps-list')
        ->call('toggleApp', $this->appA->id);

    expect($this->tenant->tenantApps()->pluck('tenant_apps.id')->toArray())
        ->toContain($this->appA->id);
});

it('toggleApp detaches an assigned app', function (): void {
    $this->tenant->tenantApps()->attach($this->appA->id, ['sort_order' => 0]);

    $this->actingAs($this->admin);

    Livewire::test('setup.tenant-apps-list')
        ->call('toggleApp', $this->appA->id);

    expect($this->tenant->tenantApps()->pluck('tenant_apps.id')->toArray())
        ->not->toContain($this->appA->id);
});

it('toggleApp sets correct sort_order when adding', function (): void {
    $this->tenant->tenantApps()->attach($this->appA->id, ['sort_order' => 0]);
    $this->tenant->tenantApps()->attach($this->appB->id, ['sort_order' => 1]);

    $this->actingAs($this->admin);

    Livewire::test('setup.tenant-apps-list')
        ->call('toggleApp', $this->appC->id);

    $pivot = $this->tenant->tenantApps()->where('tenant_apps.id', $this->appC->id)->first()->pivot;
    expect($pivot->sort_order)->toBe(2);
});

it('appSort updates sort_order correctly', function (): void {
    $this->tenant->tenantApps()->attach($this->appA->id, ['sort_order' => 0]);
    $this->tenant->tenantApps()->attach($this->appB->id, ['sort_order' => 1]);
    $this->tenant->tenantApps()->attach($this->appC->id, ['sort_order' => 2]);

    $this->actingAs($this->admin);

    // Move App C from position 2 to position 0
    Livewire::test('setup.tenant-apps-list')
        ->call('appSort', $this->appC->id, 0);

    $apps = $this->tenant->tenantApps()->get();

    expect($apps[0]->id)->toBe($this->appC->id);
    expect($apps[0]->pivot->sort_order)->toBe(0);
});

it('moves assigned apps between sections on toggle', function (): void {
    $this->tenant->tenantApps()->attach($this->appA->id, ['sort_order' => 0]);

    $this->actingAs($this->admin);

    $component = Livewire::test('setup.tenant-apps-list');

    $assignedBefore = count($component->get('assignedApps'));
    $availableBefore = count($component->get('availableApps'));

    expect($assignedBefore)->toBe(1);

    // Add App B
    $component->call('toggleApp', $this->appB->id);

    expect($component->get('assignedApps'))->toHaveCount($assignedBefore + 1);
    expect($component->get('availableApps'))->toHaveCount($availableBefore - 1);
});
