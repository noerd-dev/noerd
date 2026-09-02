<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\NoerdUser;
use Noerd\Notifications\NoerdResetPassword;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The password reset flow runs entirely on noerd's OWN broker (never the
 | host's default one): the request form must not leak which addresses exist,
 | and a successful reset must invalidate the old credentials.
 */

/** The status region the component renders from session('status'). */
function zzForgotPasswordStatus(string $html): string
{
    preg_match('/<div[^>]*>\s*([^<]*reset link[^<]*)\s*<\/div>/i', $html, $matches);

    return mb_trim($matches[1] ?? '');
}

describe('forgot password', function (): void {
    it('sends the reset notification for a known email', function (): void {
        Notification::fake();

        $user = NoerdUser::factory()->create();

        $html = Livewire::test('noerd::auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors()
            ->html();

        Notification::assertSentTo($user, NoerdResetPassword::class);
        expect(zzForgotPasswordStatus($html))->not->toBe('');
    });

    it('answers an unknown email with the same status and sends nothing', function (): void {
        Notification::fake();

        $known = NoerdUser::factory()->create();

        $knownStatus = zzForgotPasswordStatus(
            Livewire::test('noerd::auth.forgot-password')
                ->set('email', $known->email)
                ->call('sendPasswordResetLink')
                ->html(),
        );

        $unknownStatus = zzForgotPasswordStatus(
            Livewire::test('noerd::auth.forgot-password')
                ->set('email', 'zz-nobody@example.test')
                ->call('sendPasswordResetLink')
                ->assertHasNoErrors()
                ->html(),
        );

        // Identical wording either way — the form must not disclose which
        // addresses have an account.
        expect($unknownStatus)->toBe($knownStatus)->not->toBe('');
        Notification::assertSentToTimes($known, NoerdResetPassword::class, 1);
    });
});

describe('reset password', function (): void {
    it('resets the password with a valid broker token and invalidates the old one', function (): void {
        $user = NoerdUser::factory()->create(['password' => Hash::make('old-password')]);
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        $oldRememberToken = $user->remember_token;

        $token = NoerdAuth::broker()->createToken($user);

        Livewire::test('noerd::auth.reset-password', ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'a-brand-new-password')
            ->set('password_confirmation', 'a-brand-new-password')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('noerd.login'));

        $user->refresh();

        expect(Hash::check('a-brand-new-password', $user->password))->toBeTrue()
            ->and(Hash::check('old-password', $user->password))->toBeFalse()
            ->and($user->remember_token)->not->toBe($oldRememberToken);

        expect(NoerdAuth::guard()->attempt(['email' => $user->email, 'password' => 'old-password']))->toBeFalse();
    });

    it('rejects an invalid token', function (): void {
        $user = NoerdUser::factory()->create(['password' => Hash::make('old-password')]);

        Livewire::test('noerd::auth.reset-password', ['token' => 'zz-not-a-token'])
            ->set('email', $user->email)
            ->set('password', 'a-brand-new-password')
            ->set('password_confirmation', 'a-brand-new-password')
            ->call('resetPassword')
            ->assertHasErrors(['email']);

        expect(Hash::check('old-password', $user->refresh()->password))->toBeTrue();
    });
});
