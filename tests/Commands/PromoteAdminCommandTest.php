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
    $this->artisan('noerd:promote-admin', ['user' => (string) $user->id])->assertExitCode(0);

    // Verify user is now admin
    $user->refresh();
    expect($user->isAdminOfAnyTenant())->toBeTrue();

    // Verify user has the admin profile attached
    $userTenant = $user->tenants()
        ->where('tenant_id', $tenant->id)
        ->first();
    expect($userTenant->pivot->profile_key)->toBe(Profile::Admin->value);
});

it('promotes a user addressed by email', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->create(['email' => 'promote-me@example.com']);

    $this->artisan('noerd:promote-admin', ['user' => 'promote-me@example.com'])->assertExitCode(0);

    expect($user->fresh()->isAdminOfAnyTenant())->toBeTrue();
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
    $this->artisan('noerd:promote-admin', ['user' => (string) $user->id])->assertExitCode(0);

    // Verify user is now admin on both tenants
    $user->refresh();
    expect($user->isAdminOfAnyTenant())->toBeTrue()
        ->and($user->isAdmin($tenant1->id))->toBeTrue()
        ->and($user->isAdmin($tenant2->id))->toBeTrue();
});

it('selects one of the user\'s own tenants, never a tenant they do not belong to', function (): void {
    $foreign = Tenant::factory()->create(['name' => 'Foreign']);
    $own = Tenant::factory()->create(['name' => 'Own']);
    $user = NoerdUser::factory()->create(['selected_tenant_id' => null]);
    $user->tenants()->attach($own->id, ['profile_key' => Profile::User->value]);

    $this->artisan('noerd:promote-admin', ['user' => (string) $user->id])->assertExitCode(0);

    $user->refresh();
    expect($user->selected_tenant_id)->toBe($own->id)
        ->and($user->isAdmin($own->id))->toBeTrue()
        ->and($user->isAdmin($foreign->id))->toBeFalse();
});

it('recognizes user who is already admin but ensures tenant assignment', function (): void {
    // Create an admin user
    $user = NoerdUser::factory()->adminUser()->create();

    expect($user->isAdminOfAnyTenant())->toBeTrue();

    // Run the command - should warn but continue to ensure tenant assignment
    $this->artisan('noerd:promote-admin', ['user' => (string) $user->id])
        ->expectsOutputToContain('already an admin')
        ->assertExitCode(0);

    // Verify selected_tenant_id is set
    $user->refresh();
    expect($user->selected_tenant_id)->not->toBeNull();
});

it('rejects an unknown user', function (string $identifier): void {
    // The error message is the behaviour: it tells the operator what to fix.
    $this->artisan('noerd:promote-admin', ['user' => $identifier])
        ->expectsOutput("User '{$identifier}' not found.")
        ->assertExitCode(1);
})->with([
    'unknown email' => ['nobody@example.com'],
    'unknown id' => ['99999'],
]);

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
    $this->artisan('noerd:promote-admin', ['user' => (string) $user->id])->assertExitCode(0);

    // Verify user now has admin on both tenants
    $user->refresh();
    $tenant1Profile = $user->tenants()->where('tenant_id', $tenant1->id)->first();
    $tenant2Profile = $user->tenants()->where('tenant_id', $tenant2->id)->first();
    expect($tenant1Profile->pivot->profile_key)->toBe(Profile::Admin->value)
        ->and($tenant2Profile->pivot->profile_key)->toBe(Profile::Admin->value);
});
