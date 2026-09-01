<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Listeners\RecordLogin;
use Noerd\Models\NoerdLogin;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('persists a login row for every successful login', function (): void {
    $user = NoerdUser::factory()->create(['password' => bcrypt('password')]);

    Livewire::test('noerd::auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    Livewire::test('noerd::auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    expect(NoerdLogin::where('user_id', $user->id)->count())->toBe(2);

    $login = NoerdLogin::where('user_id', $user->id)->latest('id')->first();
    expect($login->impersonated_by_id)->toBeNull();
    expect($login->created_at)->not->toBeNull();
});

it('records nothing for a failed login', function (): void {
    $user = NoerdUser::factory()->create(['password' => bcrypt('password')]);

    Livewire::test('noerd::auth.login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);

    expect(NoerdLogin::count())->toBe(0);
});

it('stores the remember flag and the request metadata', function (): void {
    $user = NoerdUser::factory()->create(['password' => bcrypt('password')]);

    Livewire::test('noerd::auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->set('remember', true)
        ->call('login');

    $login = NoerdLogin::where('user_id', $user->id)->sole();

    expect($login->remember)->toBeTrue();
    expect($login->ip_address)->not->toBeNull();
});

it('marks an impersonated login with the impersonating user', function (): void {
    $admin = NoerdUser::factory()->create();
    $user = NoerdUser::factory()->create();

    session(['impersonating_from' => $admin->id]);

    (new RecordLogin())->handle(new Login('noerd', $user, false));

    $login = NoerdLogin::where('user_id', $user->id)->sole();

    expect($login->impersonated_by_id)->toBe($admin->id);
    expect($login->impersonatedBy->is($admin))->toBeTrue();
});

it('ignores logins on a foreign guard', function (): void {
    $user = NoerdUser::factory()->create();

    (new RecordLogin())->handle(new Login('web', $user, false));

    expect(NoerdLogin::count())->toBe(0);
});

it('removes the login rows when the user is deleted', function (): void {
    $user = NoerdUser::factory()->create();
    NoerdLogin::factory()->count(3)->create(['user_id' => $user->id]);

    $user->delete();

    expect(NoerdLogin::count())->toBe(0);
});

it('exposes the most recent login through the latestLogin relation', function (): void {
    $user = NoerdUser::factory()->create();

    NoerdLogin::create(['user_id' => $user->id, 'created_at' => now()->subDays(3)]);
    $latest = NoerdLogin::create(['user_id' => $user->id, 'created_at' => now()->subHour()]);

    expect($user->latestLogin()->first()->id)->toBe($latest->id);
});

it('eager-loads the last login so a list column resolves without a per-row query', function (): void {
    $user = NoerdUser::factory()->create();
    NoerdLogin::create(['user_id' => $user->id, 'created_at' => now()->subHour()]);

    $loaded = NoerdUser::with('latestLogin')->whereKey($user->id)->sole();

    expect($loaded->relationLoaded('latestLogin'))->toBeTrue();
    expect(data_get($loaded, 'latestLogin.created_at'))->not->toBeNull();
});

it('leaves the last login empty for a user who never logged in', function (): void {
    $user = NoerdUser::factory()->create();

    expect($user->latestLogin()->first())->toBeNull();
});
