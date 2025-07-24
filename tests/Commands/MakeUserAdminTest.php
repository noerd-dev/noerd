<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nywerk\Noerd\Models\Profile;
use Nywerk\Noerd\Models\Tenant;
use Nywerk\Noerd\Models\User;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

it('successfully makes a user admin', function (): void {
    // Create a user with tenant access but no admin privileges
    $user = User::factory()->withDeliveryAndMenu()->create();
    $tenant = $user->tenants->first();

    // Ensure user is not admin initially
    expect($user->isAdmin())->toBeFalse();

    // Run the command
    $this->artisan('noerd:make-admin', ['user_id' => $user->id])
        ->expectsOutput("Processing user: {$user->name} ({$user->email})")
        ->expectsOutput("User has access to 1 tenant(s).")
        ->expectsOutput("Processing tenant: {$tenant->name}")
        ->expectsOutput("  ✓ Created ADMIN profile for tenant: {$tenant->name}")
        ->expectsOutput("  ✓ Granted ADMIN access for tenant: {$tenant->name}")
        ->expectsOutput("✅ User {$user->name} is now an admin with access to Setup!")
        ->assertExitCode(0);

    // Verify user is now admin
    $user->refresh();
    expect($user->isAdmin())->toBeTrue();

    // Verify ADMIN profile was created
    $adminProfile = Profile::where('tenant_id', $tenant->id)
        ->where('key', 'ADMIN')
        ->first();
    expect($adminProfile)->not->toBeNull();
    expect($adminProfile->name)->toBe('Administrator');

    // Verify user has admin profile attached
    $userTenant = $user->tenants()
        ->where('tenant_id', $tenant->id)
        ->first();
    expect($userTenant->pivot->profile_id)->toBe($adminProfile->id);
});

it('handles user with multiple tenants', function (): void {
    // Create user with multiple tenants
    $user = User::factory()->create();
    $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1']);
    $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2']);

    // Create user profiles for both tenants
    $profile1 = Profile::factory()->create([
        'tenant_id' => $tenant1->id,
        'key' => 'USER',
        'name' => 'User',
    ]);
    $profile2 = Profile::factory()->create([
        'tenant_id' => $tenant2->id,
        'key' => 'USER',
        'name' => 'User',
    ]);

    // Attach user to both tenants
    $user->tenants()->attach($tenant1->id, ['profile_id' => $profile1->id]);
    $user->tenants()->attach($tenant2->id, ['profile_id' => $profile2->id]);

    expect($user->isAdmin())->toBeFalse();

    // Run the command
    $this->artisan('noerd:make-admin', ['user_id' => $user->id])
        ->expectsOutput("User has access to 2 tenant(s).")
        ->expectsOutput("Processing tenant: {$tenant1->name}")
        ->expectsOutput("Processing tenant: {$tenant2->name}")
        ->expectsOutput("- ADMIN profiles created: 2")
        ->expectsOutput("- ADMIN access granted: 2")
        ->assertExitCode(0);

    // Verify user is now admin
    $user->refresh();
    expect($user->isAdmin())->toBeTrue();

    // Verify both tenants have admin profiles
    expect(Profile::where('tenant_id', $tenant1->id)->where('key', 'ADMIN')->exists())->toBeTrue();
    expect(Profile::where('tenant_id', $tenant2->id)->where('key', 'ADMIN')->exists())->toBeTrue();
});

it('recognizes user who is already admin', function (): void {
    // Create an admin user
    $user = User::factory()->adminUser()->create();

    expect($user->isAdmin())->toBeTrue();

    // Run the command
    $this->artisan('noerd:make-admin', ['user_id' => $user->id])
        ->expectsOutput("Processing user: {$user->name} ({$user->email})")
        ->expectsOutput("User is already an admin.")
        ->assertExitCode(0);
});

it('handles existing admin profile correctly', function (): void {
    // Create user with tenant access
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['name' => 'Test Tenant']);

    // Create admin profile for the tenant
    $adminProfile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'ADMIN',
        'name' => 'Administrator',
    ]);

    // Create user profile 
    $userProfile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => 'USER',
        'name' => 'User',
    ]);

    // Attach user to tenant with user profile
    $user->tenants()->attach($tenant->id, ['profile_id' => $userProfile->id]);

    expect($user->isAdmin())->toBeFalse();

    // Run the command
    $this->artisan('noerd:make-admin', ['user_id' => $user->id])
        ->expectsOutput("  - ADMIN profile already exists for tenant: {$tenant->name}")
        ->expectsOutput("  ✓ Granted ADMIN access for tenant: {$tenant->name}")
        ->expectsOutput("- ADMIN profiles created: 0")
        ->expectsOutput("- ADMIN access granted: 1")
        ->assertExitCode(0);

    // Verify user is now admin
    $user->refresh();
    expect($user->isAdmin())->toBeTrue();
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

it('fails with user who has no tenant access', function (): void {
    // Create user without any tenant access
    $user = User::factory()->create();

    expect($user->tenants)->toBeEmpty();

    $this->artisan('noerd:make-admin', ['user_id' => $user->id])
        ->expectsOutput("Processing user: {$user->name} ({$user->email})")
        ->expectsOutput('User has no tenant access. Cannot make admin without tenant access.')
        ->assertExitCode(1);
});

it('handles user with partial admin access correctly', function (): void {
    // Create user with two tenants, admin on one, user on the other
    $user = User::factory()->create();
    $tenant1 = Tenant::factory()->create(['name' => 'Admin Tenant']);
    $tenant2 = Tenant::factory()->create(['name' => 'User Tenant']);

    // Create profiles
    $adminProfile1 = Profile::factory()->create([
        'tenant_id' => $tenant1->id,
        'key' => 'ADMIN',
        'name' => 'Administrator',
    ]);
    $userProfile2 = Profile::factory()->create([
        'tenant_id' => $tenant2->id,
        'key' => 'USER',
        'name' => 'User',
    ]);

    // Attach user to both tenants
    $user->tenants()->attach($tenant1->id, ['profile_id' => $adminProfile1->id]);
    $user->tenants()->attach($tenant2->id, ['profile_id' => $userProfile2->id]);

    // User should already be admin due to first tenant
    expect($user->isAdmin())->toBeTrue();

    $this->artisan('noerd:make-admin', ['user_id' => $user->id])
        ->expectsOutput('User is already an admin.')
        ->assertExitCode(0);
});

it('provides detailed summary output', function (): void {
    $user = User::factory()->withDeliveryAndMenu()->create();

    $this->artisan('noerd:make-admin', ['user_id' => $user->id])
        ->expectsOutput('Summary:')
        ->expectsOutput('- ADMIN profiles created: 1')
        ->expectsOutput('- ADMIN access granted: 1')
        ->assertExitCode(0);
});

it('verifies admin status after completion', function (): void {
    $user = User::factory()->withDeliveryAndMenu()->create();

    expect($user->isAdmin())->toBeFalse();

    $this->artisan('noerd:make-admin', ['user_id' => $user->id])
        ->expectsOutput("✅ User {$user->name} is now an admin with access to Setup!")
        ->assertExitCode(0);

    // Double-check the user can now access setup
    $user->refresh();
    expect($user->isAdmin())->toBeTrue();
    expect($user->profiles->where('key', 'ADMIN')->count())->toBeGreaterThan(0);
}); 