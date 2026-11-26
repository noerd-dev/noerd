<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Services\ComponentSlotsRegistry;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('login slot', function (): void {
    it('renders the components registered for the login page above the form', function (): void {
        app(ComponentSlotsRegistry::class)->register(ComponentSlotsRegistry::AUTH_LOGIN, 'noerd-test::login-slot-test');

        Livewire::test('noerd::auth.login')
            ->assertSee('Sign in with Zz-Provider')
            ->assertSeeHtml('wire:submit="login"')
            ->assertSee(__('or'));
    });

    it('renders no separator without registered components', function (): void {
        Livewire::test('noerd::auth.login')
            ->assertSeeHtml('wire:submit="login"')
            ->assertDontSee('Sign in with Zz-Provider');
    });
});

describe('password login switched off', function (): void {
    beforeEach(function (): void {
        config(['noerd.features.password_login' => false]);
    });

    it('shows only the login slot', function (): void {
        app(ComponentSlotsRegistry::class)->register(ComponentSlotsRegistry::AUTH_LOGIN, 'noerd-test::login-slot-test');

        Livewire::test('noerd::auth.login')
            ->assertSee('Sign in with Zz-Provider')
            ->assertDontSeeHtml('wire:submit="login"');
    });

    it('refuses a password login', function (): void {
        $user = NoerdUser::factory()->create(['password' => bcrypt('password')]);

        Livewire::test('noerd::auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertStatus(404);

        expect(auth('noerd')->check())->toBeFalse();
    });

    it('refuses the password reset screens', function (): void {
        $this->get(route('noerd.password.request'))->assertNotFound();
        $this->get(route('noerd.password.reset', ['token' => 'abc']))->assertNotFound();
    });

    it('refuses changing the own password', function (): void {
        $user = NoerdUser::factory()->create(['password' => bcrypt('password')]);
        $this->actingAs($user, 'noerd');

        Livewire::test('noerd::profile.update-password-form')
            ->set('current_password', 'password')
            ->set('password', 'N3w-Secret-Passw0rd!')
            ->set('password_confirmation', 'N3w-Secret-Passw0rd!')
            ->call('updatePassword')
            ->assertStatus(404);
    });

    it('lets nobody set another account\'s password', function (): void {
        $admin = NoerdUser::factory()->create();
        $admin->forceFill(['super_admin' => true])->save();
        $target = NoerdUser::factory()->create();
        $this->actingAs($admin, 'noerd');

        Livewire::test('noerd::user-update-password', ['userId' => $target->id])
            ->set('password', 'N3w-Secret-Passw0rd!')
            ->set('password_confirmation', 'N3w-Secret-Passw0rd!')
            ->call('updatePassword')
            ->assertForbidden();
    });
});

describe('remember me cookie', function (): void {
    it('signs the person in while the password login is on', function (): void {
        $user = NoerdUser::factory()->create(['remember_token' => 'zz-remember-token']);

        expect(auth('noerd')->getProvider()->retrieveByToken($user->id, 'zz-remember-token')?->getAuthIdentifier())->toBe($user->id);
    });

    it('signs nobody in while the password login is off', function (): void {
        config(['noerd.features.password_login' => false]);
        $user = NoerdUser::factory()->create(['remember_token' => 'zz-remember-token']);

        expect(auth('noerd')->getProvider()->retrieveByToken($user->id, 'zz-remember-token'))->toBeNull();
    });
});
