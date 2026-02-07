<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\User;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('authenticates a user and redirects without navigate', function (): void {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('/dashboard');
});

it('fails login with invalid credentials', function (): void {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    Livewire::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);
});

it('requires email and password', function (): void {
    Livewire::test('auth.login')
        ->call('login')
        ->assertHasErrors(['email', 'password']);
});

it('rate limits login attempts', function (): void {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $component = Livewire::test('auth.login')
        ->set('email', $user->email);

    for ($i = 0; $i < 5; $i++) {
        $component->set('password', 'wrong-password')
            ->call('login');
    }

    $component->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);
});
