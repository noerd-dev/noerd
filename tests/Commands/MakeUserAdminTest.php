<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Enums\Profile;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('successfully makes a user admin', function (): void {
    // Create a user with tenant access but no admin privileges
    $user = NoerdUser::factory()->withExampleTenant()->create();
    $tenant = $user->tenants->first();

    // Ensure user is not admin initially
    expect($user->isAdminOfAnyTenant())->toBeFalse();

    // Run the command
    $this->artisan('noerd:make-admin', ['user_id' => $user->id])
        ->expectsOutput("Processing user: {$user->name} ({$user->email})")
        ->expectsOutput('User has access to 1 tenant(s).')
        ->expectsOutput("Processing tenant: {$tenant->name}")
        ->expectsOutput("  ✓ Granted ADMIN access for tenant: {$tenant->name}")
        ->expectsOutput("✅ User {$user->name} is now an admin with access to Setup!")
        ->assertExitCode(0);

    // Verify user is now admin
    $user->refresh();
    expect($user->isAdminOfAnyTenant())->toBeTrue();

    // Verify user has the admin profile attached
    $userTenant = $user->tenants()
        ->where('tenant_id', $tenant->id)
        ->first();
    expect($userTenant->pivot->profile_key)->toBe(Profile::Admin->value);
});

it('handles user with multiple tenants', function (): void {
    // Create user with multiple tenants
    $user = NoerdUser::factory()->create();
    $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1']);
    $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2']);

    // Attach user to both tenants
    $user->tenants()->attach($tenant1->id, ['profile_key' => Profile::User->value]);
    $user->tenants()->attach($tenant2->id, ['profile_key' => Profile::User->value]);

    expect($user->isAdminOfAnyTenant())->toBeFalse();

    // Run the command
    $this->artisan('noerd:make-admin', ['user_id' => $user->id])
        ->expectsOutput('User has access to 2 tenant(s).')
        ->expectsOutput("Processing tenant: {$tenant1->name}")
        ->expectsOutput("Processing tenant: {$tenant2->name}")
        ->expectsOutput('- ADMIN access granted: 2')
        ->assertExitCode(0);

    // Verify user is now admin on both tenants
    $user->refresh();
    expect($user->isAdminOfAnyTenant())->toBeTrue()
        ->and($user->isAdmin($tenant1->id))->toBeTrue()
        ->and($user->isAdmin($tenant2->id))->toBeTrue();
});

it('recognizes user who is already admin but ensures tenant assignment', function (): void {
    // Create an admin user
    $user = NoerdUser::factory()->adminUser()->create();

    expect($user->isAdminOfAnyTenant())->toBeTrue();

    // Run the command - should warn but continue to ensure tenant assignment
    $this->artisan('noerd:make-admin', ['user_id' => $user->id])
        ->expectsOutput("Processing user: {$user->name} ({$user->email})")
        ->expectsOutput('User is already an admin. Ensuring tenant assignment is correct...')
        ->expectsOutput("✅ User {$user->name} remains an admin. Tenant assignment verified.")
        ->assertExitCode(0);

    // Verify selected_tenant_id is set
    $user->refresh();
    expect($user->selected_tenant_id)->not->toBeNull();
});

it('upgrades a USER profile to ADMIN', function (): void {
    // Create user with tenant access under the USER profile
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create(['name' => 'Test Tenant']);
    $user->tenants()->attach($tenant->id, ['profile_key' => Profile::User->value]);

    expect($user->isAdminOfAnyTenant())->toBeFalse();

    // Run the command
    $this->artisan('noerd:make-admin', ['user_id' => $user->id])
        ->expectsOutput("  ✓ Granted ADMIN access for tenant: {$tenant->name}")
        ->expectsOutput('- ADMIN access granted: 1')
        ->assertExitCode(0);

    // Verify user is now admin
    $user->refresh();
    expect($user->isAdminOfAnyTenant())->toBeTrue();
});

it('fails with invalid user id', function (): void {
    $this->artisan('noerd:make-admin', ['user_id' => 'invalid'])
        ->expectsOutput('User ID must be a number.')
        ->assertExitCode(1);
});

it('fails with non-existent user id', function (): void {
    $this->artisan('noerd:make-admin', ['user_id' => 99999])
        ->expectsOutput('User with ID 99999 not found.')
        ->assertExitCode(1);
});

it('handles user with partial admin access correctly', function (): void {
    // Create user with two tenants, admin on one, user on the other
    $user = NoerdUser::factory()->create();
    $tenant1 = Tenant::factory()->create(['name' => 'Admin Tenant']);
    $tenant2 = Tenant::factory()->create(['name' => 'User Tenant']);

    // Attach user to both tenants
    $user->tenants()->attach($tenant1->id, ['profile_key' => Profile::Admin->value]);
    $user->tenants()->attach($tenant2->id, ['profile_key' => Profile::User->value]);

    // User should already be admin due to first tenant
    expect($user->isAdminOfAnyTenant())->toBeTrue();

    // Command should continue and grant admin on the second tenant too
    $this->artisan('noerd:make-admin', ['user_id' => $user->id])
        ->expectsOutput('User is already an admin. Ensuring tenant assignment is correct...')
        ->expectsOutput("  - User already has ADMIN access for tenant: {$tenant1->name}")
        ->expectsOutput("  ✓ Granted ADMIN access for tenant: {$tenant2->name}")
        ->expectsOutput("✅ User {$user->name} remains an admin. Tenant assignment verified.")
        ->assertExitCode(0);

    // Verify user now has admin on both tenants
    $user->refresh();
    $tenant2Profile = $user->tenants()->where('tenant_id', $tenant2->id)->first();
    expect($tenant2Profile->pivot->profile_key)->toBe(Profile::Admin->value);
});
