<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\TenantApp;
use Noerd\Models\User;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $user = User::factory()->withExampleTenant()->create();
    $this->actingAs($user);
});

function setRequestRoute(string $routeName): void
{
    $route = new Route('GET', '/test', fn () => 'ok');
    $route->name($routeName);

    $request = Request::create('/test');
    $request->setRouteResolver(fn () => $route);

    app()->instance('request', $request);
}

it('sets app when route matches a tenant app', function (): void {
    TenantApp::create([
        'name' => 'CONTENT',
        'title' => 'Content',
        'icon' => 'content::icons.app',
        'route' => 'exampleApp.content',
        'is_active' => true,
    ]);

    setRequestRoute('exampleApp.content');

    TenantHelper::setSelectedAppFromRoute();

    expect(TenantHelper::getSelectedApp())->toBe('CONTENT');
});

it('does nothing when route does not exist in tenant_apps', function (): void {
    TenantHelper::setSelectedApp('EXISTING');

    setRequestRoute('some.unknown.route');

    TenantHelper::setSelectedAppFromRoute();

    expect(TenantHelper::getSelectedApp())->toBe('EXISTING');
});

it('does nothing without a current route', function (): void {
    TenantHelper::setSelectedApp('EXISTING');

    TenantHelper::setSelectedAppFromRoute();

    expect(TenantHelper::getSelectedApp())->toBe('EXISTING');
});

it('switches app when a different app is already selected', function (): void {
    TenantHelper::setSelectedApp('OLD_APP');

    TenantApp::create([
        'name' => 'CMS',
        'title' => 'Cms',
        'icon' => 'cms::icons.app',
        'route' => 'exampleApp.cms',
        'is_active' => true,
    ]);

    setRequestRoute('exampleApp.cms');

    TenantHelper::setSelectedAppFromRoute();

    expect(TenantHelper::getSelectedApp())->toBe('CMS');
});

it('does not change app when correct app is already active', function (): void {
    TenantApp::create([
        'name' => 'CMS',
        'title' => 'Cms',
        'icon' => 'cms::icons.app',
        'route' => 'exampleApp.cms',
        'is_active' => true,
    ]);

    TenantHelper::setSelectedApp('CMS');

    setRequestRoute('exampleApp.cms');

    TenantHelper::setSelectedAppFromRoute();

    expect(TenantHelper::getSelectedApp())->toBe('CMS');
});
