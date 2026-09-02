<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();

    $this->admin = NoerdUser::factory()->create(['super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['profile_key' => Profile::Admin->value]);

    TenantHelper::setSelectedTenantId($this->tenant->id);
    TenantHelper::setSelectedApp('SETUP');

    $this->appA = TenantApp::create(['title' => 'App A', 'name' => 'APP_A', 'icon' => 'heroicon:outline:squares-2x2', 'route' => 'app-a.index', 'is_active' => true]);
    $this->appB = TenantApp::create(['title' => 'App B', 'name' => 'APP_B', 'icon' => 'heroicon:outline:cube', 'route' => 'app-b.index', 'is_active' => true]);
    $this->appC = TenantApp::create(['title' => 'App C', 'name' => 'APP_C', 'icon' => 'heroicon:outline:cog-6-tooth', 'route' => 'app-c.index', 'is_active' => true]);
});

it('renders the tenant-apps page for admins', function (): void {
    $this->actingAs($this->admin);

    $this->get('/setup/tenant-apps')
        ->assertSuccessful()
        ->assertSeeLivewire('noerd::tenant-apps-list');
});

it('denies access to non-admin users', function (): void {
    // A member of the selected tenant, but without an ADMIN profile. Without a
    // membership the request is redirected to the no-tenant screen before the
    // admin check is ever reached (EnsureTenantMembership drops a tenant the
    // user does not belong to), which would not test authorization at all.
    $tenant = Tenant::factory()->create();
    $nonAdmin = NoerdUser::factory()->create();
    $nonAdmin->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);

    $this->actingAs($nonAdmin);

    $this->get('/setup/tenant-apps')
        ->assertForbidden();
});

it('shows assigned and available apps', function (): void {
    $this->tenant->tenantApps()->attach($this->appA->id, ['sort_order' => 0]);

    $this->actingAs($this->admin);

    Livewire::test('noerd::tenant-apps-list')
        ->assertSee('App A')
        ->assertSee('App B')
        ->assertSee('App C');
});

it('toggleApp detaches an assigned app', function (): void {
    $this->tenant->tenantApps()->attach($this->appA->id, ['sort_order' => 0]);

    $this->actingAs($this->admin);

    Livewire::test('noerd::tenant-apps-list')
        ->call('toggleApp', $this->appA->id);

    expect($this->tenant->tenantApps()->pluck('tenant_apps.id')->toArray())
        ->not->toContain($this->appA->id);
});

it('toggleApp sets correct sort_order when adding', function (): void {
    $this->tenant->tenantApps()->attach($this->appA->id, ['sort_order' => 0]);
    $this->tenant->tenantApps()->attach($this->appB->id, ['sort_order' => 1]);

    $this->actingAs($this->admin);

    Livewire::test('noerd::tenant-apps-list')
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
    Livewire::test('noerd::tenant-apps-list')
        ->call('appSort', $this->appC->id, 0);

    $apps = $this->tenant->tenantApps()->get();

    expect($apps[0]->id)->toBe($this->appC->id);
    expect($apps[0]->pivot->sort_order)->toBe(0);
});

it('toggleApp attaches an unassigned app and moves it between the sections', function (string $actor, bool $multiTenant): void {
    config(['noerd.features.multi_tenant' => $multiTenant]);

    $user = $this->admin;

    if ($actor === 'tenant admin') {
        // isAdmin() is scoped to the SELECTED tenant — a plain tenant admin
        // manages its own tenant's apps without being a super admin.
        $user = NoerdUser::factory()->create();
        $user->tenants()->attach($this->tenant->id, ['profile_key' => Profile::Admin->value]);
    }

    $this->tenant->tenantApps()->attach($this->appA->id, ['sort_order' => 0]);

    $this->actingAs($user);

    $component = Livewire::test('noerd::tenant-apps-list');
    $assignedBefore = count($component->get('assignedApps'));
    $availableBefore = count($component->get('availableApps'));

    $component->call('toggleApp', $this->appB->id)->assertOk();

    expect($this->tenant->tenantApps()->pluck('tenant_apps.id')->toArray())->toContain($this->appB->id)
        ->and($component->get('assignedApps'))->toHaveCount($assignedBefore + 1)
        ->and($component->get('availableApps'))->toHaveCount($availableBefore - 1);
})->with([
    ['super admin', true],
    ['tenant admin', true],
    ['super admin', false],
]);
