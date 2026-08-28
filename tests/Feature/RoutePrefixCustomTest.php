<?php

declare(strict_types=1);

use Noerd\Tests\TestCase;

/**
 * Boots the application with a NON-default route prefix to prove the URLs
 * are driven by config('noerd.routes.prefix') — not by a hardcoded 'noerd'.
 */
class RoutePrefixCustomTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('noerd.routes.prefix', 'backend');
    }
}

uses(RoutePrefixCustomTestCase::class);

it('applies a custom prefix from the config to the core routes', function (): void {
    expect(route('noerd.login', absolute: false))->toBe('/backend/login')
        ->and(route('noerd.profile', absolute: false))->toBe('/backend/user');
});

it('points the /login alias at the custom prefix', function (): void {
    $this->get('/login')->assertRedirect('/backend/login');
});

it('keeps the setup area and the apps dashboard outside the custom prefix', function (): void {
    expect(route('noerd.setup', absolute: false))->toBe('/setup')
        ->and(route('noerd.apps', absolute: false))->toBe('/noerd-apps');
});
