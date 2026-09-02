<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Noerd\Exceptions\NoerdException;
use Noerd\Helpers\TenantHelper;
use Noerd\Middleware\AppAccessMiddleware;
use Noerd\Models\NoerdUser;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->middleware = new AppAccessMiddleware();
});

describe('AppAccessMiddleware', function (): void {
    it('redirects to login when user is not authenticated', function (): void {
        $request = Request::create('/cms/pages', 'GET');

        $response = $this->middleware->handle($request, fn() => response('OK'), 'cms');

        expect($response->getStatusCode())->toBe(302);
        expect($response->headers->get('Location'))->toContain('/noerd/login');
    });

    it('redirects to home when user has no selected tenant', function (): void {
        $user = NoerdUser::factory()->create();
        $this->actingAs($user);

        $request = Request::create('/cms/pages', 'GET');
        $request->setUserResolver(fn() => $user);

        $response = $this->middleware->handle($request, fn() => response('OK'), 'cms');

        expect($response->getStatusCode())->toBe(302);
        expect($response->headers->get('Location'))->toBe(route('noerd.no-tenant'));
    });

    it('throws NoerdException when tenant does not have the app assigned', function (): void {
        $user = NoerdUser::factory()->withExampleTenant()->create();
        $this->actingAs($user);

        $request = Request::create('/cms/pages', 'GET');
        $request->setUserResolver(fn() => $user);

        $this->middleware->handle($request, fn() => response('OK'), 'cms');
    })->throws(NoerdException::class, "App 'CMS' is not assigned to this tenant");

    it('allows access when tenant has the app assigned', function (): void {
        $user = NoerdUser::factory()->withExampleTenant()->create();
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

    it('sets the selected app when access is allowed', function (): void {
        $user = NoerdUser::factory()->withExampleTenant()->create();
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

        expect(TenantHelper::getSelectedApp())->toBe('MEDIA');
    });
});
