<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\NoerdUser;
use Noerd\Notifications\NoerdResetPassword;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('core route URL prefix', function (): void {
    it('registers the core routes under the configured prefix', function (string $name, string $path): void {
        expect(route($name, ['componentName' => 'x', 'token' => 'x'], absolute: false))
            ->toStartWith($path);
    })->with([
        ['noerd.login', '/noerd/login'],
        ['noerd.password.request', '/noerd/forgot-password'],
        ['noerd.password.reset', '/noerd/reset-password'],
        ['noerd.profile', '/noerd/user'],
        ['noerd.no-tenant', '/noerd/no-tenant'],
        ['noerd.component-page', '/noerd/component-page'],
    ]);

    it('keeps the setup area and the apps dashboard outside the prefix', function (): void {
        expect(route('noerd.setup', absolute: false))->toBe('/setup')
            ->and(route('noerd.apps', absolute: false))->toBe('/noerd-apps');
    });

    it('redirects the /login alias to the prefixed login route', function (): void {
        $this->get('/login')->assertRedirect('/noerd/login');
    });
});

describe('starter-kit coexistence contract', function (): void {
    // The package must never claim the route names a Laravel starter kit
    // registers — duplicate names break route:cache and route() resolution.
    it('does not register starter-kit route names', function (string $name): void {
        expect(Route::has($name))->toBeFalse();
    })->with(['login', 'dashboard', 'profile', 'password.request', 'password.reset', 'home']);

    // The same applies to generic host names the core used to claim before its
    // routes were namespaced under `noerd.` — they must stay free for hosts.
    it('does not register generic global route names', function (string $name): void {
        expect(Route::has($name))->toBeFalse();
    })->with(['users', 'tenants', 'setup', 'tenant-apps', 'system-settings', 'no-tenant', 'component-page', 'create-tenant']);

    it('does not claim the /dashboard and /profile URIs', function (): void {
        $routes = collect(Route::getRoutes()->get('GET'));

        expect($routes->keys()->all())
            ->not->toContain('dashboard')
            ->not->toContain('profile');
    });
});

describe('auth redirects', function (): void {
    it('sends guests on noerd routes to the noerd login', function (): void {
        $this->get(route('noerd.apps'))->assertRedirect(route('noerd.login'));
    });

    it('sends authenticated users on guest routes to the apps dashboard', function (): void {
        $this->actingAs(NoerdUser::factory()->create(), 'noerd');

        $this->get(route('noerd.login'))->assertRedirect(route('noerd.apps'));
    });
});

describe('generic component page', function (): void {
    it('aborts with 404 when the component name has no module namespace', function (): void {
        $user = NoerdUser::factory()->withExampleTenant()->create();

        $this->actingAs($user)
            ->get(route('noerd.component-page', ['componentName' => 'some-local-component']))
            ->assertNotFound();
    });
});

describe('password reset mail', function (): void {
    it('links to the prefixed noerd reset route', function (): void {
        Notification::fake();

        $user = NoerdUser::factory()->create();

        NoerdAuth::broker()->sendResetLink(['email' => $user->email]);

        Notification::assertSentTo($user, NoerdResetPassword::class, fn(NoerdResetPassword $notification): bool => str_contains($notification->toMail($user)->actionUrl, '/noerd/reset-password/'));
    });
});
