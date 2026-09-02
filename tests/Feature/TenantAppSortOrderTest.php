<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns tenant apps ordered by sort_order', function (): void {
    $tenant = Tenant::factory()->create();

    $appA = TenantApp::create(['title' => 'App A', 'name' => 'app-a', 'icon' => 'icon-a', 'route' => 'app-a.index', 'is_active' => true]);
    $appB = TenantApp::create(['title' => 'App B', 'name' => 'app-b', 'icon' => 'icon-b', 'route' => 'app-b.index', 'is_active' => true]);
    $appC = TenantApp::create(['title' => 'App C', 'name' => 'app-c', 'icon' => 'icon-c', 'route' => 'app-c.index', 'is_active' => true]);

    $tenant->tenantApps()->attach($appA->id, ['sort_order' => 3]);
    $tenant->tenantApps()->attach($appB->id, ['sort_order' => 1]);
    $tenant->tenantApps()->attach($appC->id, ['sort_order' => 2]);

    $apps = $tenant->tenantApps()->get();

    expect($apps)->toHaveCount(3);
    expect($apps[0]->name)->toBe('app-b');
    expect($apps[1]->name)->toBe('app-c');
    expect($apps[2]->name)->toBe('app-a');
});

it('excludes inactive apps from sorted results', function (): void {
    $tenant = Tenant::factory()->create();

    $activeApp = TenantApp::create(['title' => 'Active', 'name' => 'active', 'icon' => 'icon', 'route' => 'active.index', 'is_active' => true]);
    $inactiveApp = TenantApp::create(['title' => 'Inactive', 'name' => 'inactive', 'icon' => 'icon', 'route' => 'inactive.index', 'is_active' => false]);

    $tenant->tenantApps()->attach($activeApp->id, ['sort_order' => 2]);
    $tenant->tenantApps()->attach($inactiveApp->id, ['sort_order' => 1]);

    $apps = $tenant->tenantApps()->get();

    expect($apps)->toHaveCount(1);
    expect($apps[0]->name)->toBe('active');
});
