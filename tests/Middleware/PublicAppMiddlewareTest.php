<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Noerd\Exceptions\NoerdException;
use Noerd\Middleware\PublicAppMiddleware;
use Noerd\Models\TenantApp;
use Noerd\Models\User;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->middleware = new PublicAppMiddleware();
});

describe('PublicAppMiddleware', function (): void {
    it('allows unauthenticated access to public apps', function (): void {
        TenantApp::create([
            'name' => 'DOCUMENTATION',
            'title' => 'Documentation',
            'icon' => 'noerd::icons.docs',
            'route' => 'docs',
            'is_active' => true,
            'is_public' => true,
        ]);

        $request = Request::create('/docs/installation', 'GET');

        $response = $this->middleware->handle($request, fn() => response('OK'), 'documentation');

        expect($response->getContent())->toBe('OK');
    });

    it('redirects unauthenticated users to login for private apps', function (): void {
        TenantApp::create([
            'name' => 'CMS',
            'title' => 'CMS',
            'icon' => 'noerd::icons.cms',
            'route' => 'cms.pages',
            'is_active' => true,
            'is_public' => false,
        ]);

        $request = Request::create('/cms/pages', 'GET');

        $response = $this->middleware->handle($request, fn() => response('OK'), 'cms');

        expect($response->getStatusCode())->toBe(302);
        expect($response->headers->get('Location'))->toContain('/login');
    });

    it('allows authenticated access to public apps', function (): void {
        $user = User::factory()->withExampleTenant()->create();

        TenantApp::create([
            'name' => 'DOCUMENTATION',
            'title' => 'Documentation',
            'icon' => 'noerd::icons.docs',
            'route' => 'docs',
            'is_active' => true,
            'is_public' => true,
        ]);

        $this->actingAs($user);

        $request = Request::create('/docs/installation', 'GET');
        $request->setUserResolver(fn() => $user);

        $response = $this->middleware->handle($request, fn() => response('OK'), 'documentation');

        expect($response->getContent())->toBe('OK');
    });

    it('treats inactive public apps as private', function (): void {
        TenantApp::create([
            'name' => 'DOCUMENTATION',
            'title' => 'Documentation',
            'icon' => 'noerd::icons.docs',
            'route' => 'docs',
            'is_active' => false,
            'is_public' => true,
        ]);

        $request = Request::create('/docs/installation', 'GET');

        $response = $this->middleware->handle($request, fn() => response('OK'), 'documentation');

        expect($response->getStatusCode())->toBe(302);
        expect($response->headers->get('Location'))->toContain('/login');
    });

    it('matches app name case-insensitively for public apps', function (): void {
        TenantApp::create([
            'name' => 'DOCUMENTATION',
            'title' => 'Documentation',
            'icon' => 'noerd::icons.docs',
            'route' => 'docs',
            'is_active' => true,
            'is_public' => true,
        ]);

        $request = Request::create('/docs/installation', 'GET');

        $response = $this->middleware->handle($request, fn() => response('OK'), 'Documentation');

        expect($response->getContent())->toBe('OK');
    });

    it('falls back to tenant-based access for non-public apps when authenticated', function (): void {
        $user = User::factory()->withExampleTenant()->create();
        $tenant = $user->selectedTenant();

        $app = TenantApp::create([
            'name' => 'CMS',
            'title' => 'CMS',
            'icon' => 'noerd::icons.cms',
            'route' => 'cms.pages',
            'is_active' => true,
            'is_public' => false,
        ]);
        $tenant->tenantApps()->attach($app->id);

        $this->actingAs($user);

        $request = Request::create('/cms/pages', 'GET');
        $request->setUserResolver(fn() => $user);

        $response = $this->middleware->handle($request, fn() => response('OK'), 'cms');

        expect($response->getContent())->toBe('OK');
    });

    it('throws exception for authenticated user without app access on private apps', function (): void {
        $user = User::factory()->withExampleTenant()->create();

        TenantApp::create([
            'name' => 'CMS',
            'title' => 'CMS',
            'icon' => 'noerd::icons.cms',
            'route' => 'cms.pages',
            'is_active' => true,
            'is_public' => false,
        ]);

        $this->actingAs($user);

        $request = Request::create('/cms/pages', 'GET');
        $request->setUserResolver(fn() => $user);

        $this->middleware->handle($request, fn() => response('OK'), 'cms');
    })->throws(NoerdException::class, "App 'CMS' is not assigned to this tenant");
});
