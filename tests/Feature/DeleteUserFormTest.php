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

    Livewire::test('noerd::profile.delete-user-form')
        ->set('password', 'secret-pw')
        ->call('deleteUser')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect(NoerdUser::find($user->id))->toBeNull();
    expect(DB::table('users_tenants')->where('user_id', $user->id)->count())->toBe(0);
});

it('keeps the account when the password does not match', function (): void {
    $user = NoerdUser::factory()->create(['password' => 'secret-pw']);
    $this->actingAs($user);

    Livewire::test('noerd::profile.delete-user-form')
        ->set('password', 'wrong-pw')
        ->call('deleteUser')
        ->assertHasErrors('password');

    expect(NoerdUser::find($user->id))->not->toBeNull();
});
