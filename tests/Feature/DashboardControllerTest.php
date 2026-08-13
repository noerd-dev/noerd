<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Noerd\Helpers\AccessHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    if (!Route::has('dashboard-probe')) {
        Route::get('/dashboard-probe', fn(): string => 'ok')->name('dashboard-probe');
    }
});

it('redirects to the tenant\'s first app when no access gate restricts it', function (): void {
    $user = NoerdUser::factory()->adminUser()->create();
    $tenant = $user->adminTenants()->first();

    $app = TenantApp::create([
        'title' => 'Probe App',
        'name' => 'PROBE',
        'icon' => 'noerd::icons.app',
        'route' => 'dashboard-probe',
        'is_active' => true,
    ]);
    $tenant?->tenantApps()->attach($app->id, ['sort_order' => 0]);
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertRedirect(route('dashboard-probe'));
});

it('skips an app the user is denied and redirects to the next accessible app', function (): void {
    $user = NoerdUser::factory()->adminUser()->create();
    $tenant = $user->adminTenants()->first();

    $deniedApp = TenantApp::create([
        'title' => 'Denied Probe App',
        'name' => 'DENIED_PROBE',
        'icon' => 'noerd::icons.app',
        'route' => 'dashboard-probe',
        'is_active' => true,
    ]);
    $allowedApp = TenantApp::create([
        'title' => 'Allowed Probe App',
        'name' => 'ALLOWED_PROBE',
        'icon' => 'noerd::icons.app',
        'route' => 'noerd-apps',
        'is_active' => true,
    ]);
    $tenant?->tenantApps()->attach($deniedApp->id, ['sort_order' => 0]);
    $tenant?->tenantApps()->attach($allowedApp->id, ['sort_order' => 1]);
    $this->actingAs($user);

    Gate::define(AccessHelper::APP_GATE, fn(?NoerdUser $user, string $appName): bool => $appName !== 'DENIED_PROBE');

    $this->get(route('dashboard'))->assertRedirect(route('noerd-apps'));
});

it('falls back to noerd-apps when no tenant app is accessible', function (): void {
    $user = NoerdUser::factory()->adminUser()->create();
    $tenant = $user->adminTenants()->first();

    $deniedApp = TenantApp::create([
        'title' => 'Denied Probe App',
        'name' => 'DENIED_PROBE',
        'icon' => 'noerd::icons.app',
        'route' => 'dashboard-probe',
        'is_active' => true,
    ]);
    $tenant?->tenantApps()->attach($deniedApp->id, ['sort_order' => 0]);
    $this->actingAs($user);

    Gate::define(AccessHelper::APP_GATE, fn(?NoerdUser $user, string $appName): bool => $appName !== 'DENIED_PROBE');

    $this->get(route('dashboard'))->assertRedirect(route('noerd-apps'));
});
