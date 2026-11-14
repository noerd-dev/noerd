<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Noerd\Events\TenantAppRemoved;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Tenant::removeApp() is the counterpart of assignApp(): it detaches the pivot
 | row once and fires TenantAppRemoved for an app the tenant held.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->zzApp = TenantApp::factory()->create(['name' => 'ZZ_REMOVE', 'is_active' => true]);
    $this->tenant->assignApp($this->zzApp);

    Event::fake([TenantAppRemoved::class]);
});

it('detaches a held app and fires the event once', function (): void {
    expect($this->tenant->removeApp($this->zzApp))->toBeTrue()
        ->and($this->tenant->removeApp($this->zzApp))->toBeFalse();

    expect($this->tenant->tenantApps()->count())->toBe(0);
    Event::assertDispatchedTimes(TenantAppRemoved::class, 1);
    Event::assertDispatched(
        TenantAppRemoved::class,
        fn(TenantAppRemoved $event): bool => $event->tenantId === $this->tenant->id && $event->appName === 'ZZ_REMOVE',
    );
});

it('finds the app by its name in any case and by its id', function (): void {
    expect($this->tenant->removeApp('zz_remove'))->toBeTrue();

    $this->tenant->assignApp($this->zzApp);

    expect($this->tenant->removeApp($this->zzApp->id))->toBeTrue();
    Event::assertDispatchedTimes(TenantAppRemoved::class, 2);
});

it('returns false for an unknown id and throws for an unknown name', function (): void {
    expect($this->tenant->removeApp(999999))->toBeFalse();
    Event::assertNotDispatched(TenantAppRemoved::class);

    $this->tenant->removeApp('ZZ_DOES_NOT_EXIST');
})->throws(InvalidArgumentException::class);

it('keeps the apps of other tenants', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherTenant->assignApp($this->zzApp);

    $this->tenant->removeApp($this->zzApp);

    expect($otherTenant->tenantApps()->pluck('tenant_apps.id')->all())->toBe([$this->zzApp->id]);
});
