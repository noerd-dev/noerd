<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Models\TenantApp;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    if (! Route::has('app-bar-test')) {
        Route::get('/app-bar-test', fn() => 'app-bar-test')->name('app-bar-test');
    }
});

it('renders the app bar for an authenticated user', function (): void {
    $user = NoerdUser::factory()->adminUser()->create();
    $tenant = $user->adminTenants()->first();

    $tenantApp = TenantApp::create([
        'title' => 'AppBar Test App',
        'name' => 'APP_BAR_TEST',
        'icon' => 'noerd::icons.app',
        'route' => 'app-bar-test',
        'is_active' => true,
    ]);

    $tenant?->tenantApps()->attach($tenantApp->id, ['is_hidden' => false]);
    $this->actingAs($user);

    Livewire::test('noerd::layout.app-bar')
        ->assertSee('AppBar Test App');
});

it('sets selected app and redirects when opening an app', function (): void {
    $user = NoerdUser::factory()->adminUser()->create();
    $tenant = $user->adminTenants()->first();

    $tenantApp = TenantApp::create([
        'title' => 'Redirect App',
        'name' => 'REDIRECT_APP',
        'icon' => 'noerd::icons.app',
        'route' => 'app-bar-test',
        'is_active' => true,
    ]);

    $tenant?->tenantApps()->attach($tenantApp->id, ['is_hidden' => false]);
    $this->actingAs($user);

    Livewire::test('noerd::layout.app-bar')
        ->call('openApp', $tenantApp->name, $tenantApp->route)
        ->assertRedirect(route('app-bar-test'));

    expect(session('noerd.selected_app'))->toBe('REDIRECT_APP');
});
