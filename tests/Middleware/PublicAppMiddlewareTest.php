<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Noerd\Exceptions\NoerdException;
use Noerd\Helpers\TenantHelper;
use Noerd\Middleware\PublicAppMiddleware;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupCollection;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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
        expect($response->headers->get('Location'))->toContain('/noerd/login');
    });

    it('allows authenticated access to public apps', function (): void {
        $user = NoerdUser::factory()->withExampleTenant()->create();

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
        expect($response->headers->get('Location'))->toContain('/noerd/login');
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
        $user = NoerdUser::factory()->withExampleTenant()->create();
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

    it('establishes the tenant context for a guest on a single-tenant public app', function (): void {
        $tenant = Tenant::factory()->create();
        $app = TenantApp::create([
            'name' => 'DOCUMENTATION',
            'title' => 'Documentation',
            'icon' => 'noerd::icons.docs',
            'route' => 'docs',
            'is_active' => true,
            'is_public' => true,
        ]);
        $tenant->tenantApps()->attach($app->id);

        $this->middleware->handle(Request::create('/docs', 'GET'), fn() => response('OK'), 'documentation');

        expect(TenantHelper::getGuestTenantId())->toBe($tenant->id)
            ->and(TenantHelper::currentTenantId())->toBe($tenant->id);

        // Tenant-owned data is now scoped to that tenant instead of spanning all.
        $mine = SetupCollection::factory()->create(['tenant_id' => $tenant->id]);
        $other = SetupCollection::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $ids = SetupCollection::query()->pluck('id');
        expect($ids)->toContain($mine->id)->not->toContain($other->id);
    });

    it('yields no tenant rows for a guest when the public app spans several tenants', function (): void {
        $app = TenantApp::create([
            'name' => 'DOCUMENTATION',
            'title' => 'Documentation',
            'icon' => 'noerd::icons.docs',
            'route' => 'docs',
            'is_active' => true,
            'is_public' => true,
        ]);
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $tenantA->tenantApps()->attach($app->id);
        $tenantB->tenantApps()->attach($app->id);
        SetupCollection::factory()->create(['tenant_id' => $tenantA->id]);

        $this->middleware->handle(Request::create('/docs', 'GET'), fn() => response('OK'), 'documentation');

        // Ambiguous assignment: the app must establish the context itself. Until
        // then the guest sees nothing rather than another tenant's data.
        expect(TenantHelper::getGuestTenantId())->toBeNull()
            ->and(SetupCollection::query()->count())->toBe(0);
    });

    it('throws exception for authenticated user without app access on private apps', function (): void {
        $user = NoerdUser::factory()->withExampleTenant()->create();

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
