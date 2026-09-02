<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The super admin flag is $guarded and has no screen — the console is the
 | only way to grant it and, with --revoke, the only way to withdraw it.
 */

it('grants the super admin flag by user id', function (): void {
    $user = NoerdUser::factory()->create(['super_admin' => false]);

    $this->artisan('noerd:super-admin', ['user' => (string) $user->id])
        ->expectsOutput("User '{$user->name}' ({$user->email}) is now a super admin.")
        ->assertExitCode(0);

    expect($user->fresh()->isSuperAdmin())->toBeTrue();
});

it('grants the super admin flag by email', function (): void {
    $user = NoerdUser::factory()->create(['super_admin' => false, 'email' => 'operator@example.com']);

    $this->artisan('noerd:super-admin', ['user' => 'operator@example.com'])
        ->assertExitCode(0);

    expect($user->fresh()->isSuperAdmin())->toBeTrue();
});

it('leaves an existing super admin untouched when granting again', function (): void {
    $user = NoerdUser::factory()->create(['super_admin' => true]);

    $this->artisan('noerd:super-admin', ['user' => (string) $user->id])
        ->expectsOutput("User '{$user->name}' ({$user->email}) already is a super admin.")
        ->assertExitCode(0);

    expect($user->fresh()->isSuperAdmin())->toBeTrue();
});

it('revokes the super admin flag while another super admin remains', function (): void {
    NoerdUser::factory()->create(['super_admin' => true]);
    $user = NoerdUser::factory()->create(['super_admin' => true]);

    $this->artisan('noerd:super-admin', ['user' => (string) $user->id, '--revoke' => true])
        ->expectsOutput("User '{$user->name}' ({$user->email}) is no longer a super admin.")
        ->assertExitCode(0);

    expect($user->fresh()->isSuperAdmin())->toBeFalse();
});

it('refuses to revoke the last super admin without --force', function (): void {
    $user = NoerdUser::factory()->create(['super_admin' => true]);

    $this->artisan('noerd:super-admin', ['user' => (string) $user->id, '--revoke' => true])
        ->expectsOutput("User '{$user->name}' ({$user->email}) is the last super admin of this installation. Pass --force to revoke anyway.")
        ->assertExitCode(1);

    expect($user->fresh()->isSuperAdmin())->toBeTrue();
});

it('revokes the last super admin with --force', function (): void {
    $user = NoerdUser::factory()->create(['super_admin' => true]);

    $this->artisan('noerd:super-admin', ['user' => (string) $user->id, '--revoke' => true, '--force' => true])
        ->assertExitCode(0);

    expect($user->fresh()->isSuperAdmin())->toBeFalse();
});

it('reports a revoke on a user that is no super admin', function (): void {
    $user = NoerdUser::factory()->create(['super_admin' => false]);

    $this->artisan('noerd:super-admin', ['user' => (string) $user->id, '--revoke' => true])
        ->expectsOutput("User '{$user->name}' ({$user->email}) is not a super admin.")
        ->assertExitCode(0);
});

it('fails for an unknown user', function (): void {
    $this->artisan('noerd:super-admin', ['user' => 'nobody@example.com'])
        ->expectsOutput("User 'nobody@example.com' not found.")
        ->assertExitCode(1);
});
