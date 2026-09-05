<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Noerd\Events\TenantAppAssigned;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create test tenant
    $this->tenant = Tenant::factory()->create([
        'name' => 'Test Restaurant',
    ]);

    // Create test apps (independent from other modules)
    $this->noerdAppA = TenantApp::create([
        'name' => 'NOERD_APP_A',
        'title' => 'Noerd App A',
        'icon' => 'noerd-app-a',
        'route' => 'noerd-app-a.index',
        'is_active' => true,
    ]);
    $this->noerdAppB = TenantApp::create([
        'name' => 'NOERD_APP_B',
        'title' => 'Noerd App B',
        'icon' => 'noerd-app-b',
        'route' => 'noerd-app-b.index',
        'is_active' => true,
    ]);

    $this->assignedAppIds = fn(): array => $this->tenant->fresh()->tenantApps->pluck('id')->sort()->values()->all();
});

it('fails with non-existent tenant id', function (): void {
    // The message is the behaviour: the operator has to see which id was wrong.
    $this->artisan('noerd:assign-apps-to-tenant', ['--tenant-id' => 99999])
        ->expectsOutput('Tenant with ID 99999 not found.')
        ->assertExitCode(1);
});

it('fails gracefully when no active apps exist', function (): void {
    // Make all apps inactive
    TenantApp::query()->update(['is_active' => false]);

    $this->artisan('noerd:assign-apps-to-tenant', ['--tenant-id' => $this->tenant->id])
        ->expectsOutput('No active apps found.')
        ->assertExitCode(1);

    expect(($this->assignedAppIds)())->toBe([]);
});

it('assigns the selected apps to a tenant without any assignment yet', function (): void {
    $this->tenant->tenantApps()->detach();

    $this->artisan('noerd:assign-apps-to-tenant', ['--tenant-id' => $this->tenant->id])
        ->expectsQuestion('Select apps to assign to this tenant:', [$this->noerdAppA->id, $this->noerdAppB->id])
        ->assertExitCode(0);

    expect(($this->assignedAppIds)())
        ->toBe([$this->noerdAppA->id, $this->noerdAppB->id]);
});

it('syncs the selection onto the tenant, removing what was deselected', function (): void {
    $this->tenant->tenantApps()->attach([
        $this->noerdAppA->id,
        $this->noerdAppB->id,
    ]);

    $this->artisan('noerd:assign-apps-to-tenant', ['--tenant-id' => $this->tenant->id])
        ->expectsQuestion('Select apps to assign to this tenant:', [$this->noerdAppB->id])
        ->assertExitCode(0);

    expect(($this->assignedAppIds)())->toBe([$this->noerdAppB->id]);
});

it('announces only the apps the sync newly assigned', function (): void {
    Event::fake([TenantAppAssigned::class]);

    $this->tenant->tenantApps()->attach($this->noerdAppA->id);

    $this->artisan('noerd:assign-apps-to-tenant', ['--tenant-id' => $this->tenant->id])
        ->expectsQuestion('Select apps to assign to this tenant:', [$this->noerdAppA->id, $this->noerdAppB->id])
        ->assertExitCode(0);

    Event::assertDispatchedTimes(TenantAppAssigned::class, 1);
    Event::assertDispatched(
        TenantAppAssigned::class,
        fn(TenantAppAssigned $event): bool => $event->tenantId === $this->tenant->id && $event->appName === 'NOERD_APP_B',
    );
});
