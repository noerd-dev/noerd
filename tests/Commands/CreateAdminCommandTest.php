<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Noerd\Models\Tenant;
use Noerd\Noerd\Models\User;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

it('creates a new admin user with command options', function (): void {
    // Create a tenant first so the make-admin command has something to work with
    Tenant::factory()->create(['name' => 'Test Tenant']);

    $this->artisan('noerd:create-admin', [
        '--name' => 'Test Admin',
        '--email' => 'admin@example.com',
        '--password' => 'password123',
    ])
        ->expectsOutput("User 'Test Admin' created successfully.")
        ->assertExitCode(0);

    // Verify user was created
    $user = User::where('email', 'admin@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Test Admin');
    expect($user->isAdmin())->toBeTrue();
    expect($user->super_admin)->toBeFalse();
});

it('creates a super admin user when flag is provided', function (): void {
    // Create a tenant first
    Tenant::factory()->create(['name' => 'Test Tenant']);

    $this->artisan('noerd:create-admin', [
        '--name' => 'Super Admin',
        '--email' => 'superadmin@example.com',
        '--password' => 'password123',
        '--super-admin' => true,
    ])
        ->expectsOutput("User 'Super Admin' created as Super Admin.")
        ->assertExitCode(0);

    // Verify user was created as super admin
    $user = User::where('email', 'superadmin@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->super_admin)->toBeTrue();
    expect($user->isSuperAdmin())->toBeTrue();
});

it('fails with invalid email format', function (): void {
    $this->artisan('noerd:create-admin', [
        '--name' => 'Test User',
        '--email' => 'invalid-email',
        '--password' => 'password123',
    ])
        ->expectsOutput('Please enter a valid email address.')
        ->assertExitCode(1);
});

it('fails with duplicate email', function (): void {
    // Create existing user
    User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('noerd:create-admin', [
        '--name' => 'Test User',
        '--email' => 'existing@example.com',
        '--password' => 'password123',
    ])
        ->expectsOutput('A user with this email already exists.')
        ->assertExitCode(1);
});

it('fails with password shorter than 8 characters', function (): void {
    $this->artisan('noerd:create-admin', [
        '--name' => 'Test User',
        '--email' => 'test@example.com',
        '--password' => 'short',
    ])
        ->expectsOutput('Password must be at least 8 characters.')
        ->assertExitCode(1);
});

it('assigns user to all tenants as admin', function (): void {
    // Create multiple tenants
    $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1']);
    $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2']);

    $this->artisan('noerd:create-admin', [
        '--name' => 'Multi-Tenant Admin',
        '--email' => 'multiadmin@example.com',
        '--password' => 'password123',
    ])
        ->assertExitCode(0);

    $user = User::where('email', 'multiadmin@example.com')->first();

    // Verify user has access to all tenants
    expect($user->tenants->count())->toBe(2);
    expect($user->tenants->contains($tenant1->id))->toBeTrue();
    expect($user->tenants->contains($tenant2->id))->toBeTrue();
});

it('sets selected_tenant_id after creation', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Test Tenant']);

    $this->artisan('noerd:create-admin', [
        '--name' => 'Test Admin',
        '--email' => 'admin@example.com',
        '--password' => 'password123',
    ])
        ->assertExitCode(0);

    $user = User::where('email', 'admin@example.com')->first();
    expect($user->selected_tenant_id)->not->toBeNull();
});
