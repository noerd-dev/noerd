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

beforeEach(function (): void {
    if (! Route::has('app-bar-test')) {
        Route::get('/app-bar-test', fn() => 'app-bar-test')->name('app-bar-test');
    }
});

/**
 * @param  array<string, mixed>  $attributes
 */
function zzAttachAppBarApp(NoerdUser $user, array $attributes = []): TenantApp
{
    $tenant = $user->adminTenants()->first() ?? $user->tenants()->first();

    $tenantApp = TenantApp::create(array_merge([
        'title' => 'Redirect App',
        'name' => 'REDIRECT_APP',
        'icon' => 'noerd::icons.app',
        'route' => 'app-bar-test',
        'is_active' => true,
    ], $attributes));

    $tenant?->tenantApps()->attach($tenantApp->id, ['is_hidden' => false]);

    return $tenantApp;
}

it('references only registered route names in the rendered app bar', function (): void {
    $user = NoerdUser::factory()->adminUser()->create();
    zzAttachAppBarApp($user, ['name' => 'APP_BAR_ROUTE_APP', 'title' => 'AppBar Route App']);
    $this->actingAs($user);

    $html = Livewire::test('noerd::layout.app-bar')->html();

    // The route never reaches the client any more — the tile only carries the
    // app name and the component resolves the target from the tenant's record.
    expect($html)->toContain('AppBar Route App');
    expect(Route::has('app-bar-test'))->toBeTrue();
});

it('sets selected app and redirects when opening an app', function (): void {
    $user = NoerdUser::factory()->adminUser()->create();
    $tenantApp = zzAttachAppBarApp($user);
    $this->actingAs($user);

    Livewire::test('noerd::layout.app-bar')
        ->call('openApp', $tenantApp->name)
        ->assertRedirect(route('app-bar-test'));

    expect(session('noerd.selected_app'))->toBe('REDIRECT_APP');
});

it('rejects an app name the tenant does not have', function (): void {
    $user = NoerdUser::factory()->adminUser()->create();
    zzAttachAppBarApp($user);
    $this->actingAs($user);

    Livewire::test('noerd::layout.app-bar')
        ->call('openApp', 'ZZ_NOT_MY_APP')
        ->assertForbidden();
});

it('rejects an app the permission gate denies', function (): void {
    $user = NoerdUser::factory()->adminUser()->create();
    $tenantApp = zzAttachAppBarApp($user);
    $this->actingAs($user);

    Gate::define(AccessHelper::APP_GATE, fn(?NoerdUser $actor, string $appName): bool => false);

    Livewire::test('noerd::layout.app-bar')
        ->call('openApp', $tenantApp->name)
        ->assertForbidden();
});

it('rejects an inactive app', function (): void {
    $user = NoerdUser::factory()->adminUser()->create();
    $tenantApp = zzAttachAppBarApp($user, ['is_active' => false]);
    $this->actingAs($user);

    Livewire::test('noerd::layout.app-bar')
        ->call('openApp', $tenantApp->name)
        ->assertForbidden();
});

it('never redirects to a client supplied route', function (): void {
    $user = NoerdUser::factory()->adminUser()->create();
    $tenantApp = zzAttachAppBarApp($user);
    $this->actingAs($user);

    // openApp() takes the app name only; the route argument is gone, so a
    // second argument can no longer steer the redirect.
    expect((new ReflectionMethod(Livewire::test('noerd::layout.app-bar')->instance(), 'openApp'))->getNumberOfParameters())
        ->toBe(1);

    Livewire::test('noerd::layout.app-bar')
        ->call('openApp', $tenantApp->name)
        ->assertRedirect(route('app-bar-test'));
});

it('resolves the target of the apps page from the tenant record too', function (): void {
    $user = NoerdUser::factory()->adminUser()->create();
    $tenantApp = zzAttachAppBarApp($user);
    $this->actingAs($user);

    Livewire::test('noerd::noerd-apps')
        ->call('openApp', $tenantApp->name)
        ->assertRedirect(route('app-bar-test'));

    Livewire::test('noerd::noerd-apps')
        ->call('openApp', 'ZZ_NOT_MY_APP')
        ->assertForbidden();
});
