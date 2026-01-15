<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Noerd\Noerd\Exceptions\NoerdException;
use Noerd\Noerd\Middleware\AppAccessMiddleware;
use Noerd\Noerd\Models\TenantApp;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->middleware = new AppAccessMiddleware();
});

describe('AppAccessMiddleware', function (): void {
    it('redirects to login when user is not authenticated', function (): void {
        $request = Request::create('/cms/pages', 'GET');

        $response = $this->middleware->handle($request, fn() => response('OK'), 'cms');

        expect($response->getStatusCode())->toBe(302);
        expect($response->headers->get('Location'))->toContain('/login');
    });

    it('redirects to home when user has no selected tenant', function (): void {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/cms/pages', 'GET');
        $request->setUserResolver(fn() => $user);

        $response = $this->middleware->handle($request, fn() => response('OK'), 'cms');

        expect($response->getStatusCode())->toBe(302);
        expect($response->headers->get('Location'))->not->toContain('/login');
    });

    it('throws NoerdException when tenant does not have the app assigned', function (): void {
        $user = User::factory()->withExampleTenant()->create();
        $this->actingAs($user);

        $request = Request::create('/cms/pages', 'GET');
        $request->setUserResolver(fn() => $user);

        $this->middleware->handle($request, fn() => response('OK'), 'cms');
    })->throws(NoerdException::class, "App 'CMS' is not assigned to this tenant");

    it('allows access when tenant has the app assigned', function (): void {
        $user = User::factory()->withExampleTenant()->create();
        $tenant = $user->selectedTenant();

        $app = TenantApp::create([
            'name' => 'CMS',
            'title' => 'CMS',
            'icon' => 'noerd::icons.cms',
            'route' => 'cms.pages',
            'is_active' => true,
        ]);
        $tenant->tenantApps()->attach($app->id);

        $this->actingAs($user);

        $request = Request::create('/cms/pages', 'GET');
        $request->setUserResolver(fn() => $user);

        $response = $this->middleware->handle($request, fn() => response('OK'), 'cms');

        expect($response->getContent())->toBe('OK');
    });

    it('sets selected_app when access is allowed', function (): void {
        $user = User::factory()->withExampleTenant()->create();
        $tenant = $user->selectedTenant();

        $app = TenantApp::create([
            'name' => 'MEDIA',
            'title' => 'Media',
            'icon' => 'noerd::icons.media',
            'route' => 'media.dashboard',
            'is_active' => true,
        ]);
        $tenant->tenantApps()->attach($app->id);

        $this->actingAs($user);

        $request = Request::create('/media/dashboard', 'GET');
        $request->setUserResolver(fn() => $user);

        $this->middleware->handle($request, fn() => response('OK'), 'media');

        expect($user->fresh()->selected_app)->toBe('MEDIA');
    });

    it('matches app name case-insensitively', function (): void {
        $user = User::factory()->withExampleTenant()->create();
        $tenant = $user->selectedTenant();

        $app = TenantApp::create([
            'name' => 'UKI',
            'title' => 'UKI',
            'icon' => 'noerd::icons.uki',
            'route' => 'uki.dashboard',
            'is_active' => true,
        ]);
        $tenant->tenantApps()->attach($app->id);

        $this->actingAs($user);

        $request = Request::create('/uki/dashboard', 'GET');
        $request->setUserResolver(fn() => $user);

        $response = $this->middleware->handle($request, fn() => response('OK'), 'uki');

        expect($response->getContent())->toBe('OK');
    });
});

describe('NoerdException rendering', function (): void {
    it('renders app not assigned error page', function (): void {
        $exception = new NoerdException(
            NoerdException::TYPE_APP_NOT_ASSIGNED,
            appName: 'CMS',
        );

        $response = $exception->render();

        expect($response->getStatusCode())->toBe(500);
    });

    it('renders config not found error page', function (): void {
        $exception = new NoerdException(
            NoerdException::TYPE_CONFIG_NOT_FOUND,
            configFile: 'details/test.yml',
        );

        $response = $exception->render();

        expect($response->getStatusCode())->toBe(500);
    });
});
