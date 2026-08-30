<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Noerd\Helpers\AccessHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows every app when no access gate is defined', function (): void {
    expect(Gate::has(AccessHelper::APP_GATE))->toBeFalse()
        ->and(AccessHelper::canAccessApp('ANYTHING'))->toBeTrue()
        ->and(AccessHelper::canAccessApp(null))->toBeTrue()
        ->and(AccessHelper::canAccessApp(''))->toBeTrue();
});

it('hides an app from the app bar when the access gate denies it', function (): void {
    if (!Route::has('app-permission-test')) {
        Route::get('/app-permission-test', fn(): string => 'ok')->name('app-permission-test');
    }

    $user = NoerdUser::factory()->adminUser()->create();
    $tenant = $user->adminTenants()->first();

    $allowedApp = TenantApp::create([
        'title' => 'Allowed Probe App',
        'name' => 'ALLOWED_PROBE',
        'icon' => 'noerd::icons.app',
        'route' => 'app-permission-test',
        'is_active' => true,
    ]);
    $deniedApp = TenantApp::create([
        'title' => 'Denied Probe App',
        'name' => 'DENIED_PROBE',
        'icon' => 'noerd::icons.app',
        'route' => 'app-permission-test',
        'is_active' => true,
    ]);
    $tenant?->tenantApps()->attach($allowedApp->id, ['is_hidden' => false]);
    $tenant?->tenantApps()->attach($deniedApp->id, ['is_hidden' => false]);
    $this->actingAs($user);

    // Synthetic gate, standing in for a project-defined restriction: the app
    // bar must consult the gate, not the tenant assignment alone.
    Gate::define(AccessHelper::APP_GATE, fn(?NoerdUser $user, string $appName): bool => $appName !== 'DENIED_PROBE');

    Livewire::test('noerd::layout.app-bar')
        ->assertSee('Allowed Probe App')
        ->assertDontSee('Denied Probe App');
});

it('canUseApp requires tenant assignment AND app permission', function (): void {
    // No tenant selected: nothing is assigned.
    expect(AccessHelper::canUseApp('ANYTHING'))->toBeFalse();

    $user = NoerdUser::factory()->withExampleTenant()->create();
    $this->actingAs($user);
    $tenant = \Noerd\Helpers\TenantHelper::getSelectedTenant();

    $assigned = TenantApp::create([
        'title' => 'Assigned Probe App',
        'name' => 'ZZ_ASSIGNED_PROBE',
        'icon' => 'noerd::icons.app',
        'route' => 'zz-assigned-probe',
        'is_active' => true,
    ]);
    $tenant->tenantApps()->attach($assigned->id, ['is_hidden' => false]);
    \Noerd\Helpers\TenantHelper::clearCache();

    // Assigned + no denying gate: usable (case-insensitive).
    expect(AccessHelper::canUseApp('zz_assigned_probe'))->toBeTrue()
        // Not assigned to the tenant: unusable even though the gate would allow.
        ->and(AccessHelper::canUseApp('ZZ_UNASSIGNED_PROBE'))->toBeFalse()
        // One usable app out of several suffices.
        ->and(AccessHelper::canUseApp('ZZ_UNASSIGNED_PROBE', 'ZZ_ASSIGNED_PROBE'))->toBeTrue();

    // Assigned but denied by the app permission: unusable.
    Gate::define(AccessHelper::APP_GATE, fn(?NoerdUser $u, string $appName): bool => false);
    expect(AccessHelper::canUseApp('ZZ_ASSIGNED_PROBE'))->toBeFalse();
});
