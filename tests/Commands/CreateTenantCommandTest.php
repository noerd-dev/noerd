<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\Tenant;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

it('creates a new tenant with command options', function (): void {
    $this->artisan('noerd:create-tenant', [
        '--name' => 'New Tenant',
    ])
        ->expectsOutput("Tenant 'New Tenant' created successfully.")
        ->expectsOutput('  ✓ Created USER profile')
        ->expectsOutput('  ✓ Created ADMIN profile')
        ->expectsOutput("✅ Tenant 'New Tenant' is ready to use!")
        ->assertExitCode(0);

    // Verify tenant was created
    $tenant = Tenant::where('name', 'New Tenant')->first();
    expect($tenant)->not->toBeNull();
    expect($tenant->uuid)->not->toBeNull();
});

it('creates default USER and ADMIN profiles', function (): void {
    $this->artisan('noerd:create-tenant', [
        '--name' => 'Profile Tenant',
    ])
        ->assertExitCode(0);

    $tenant = Tenant::where('name', 'Profile Tenant')->first();

    // Verify USER profile was created
    $userProfile = Profile::where('tenant_id', $tenant->id)
        ->where('key', 'USER')
        ->first();
    expect($userProfile)->not->toBeNull();
    expect($userProfile->name)->toBe('User');

    // Verify ADMIN profile was created
    $adminProfile = Profile::where('tenant_id', $tenant->id)
        ->where('key', 'ADMIN')
        ->first();
    expect($adminProfile)->not->toBeNull();
    expect($adminProfile->name)->toBe('Admin');
});

it('generates unique uuid for each tenant', function (): void {
    $this->artisan('noerd:create-tenant', ['--name' => 'Tenant 1'])->assertExitCode(0);
    $this->artisan('noerd:create-tenant', ['--name' => 'Tenant 2'])->assertExitCode(0);

    $tenant1 = Tenant::where('name', 'Tenant 1')->first();
    $tenant2 = Tenant::where('name', 'Tenant 2')->first();

    expect($tenant1->uuid)->not->toBe($tenant2->uuid);
});

it('fails with name shorter than 3 characters', function (): void {
    $this->artisan('noerd:create-tenant', [
        '--name' => 'AB',
    ])
        ->expectsOutput('Tenant name must be at least 3 characters.')
        ->assertExitCode(1);
});

it('fails with name longer than 50 characters', function (): void {
    $this->artisan('noerd:create-tenant', [
        '--name' => str_repeat('A', 51),
    ])
        ->expectsOutput('Tenant name must be at most 50 characters.')
        ->assertExitCode(1);
});

it('outputs tenant ID and UUID after creation', function (): void {
    $this->artisan('noerd:create-tenant', [
        '--name' => 'ID Test Tenant',
    ])
        ->expectsOutputToContain('ID:')
        ->assertExitCode(0);

    // Verify tenant has UUID set
    $tenant = \Noerd\Noerd\Models\Tenant::where('name', 'ID Test Tenant')->first();
    expect($tenant->uuid)->not->toBeNull();
});
