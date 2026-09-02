<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    if (! Route::has('app-bar-test')) {
        Route::get('/app-bar-test', fn() => 'app-bar-test')->name('app-bar-test');
    }
});

it('references only registered route names in the rendered app bar', function (): void {
    $user = NoerdUser::factory()->adminUser()->create();
    $tenant = $user->adminTenants()->first();

    $tenantApp = TenantApp::create([
        'title' => 'AppBar Route App',
        'name' => 'APP_BAR_ROUTE_APP',
        'icon' => 'noerd::icons.app',
        'route' => 'app-bar-test',
        'is_active' => true,
    ]);

    $tenant?->tenantApps()->attach($tenantApp->id, ['is_hidden' => false]);
    $this->actingAs($user);

    $html = Livewire::test('noerd::layout.app-bar')->html();

    preg_match_all("/openApp\\('[^']+', '([^']+)'\\)/", $html, $matches);

    expect($matches[1])->not->toBeEmpty();
    foreach ($matches[1] as $routeName) {
        expect(Route::has($routeName))->toBeTrue("Route [{$routeName}] referenced in the app bar is not defined.");
    }
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
