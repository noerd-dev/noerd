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
