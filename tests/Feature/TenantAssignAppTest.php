<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Noerd\Events\TenantAppAssigned;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Tenant::assignApp() is the one way an app reaches a tenant: it attaches the
 | pivot row once and fires TenantAppAssigned for the newly held app, whatever
 | path (screen, installer, demo, provisioning) called it.
 */

beforeEach(function (): void {
    Event::fake([TenantAppAssigned::class]);

    $this->tenant = Tenant::factory()->create();
    $this->app1 = TenantApp::factory()->create(['name' => 'ZZ_ONE', 'is_active' => true]);
    $this->app2 = TenantApp::factory()->create(['name' => 'ZZ_TWO', 'is_active' => true]);
});

it('attaches the app at the end of the app bar and fires the event once', function (): void {
    expect($this->tenant->assignApp($this->app1))->toBeTrue()
        ->and($this->tenant->assignApp($this->app1))->toBeFalse();

    expect($this->tenant->tenantApps()->pluck('tenant_apps.id')->all())->toBe([$this->app1->id]);
    Event::assertDispatchedTimes(TenantAppAssigned::class, 1);
    Event::assertDispatched(
        TenantAppAssigned::class,
        fn(TenantAppAssigned $event): bool => $event->tenantId === $this->tenant->id && $event->appName === 'ZZ_ONE',
    );
});

it('numbers a new app behind the ones the tenant already holds', function (): void {
    $this->tenant->assignApp($this->app1, ['sort_order' => 7]);
    $this->tenant->assignApp($this->app2);

    expect($this->tenant->tenantApps()->pluck('sort_order', 'tenant_apps.id')->all())
        ->toBe([$this->app1->id => 7, $this->app2->id => 8]);
});

it('accepts the app by id or by its stored name and refuses an unknown one', function (): void {
    expect($this->tenant->assignApp($this->app1->id))->toBeTrue()
        ->and($this->tenant->assignApp('ZZ_TWO'))->toBeTrue()
        ->and($this->tenant->assignApp('ZZ_MISSING'))->toBeFalse()
        ->and($this->tenant->assignApp(999999))->toBeFalse();

    Event::assertDispatchedTimes(TenantAppAssigned::class, 2);
});

it('keeps the pivot values handed in', function (): void {
    $this->tenant->assignApp($this->app1, ['is_hidden' => true, 'sort_order' => 3]);

    $pivot = $this->tenant->tenantApps()->first()->pivot;

    expect((bool) $pivot->is_hidden)->toBeTrue()
        ->and((int) $pivot->sort_order)->toBe(3);
});
