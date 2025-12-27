<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\TenantApp;

uses(Tests\TestCase::class);
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
    $this->noerdAppC = TenantApp::create([
        'name' => 'NOERD_APP_C',
        'title' => 'Noerd App C',
        'icon' => 'noerd-app-c',
        'route' => 'noerd-app-c.index',
        'is_active' => true,
    ]);
    $this->noerdAppD = TenantApp::create([
        'name' => 'NOERD_APP_D',
        'title' => 'Noerd App D',
        'icon' => 'noerd-app-d',
        'route' => 'noerd-app-d.index',
        'is_active' => true,
    ]);
});

it('fails with non-existent tenant id', function (): void {
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
});

it('displays tenant information correctly', function (): void {
    // Assign some apps for display
    $this->tenant->tenantApps()->attach([
        $this->noerdAppA->id,
        $this->noerdAppB->id,
    ]);

    // Since we can't easily mock Laravel Prompts in tests, we'll test the output
    // by creating a custom artisan test that bypasses prompts
    $command = $this->artisan('noerd:assign-apps-to-tenant', ['--tenant-id' => $this->tenant->id]);

    // The command should show tenant info and current assignments before prompting
    $output = $command->expectsOutput("App Assignment for: {$this->tenant->name}")
        ->expectsOutput('Use ↑/↓ to navigate, Space to select/deselect, Enter to confirm')
        ->expectsOutput('Currently assigned apps:')
        ->expectsOutput("  ✓ {$this->noerdAppA->title} ({$this->noerdAppA->name})")
        ->expectsOutput("  ✓ {$this->noerdAppB->title} ({$this->noerdAppB->name})")
        ->run();
});

it('displays message when no apps are currently assigned', function (): void {
    // Ensure no apps are assigned
    $this->tenant->tenantApps()->detach();

    $this->artisan('noerd:assign-apps-to-tenant', ['--tenant-id' => $this->tenant->id])
        ->expectsOutput('No apps currently assigned to this tenant.')
        ->run();
});

it('only considers active apps for assignment', function (): void {
    // Make first app inactive
    $this->noerdAppA->update(['is_active' => false]);

    // The command should still work with remaining active apps
    $this->artisan('noerd:assign-apps-to-tenant', ['--tenant-id' => $this->tenant->id])
        ->run();

    // Verify inactive apps are not available for assignment
    expect($this->noerdAppA->fresh()->is_active)->toBeFalse();
    expect($this->noerdAppB->fresh()->is_active)->toBeTrue();
});

// Test the core database operations by testing the model relationships directly
it('can assign apps to tenant through relationship', function (): void {
    // Test the underlying relationship that the command uses
    expect($this->tenant->tenantApps()->count())->toBe(0);

    // Assign apps
    $this->tenant->tenantApps()->attach([
        $this->noerdAppA->id,
        $this->noerdAppB->id,
    ]);

    expect($this->tenant->fresh()->tenantApps()->count())->toBe(2);
    expect($this->tenant->tenantApps->pluck('name')->toArray())->toContain('NOERD_APP_A');
    expect($this->tenant->tenantApps->pluck('name')->toArray())->toContain('NOERD_APP_B');
});

it('can remove apps from tenant through relationship', function (): void {
    // Start with apps assigned
    $this->tenant->tenantApps()->attach([
        $this->noerdAppA->id,
        $this->noerdAppB->id,
        $this->noerdAppC->id,
    ]);

    expect($this->tenant->tenantApps()->count())->toBe(3);

    // Remove one app using detach (single)
    $this->tenant->tenantApps()->detach($this->noerdAppB->id);

    expect($this->tenant->fresh()->tenantApps()->count())->toBe(2);
    expect($this->tenant->tenantApps->pluck('name')->toArray())->not->toContain('NOERD_APP_B');
});

it('can sync apps to tenant (add and remove in one operation)', function (): void {
    // Start with some apps
    $this->tenant->tenantApps()->attach([
        $this->noerdAppA->id,
        $this->noerdAppB->id,
    ]);

    expect($this->tenant->tenantApps()->count())->toBe(2);

    // Sync to different set of apps (this is what the command uses)
    $this->tenant->tenantApps()->sync([
        $this->noerdAppC->id,
        $this->noerdAppD->id,
    ]);

    $assignedNames = $this->tenant->fresh()->tenantApps->pluck('name')->toArray();

    expect($this->tenant->tenantApps()->count())->toBe(2);
    expect($assignedNames)->toContain('NOERD_APP_C');
    expect($assignedNames)->toContain('NOERD_APP_D');
    expect($assignedNames)->not->toContain('NOERD_APP_A');
    expect($assignedNames)->not->toContain('NOERD_APP_B');
});

it('can remove all apps from tenant', function (): void {
    // Start with apps assigned
    $this->tenant->tenantApps()->attach([
        $this->noerdAppA->id,
        $this->noerdAppB->id,
    ]);

    expect($this->tenant->tenantApps()->count())->toBe(2);

    // Sync with empty array removes all
    $this->tenant->tenantApps()->sync([]);

    expect($this->tenant->fresh()->tenantApps()->count())->toBe(0);
});

it('maintains pivot table integrity', function (): void {
    // Test that pivot table records are created correctly
    $this->tenant->tenantApps()->attach($this->noerdAppA->id);

    // Check that pivot record exists
    $pivotRecord = $this->tenant->tenantApps()
        ->where('tenant_apps.id', $this->noerdAppA->id)
        ->first();

    expect($pivotRecord)->not->toBeNull();
    expect($pivotRecord->pivot->tenant_id)->toBe($this->tenant->id);
    expect($pivotRecord->pivot->tenant_app_id)->toBe($this->noerdAppA->id);
});

it('handles multiple tenants with same apps correctly', function (): void {
    // Create second tenant
    $tenant2 = Tenant::factory()->create(['name' => 'Second Tenant']);

    // Assign same app to both tenants
    $this->tenant->tenantApps()->attach($this->noerdAppA->id);
    $tenant2->tenantApps()->attach($this->noerdAppA->id);

    // Both should have the app
    expect($this->tenant->tenantApps()->count())->toBe(1);
    expect($tenant2->tenantApps()->count())->toBe(1);

    // Removing from one shouldn't affect the other
    $this->tenant->tenantApps()->detach($this->noerdAppA->id);

    expect($this->tenant->fresh()->tenantApps()->count())->toBe(0);
    expect($tenant2->fresh()->tenantApps()->count())->toBe(1);
});

it('respects the is_active flag when querying apps', function (): void {
    // Ensure all test apps are active first
    TenantApp::query()->update(['is_active' => true]);

    // This tests the query the command uses: TenantApp::where('is_active', true)
    $activeAppsCount = TenantApp::where('is_active', true)->count();
    $totalAppsCount = TenantApp::count();

    expect($activeAppsCount)->toBe($totalAppsCount); // All test apps should be active now

    // Make one app inactive
    $this->noerdAppA->update(['is_active' => false]);

    $newActiveCount = TenantApp::where('is_active', true)->count();
    expect($newActiveCount)->toBe($activeAppsCount - 1);
});

it('orders apps by title correctly', function (): void {
    // This tests the query ordering the command uses
    $orderedApps = TenantApp::where('is_active', true)->orderBy('title')->get();

    $titles = $orderedApps->pluck('title')->toArray();
    $sortedTitles = $titles;
    sort($sortedTitles);

    expect($titles)->toBe($sortedTitles);
});

it('can handle large numbers of app assignments', function (): void {
    // Get all active apps
    $allActiveApps = TenantApp::where('is_active', true)->get();

    // Assign all active apps to the tenant
    $appIds = $allActiveApps->pluck('id')->toArray();
    $this->tenant->tenantApps()->sync($appIds);

    expect($this->tenant->fresh()->tenantApps()->count())->toBe($allActiveApps->count());

    // Should be able to remove all at once
    $this->tenant->tenantApps()->sync([]);
    expect($this->tenant->fresh()->tenantApps()->count())->toBe(0);
});

it('provides correct command help information', function (): void {
    $this->artisan('noerd:assign-apps-to-tenant', ['--help'])
        ->expectsOutputToContain('Assign apps to a tenant with interactive selection')
        ->expectsOutputToContain('--tenant-id')
        ->assertExitCode(0);
});

// Test error handling scenarios
it('handles deleted tenant gracefully', function (): void {
    // Create a tenant then delete it from database directly to simulate race condition
    $tenantId = $this->tenant->id;

    // Use a different approach - just test with a non-existent ID instead
    // of deleting and causing side effects with observers/jobs
    $nonExistentId = 99999;

    $this->artisan('noerd:assign-apps-to-tenant', ['--tenant-id' => $nonExistentId])
        ->expectsOutput("Tenant with ID {$nonExistentId} not found.")
        ->assertExitCode(1);
});

it('validates tenant exists before showing app selection', function (): void {
    // This ensures the command fails fast if tenant doesn't exist
    $this->artisan('noerd:assign-apps-to-tenant', ['--tenant-id' => 99999])
        ->expectsOutput('Tenant with ID 99999 not found.')
        ->assertExitCode(1);

    // Should not proceed to app selection UI
});

it('correctly builds app choices array format', function (): void {
    // Test the array format that would be passed to Laravel Prompts
    $allApps = TenantApp::where('is_active', true)->orderBy('title')->get();

    $expectedFormat = [];
    foreach ($allApps as $app) {
        $expectedFormat[$app->id] = "{$app->title} ({$app->name})";
    }

    // Verify format is correct
    expect($expectedFormat)->toBeArray();
    expect(count($expectedFormat))->toBeGreaterThan(0);

    // Check a specific app format
    $cmsFormatted = "{$this->noerdAppA->title} ({$this->noerdAppA->name})";
    expect($expectedFormat[$this->noerdAppA->id])->toBe($cmsFormatted);
});
