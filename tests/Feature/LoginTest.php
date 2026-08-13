<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('authenticates a user and redirects to the app picker without navigate', function (): void {
    $user = NoerdUser::factory()->create([
        'password' => bcrypt('password'),
    ]);

    Livewire::test('noerd::auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('/noerd-apps');
});

it('redirects to the originally intended URL after login', function (): void {
    $user = NoerdUser::factory()->create([
        'password' => bcrypt('password'),
    ]);

    session()->put('url.intended', '/restaurant');

    Livewire::test('noerd::auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('/restaurant');
});

it('fails login with invalid credentials', function (): void {
    $user = NoerdUser::factory()->create([
        'password' => bcrypt('password'),
    ]);

    Livewire::test('noerd::auth.login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);
});

it('requires email and password', function (): void {
    Livewire::test('noerd::auth.login')
        ->call('login')
        ->assertHasErrors(['email', 'password']);
});

it('rate limits login attempts', function (): void {
    $user = NoerdUser::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $component = Livewire::test('noerd::auth.login')
        ->set('email', $user->email);

    for ($i = 0; $i < 5; $i++) {
        $component->set('password', 'wrong-password')
            ->call('login');
    }

    $component->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);
});
