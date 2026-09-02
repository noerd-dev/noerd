<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('deletes the account and its tenant links after confirming the correct password', function (): void {
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create(['password' => 'secret-pw']);
    $user->tenants()->attach($tenant->id);
    $this->actingAs($user);

    Livewire::test('noerd::delete-account-modal')
        ->set('password', 'secret-pw')
        ->call('deleteUser')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect(NoerdUser::find($user->id))->toBeNull();
    expect(DB::table('users_tenants')->where('user_id', $user->id)->count())->toBe(0);
});

it('refuses to delete the last administrator of a tenant', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Acme']);
    $admin = NoerdUser::factory()->create(['password' => 'secret-pw']);
    $admin->tenants()->attach($tenant->id, ['profile_key' => Noerd\Enums\Profile::Admin->value]);
    $this->actingAs($admin);

    Livewire::test('noerd::delete-account-modal')
        ->set('password', 'secret-pw')
        ->call('deleteUser')
        ->assertHasErrors('password');

    expect(NoerdUser::find($admin->id))->not->toBeNull();
});

it('opens the confirmation modal from the profile form', function (): void {
    $this->actingAs(NoerdUser::factory()->create());

    Livewire::test('noerd::profile.delete-user-form')
        ->call('openConfirmation')
        ->assertDispatched('noerdModal', modalComponent: 'noerd::delete-account-modal');
});

it('keeps the account when the password does not match', function (): void {
    $user = NoerdUser::factory()->create(['password' => 'secret-pw']);
    $this->actingAs($user);

    Livewire::test('noerd::delete-account-modal')
        ->set('password', 'wrong-pw')
        ->call('deleteUser')
        ->assertHasErrors('password');

    expect(NoerdUser::find($user->id))->not->toBeNull();
});
