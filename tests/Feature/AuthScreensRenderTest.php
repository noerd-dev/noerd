<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('renders the login screen inside the shared auth shell', function (): void {
    $this->get(route('noerd.login'))
        ->assertOk()
        ->assertSee(__('Log in to your account'))
        ->assertSee(__('Enter your email and password below to log in'));
});

it('renders the forgot-password screen inside the shared auth shell', function (): void {
    $this->get(route('noerd.password.request'))
        ->assertOk()
        ->assertSee(__('Forgot password'))
        ->assertSee(__('Back to login'));
});

it('renders the reset-password screen with the email taken from the query string', function (): void {
    $this->get(route('noerd.password.reset', ['token' => 'abc', 'email' => 'jane@example.com']))
        ->assertOk()
        ->assertSee(__('Enter your new password below'))
        ->assertSee('jane@example.com');
});
