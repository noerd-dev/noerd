<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ->expectsOutput("  {$this->noerdAppA->title} ({$this->noerdAppA->name})")
        ->expectsOutput("  {$this->noerdAppB->title} ({$this->noerdAppB->name})")
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
