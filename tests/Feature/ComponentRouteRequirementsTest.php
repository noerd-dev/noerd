<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Contracts\GuardsModalMount;
use Noerd\Enums\Profile;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Support\ComponentAccessGuard;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The modal stack mounts any component the client names (`noerdModal` is a
 | browser event), and route middleware never runs for such a mount. A component
 | that is only reachable through routes with an access middleware therefore
 | keeps that requirement wherever it is mounted.
 */

class ZzRouteGuardedScreen extends Component
{
    public function render(): string
    {
        return '<div>guarded screen</div>';
    }
}

/**
 * A module-owned access middleware: its rule is a switch the tests flip, and it
 * records the parameters the guard hands it.
 */
class ZzModuleAccessMiddleware implements GuardsModalMount
{
    public static bool $allow = false;

    /** @var list<list<string>> */
    public static array $calls = [];

    public static function allowsModalMount(string ...$parameters): bool
    {
        self::$calls[] = $parameters;

        return self::$allow;
    }

    public function handle(Request $request, Closure $next, string ...$parameters): mixed
    {
        abort_unless(self::allowsModalMount(...$parameters), 403);

        return $next($request);
    }
}

beforeEach(function (): void {
    ZzModuleAccessMiddleware::$allow = false;
    ZzModuleAccessMiddleware::$calls = [];
});

function zzRegisterGuardedRoute(string $uri, string $name, array $middleware): void
{
    Livewire::component('zz-route-guarded-screen', ZzRouteGuardedScreen::class);
    Route::livewire($uri, 'zz-route-guarded-screen')->middleware($middleware)->name($name);
    Route::getRoutes()->refreshNameLookups();
}

it('refuses a component of an app the tenant does not run when it is mounted outside its route', function (): void {
    actingAsNoerdUserForApp('ZZGUARDHOME');
    zzRegisterGuardedRoute('zz-guard/screen', 'zz-guard.screen', ['noerd', 'app-access:zzguardother']);

    expect(ComponentAccessGuard::allowsInModal('zz-route-guarded-screen'))->toBeFalse();

    Livewire::test('noerd-modal::noerd-modal')
        ->dispatch('noerdModal', modalComponent: 'zz-route-guarded-screen')
        ->assertForbidden();

    Livewire::test('noerd-modal::noerd-modal')
        ->dispatch('noerdModal', route: 'zz-guard.screen')
        ->assertForbidden();
});

it('leaves a mount the server decided on alone', function (): void {
    actingAsNoerdUserForApp('ZZGUARDHOME');
    zzRegisterGuardedRoute('zz-guard/screen', 'zz-guard.screen', ['noerd', 'app-access:zzguardother']);

    Livewire::test('zz-route-guarded-screen')->assertOk();
});

it('mounts the component for a user of the app', function (): void {
    actingAsNoerdUserForApp('ZZGUARDOTHER');
    zzRegisterGuardedRoute('zz-guard/screen', 'zz-guard.screen', ['noerd', 'app-access:zzguardother']);

    Livewire::test('noerd-modal::noerd-modal')
        ->dispatch('noerdModal', modalComponent: 'zz-route-guarded-screen')
        ->assertOk()
        ->assertSee('guarded screen');
});

it('accepts any one of the apps a shared route names', function (): void {
    actingAsNoerdUserForApp('ZZGUARDSECOND');
    zzRegisterGuardedRoute('zz-guard/screen', 'zz-guard.screen', ['noerd', 'app-access:zzguardother,zzguardsecond']);

    expect(ComponentAccessGuard::allowsInModal('zz-route-guarded-screen'))->toBeTrue();
});

it('is satisfied by one of several routes of the component', function (): void {
    actingAsNoerdUserForApp('ZZGUARDHOME');
    zzRegisterGuardedRoute('zz-guard/screen', 'zz-guard.screen', ['noerd', 'app-access:zzguardother']);
    zzRegisterGuardedRoute('zz-guard/home-screen', 'zz-guard.home-screen', ['noerd', 'app-access:zzguardhome']);

    expect(ComponentAccessGuard::allowsInModal('zz-route-guarded-screen'))->toBeTrue();
});

it('leaves a component open that one route serves without an access middleware', function (): void {
    actingAsNoerdUserForApp('ZZGUARDHOME');
    zzRegisterGuardedRoute('zz-guard/screen', 'zz-guard.screen', ['noerd', 'app-access:zzguardother']);
    zzRegisterGuardedRoute('zz-guard/shared-screen', 'zz-guard.shared-screen', ['noerd']);

    expect(ComponentAccessGuard::allowsInModal('zz-route-guarded-screen'))->toBeTrue();
});

it('keeps the admin requirement of a setup route', function (): void {
    actingAsNoerdUserForApp('ZZGUARDHOME', profile: Profile::User);
    zzRegisterGuardedRoute('zz-guard/setup-screen', 'zz-guard.setup-screen', ['noerd', 'setup']);

    expect(ComponentAccessGuard::allowsInModal('zz-route-guarded-screen'))->toBeFalse();

    actingAsNoerdUserForApp('ZZGUARDHOME', profile: Profile::Admin);
    TenantHelper::clearCache();

    expect(ComponentAccessGuard::allowsInModal('zz-route-guarded-screen'))->toBeTrue();
});

it('keeps the named action of an action-permission route', function (): void {
    actingAsNoerdUserForApp('ZZGUARDHOME', profile: Profile::ReadOnly);
    zzRegisterGuardedRoute('zz-guard/action-screen', 'zz-guard.action-screen', ['noerd', 'action-permission:zz_guard_action']);

    expect(ComponentAccessGuard::allowsInModal('zz-route-guarded-screen'))->toBeFalse();
});

it('does not restrict a component without a route', function (): void {
    actingAsNoerdUserForApp('ZZGUARDHOME');
    Livewire::component('zz-route-guarded-screen', ZzRouteGuardedScreen::class);

    Livewire::test('noerd-modal::noerd-modal')
        ->dispatch('noerdModal', modalComponent: 'zz-route-guarded-screen')
        ->assertOk();
});

it('keeps a module middleware implementing the contract', function (): void {
    actingAsNoerdUserForApp('ZZGUARDHOME');
    zzRegisterGuardedRoute('zz-guard/module-screen', 'zz-guard.module-screen', ['noerd', ZzModuleAccessMiddleware::class . ':zzone,zztwo']);

    expect(ComponentAccessGuard::allowsInModal('zz-route-guarded-screen'))->toBeFalse()
        ->and(ZzModuleAccessMiddleware::$calls)->toBe([['zzone', 'zztwo']]);

    Livewire::test('noerd-modal::noerd-modal')
        ->dispatch('noerdModal', modalComponent: 'zz-route-guarded-screen')
        ->assertForbidden();

    Livewire::test('noerd-modal::noerd-modal')
        ->dispatch('noerdModal', route: 'zz-guard.module-screen')
        ->assertForbidden();

    // An unregistered route falls back to the component, which is guarded all the same.
    Livewire::test('noerd-modal::noerd-modal')
        ->dispatch('noerdModal', route: 'zz-guard.not-registered', modalComponent: 'zz-route-guarded-screen')
        ->assertForbidden();
});

it('mounts the component when the module middleware allows it', function (): void {
    actingAsNoerdUserForApp('ZZGUARDHOME');
    zzRegisterGuardedRoute('zz-guard/module-screen', 'zz-guard.module-screen', ['noerd', ZzModuleAccessMiddleware::class]);
    ZzModuleAccessMiddleware::$allow = true;

    Livewire::test('noerd-modal::noerd-modal')
        ->dispatch('noerdModal', modalComponent: 'zz-route-guarded-screen')
        ->assertOk()
        ->assertSee('guarded screen');

    expect(ZzModuleAccessMiddleware::$calls)->toContain([]);
});

it('resolves a module middleware registered under an alias', function (): void {
    actingAsNoerdUserForApp('ZZGUARDHOME');
    Route::aliasMiddleware('zz-module-access', ZzModuleAccessMiddleware::class);
    zzRegisterGuardedRoute('zz-guard/module-screen', 'zz-guard.module-screen', ['noerd', 'zz-module-access:zzone']);

    expect(ComponentAccessGuard::allowsInModal('zz-route-guarded-screen'))->toBeFalse()
        ->and(ZzModuleAccessMiddleware::$calls)->toBe([['zzone']]);
});

it('expands the middleware groups of a route', function (): void {
    actingAsNoerdUserForApp('ZZGUARDHOME');
    Route::middlewareGroup('zz-guard-inner', ['app-access:zzguardother']);
    Route::middlewareGroup('zz-guard-outer', ['noerd', 'zz-guard-inner']);
    zzRegisterGuardedRoute('zz-guard/grouped-screen', 'zz-guard.grouped-screen', ['zz-guard-outer']);

    expect(ComponentAccessGuard::allowsInModal('zz-route-guarded-screen'))->toBeFalse();

    Route::middlewareGroup('zz-guard-module', ['noerd', ZzModuleAccessMiddleware::class]);
    Livewire::component('zz-route-guarded-other', ZzRouteGuardedScreen::class);
    Route::livewire('zz-guard/module-grouped', 'zz-route-guarded-other')->middleware('zz-guard-module')->name('zz-guard.module-grouped');

    expect(ComponentAccessGuard::allowsInModal('zz-route-guarded-other'))->toBeFalse();

    ZzModuleAccessMiddleware::$allow = true;

    expect(ComponentAccessGuard::allowsInModal('zz-route-guarded-other'))->toBeTrue();
});

it('requires every access middleware of a route', function (): void {
    actingAsNoerdUserForApp('ZZGUARDHOME');
    ZzModuleAccessMiddleware::$allow = true;
    zzRegisterGuardedRoute('zz-guard/module-screen', 'zz-guard.module-screen', ['noerd', 'app-access:zzguardother', ZzModuleAccessMiddleware::class]);

    expect(ComponentAccessGuard::allowsInModal('zz-route-guarded-screen'))->toBeFalse();
});

it('accepts a route whose module middleware allows when another route of the component denies', function (): void {
    actingAsNoerdUserForApp('ZZGUARDHOME');
    zzRegisterGuardedRoute('zz-guard/screen', 'zz-guard.screen', ['noerd', 'app-access:zzguardother']);
    zzRegisterGuardedRoute('zz-guard/module-screen', 'zz-guard.module-screen', ['noerd', ZzModuleAccessMiddleware::class]);
    ZzModuleAccessMiddleware::$allow = true;

    expect(ComponentAccessGuard::allowsInModal('zz-route-guarded-screen'))->toBeTrue();
});

it('mounts the component of an action-permission route for a user who may perform the action', function (): void {
    actingAsNoerdUserForApp('ZZGUARDHOME', profile: Profile::User);
    zzRegisterGuardedRoute('zz-guard/action-screen', 'zz-guard.action-screen', ['noerd', 'action-permission:zz_guard_action']);

    expect(AccessHelper::canPerformAction('zz_guard_action'))->toBeTrue()
        ->and(ComponentAccessGuard::allowsInModal('zz-route-guarded-screen'))->toBeTrue();

    Livewire::test('noerd-modal::noerd-modal')
        ->dispatch('noerdModal', modalComponent: 'zz-route-guarded-screen')
        ->assertOk()
        ->assertSee('guarded screen');
});
