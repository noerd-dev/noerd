<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('fails when no tenants exist', function (): void {
    // The message is the behaviour: it names the command that has to run first.
    $this->artisan('noerd:make-admin-user', [
        '--name' => 'Test Admin',
        '--email' => 'admin@example.com',
        '--password' => 'password123',
    ])
        ->expectsOutput('No tenants found. Please run "php artisan noerd:make-tenant" first.')
        ->assertExitCode(1);

    // Verify user was NOT created
    expect(NoerdUser::where('email', 'admin@example.com')->exists())->toBeFalse();
});

it('creates a new admin user with command options', function (): void {
    // Create a tenant first so the make-admin command has something to work with
    Tenant::factory()->create(['name' => 'Test Tenant']);

    $this->artisan('noerd:make-admin-user', [
        '--name' => 'Test Admin',
        '--email' => 'admin@example.com',
        '--password' => 'password123',
    ])->assertExitCode(0);

    // Verify user was created
    $user = NoerdUser::where('email', 'admin@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Test Admin');
    expect($user->isAdminOfAnyTenant())->toBeTrue();
    expect($user->super_admin)->toBeFalse();
    expect($user->selected_tenant_id)->not->toBeNull();
});

it('creates a super admin user when flag is provided', function (): void {
    // Create a tenant first
    Tenant::factory()->create(['name' => 'Test Tenant']);

    $this->artisan('noerd:make-admin-user', [
        '--name' => 'Super Admin',
        '--email' => 'superadmin@example.com',
        '--password' => 'password123',
        '--super-admin' => true,
    ])->assertExitCode(0);

    // Verify user was created as super admin
    $user = NoerdUser::where('email', 'superadmin@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->super_admin)->toBeTrue();
    expect($user->isSuperAdmin())->toBeTrue();
});

it('rejects invalid credentials', function (array $options, string $error): void {
    Tenant::factory()->create(['name' => 'Test Tenant']);

    // The error message is the behaviour: it tells the operator what to fix.
    $this->artisan('noerd:make-admin-user', $options)
        ->expectsOutput($error)
        ->assertExitCode(1);

    expect(NoerdUser::where('email', $options['--email'])->exists())->toBeFalse();
})->with([
    'invalid email format' => [
        ['--name' => 'Test User', '--email' => 'invalid-email', '--password' => 'password123'],
        'Please enter a valid email address.',
    ],
    'password shorter than 8 characters' => [
        ['--name' => 'Test User', '--email' => 'test@example.com', '--password' => 'short'],
        'Password must be at least 8 characters.',
    ],
]);

it('fails with duplicate email', function (): void {
    Tenant::factory()->create(['name' => 'Test Tenant']);
    NoerdUser::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('noerd:make-admin-user', [
        '--name' => 'Test User',
        '--email' => 'existing@example.com',
        '--password' => 'password123',
    ])
        ->expectsOutput('A user with this email already exists.')
        ->assertExitCode(1);

    expect(NoerdUser::where('email', 'existing@example.com')->count())->toBe(1);
});

it('assigns user to all tenants as admin', function (): void {
    // Create multiple tenants
    $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1']);
    $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2']);

    $this->artisan('noerd:make-admin-user', [
        '--name' => 'Multi-Tenant Admin',
        '--email' => 'multiadmin@example.com',
        '--password' => 'password123',
    ])
        ->assertExitCode(0);

    $user = NoerdUser::where('email', 'multiadmin@example.com')->first();

    // Verify user has access to all tenants
    expect($user->tenants->count())->toBe(2);
    expect($user->tenants->contains($tenant1->id))->toBeTrue();
    expect($user->tenants->contains($tenant2->id))->toBeTrue();
});
