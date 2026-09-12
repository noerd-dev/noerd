<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Noerd\Exceptions\NoerdException;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Middleware\AppAccessMiddleware;
use Noerd\Models\NoerdUser;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

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

describe('hidden apps', function (): void {
    function zzAttachApp(string $name, bool $hidden): TenantApp
    {
        $app = TenantApp::factory()->create(['name' => $name, 'route' => mb_strtolower($name)]);
        TenantHelper::getSelectedTenant()->tenantApps()->attach($app->id, ['is_hidden' => $hidden]);
        TenantHelper::clearCache();

        return $app;
    }

    function zzHandle(AppAccessMiddleware $middleware, NoerdUser $user, string ...$apps): Response
    {
        $request = Request::create('/zz', 'GET');
        $request->setUserResolver(fn() => $user);

        return $middleware->handle($request, fn() => response('OK'), ...$apps);
    }

    beforeEach(function (): void {
        $this->user = NoerdUser::factory()->withExampleTenant()->create();
        $this->actingAs($this->user);
    });

    it('keeps the selected app when the route belongs to a hidden app', function (): void {
        zzAttachApp('ZZSHOP', hidden: false);
        zzAttachApp('ZZCATALOG', hidden: true);
        TenantHelper::setSelectedApp('ZZSHOP');

        $response = zzHandle($this->middleware, $this->user, 'zzcatalog');

        expect($response->getContent())->toBe('OK')
            ->and(TenantHelper::getSelectedApp())->toBe('ZZSHOP');
    });

    it('selects the hidden app when nothing is selected yet', function (): void {
        zzAttachApp('ZZCATALOG', hidden: true);
        TenantHelper::setSelectedApp(null);

        zzHandle($this->middleware, $this->user, 'zzcatalog');

        expect(TenantHelper::getSelectedApp())->toBe('ZZCATALOG');
    });

    it('selects the hidden app when the selected app is not assigned to the tenant', function (): void {
        zzAttachApp('ZZCATALOG', hidden: true);
        TenantHelper::setSelectedApp('ZZSTALE');

        zzHandle($this->middleware, $this->user, 'zzcatalog');

        expect(TenantHelper::getSelectedApp())->toBe('ZZCATALOG');
    });

    it('selects the hidden app when the selected app is denied by the app gate', function (): void {
        zzAttachApp('ZZSHOP', hidden: false);
        zzAttachApp('ZZCATALOG', hidden: true);
        TenantHelper::setSelectedApp('ZZSHOP');
        Gate::define(AccessHelper::APP_GATE, fn($user, string $app): bool => mb_strtolower($app) !== 'zzshop');

        zzHandle($this->middleware, $this->user, 'zzcatalog');

        expect(TenantHelper::getSelectedApp())->toBe('ZZCATALOG');
    });

    it('keeps the selected app when it is one of the route apps, even behind a hidden first candidate', function (): void {
        zzAttachApp('ZZBASE', hidden: true);
        zzAttachApp('ZZPLUS', hidden: false);
        TenantHelper::setSelectedApp('ZZPLUS');

        zzHandle($this->middleware, $this->user, 'zzbase', 'zzplus');

        expect(TenantHelper::getSelectedApp())->toBe('ZZPLUS');
    });

    it('prefers a visible route app over a hidden one when switching apps', function (): void {
        zzAttachApp('ZZBASE', hidden: true);
        zzAttachApp('ZZPLUS', hidden: false);
        TenantHelper::setSelectedApp(null);

        zzHandle($this->middleware, $this->user, 'zzbase', 'zzplus');

        expect(TenantHelper::getSelectedApp())->toBe('ZZPLUS');
    });

    it('still switches to a visible app the route belongs to', function (): void {
        zzAttachApp('ZZSHOP', hidden: false);
        zzAttachApp('ZZOTHER', hidden: false);
        TenantHelper::setSelectedApp('ZZOTHER');

        zzHandle($this->middleware, $this->user, 'zzshop');

        expect(TenantHelper::getSelectedApp())->toBe('ZZSHOP');
    });
});
