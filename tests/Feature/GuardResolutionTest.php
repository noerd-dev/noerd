<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | noerd authenticates against its OWN guard, and `noerd.auth.set_as_default`
 | is false by default — a host application keeps the default guard. Components
 | must therefore resolve the user through NoerdAuth, never through Auth::user()
 | / auth()->user().
 |
 | The rest of the suite pins auth.defaults.guard to 'noerd' (see TestCase), so
 | it CANNOT observe this distinction. These tests deliberately point the
 | default guard elsewhere, which is the only configuration in which the bug is
 | visible.
 */

beforeEach(function (): void {
    // A host that owns the default guard: it authenticates a different provider
    // and has nobody logged in.
    config([
        'auth.defaults.guard' => 'web',
        'auth.guards.web' => ['driver' => 'session', 'provider' => 'host_users'],
        'auth.providers.host_users' => ['driver' => 'eloquent', 'model' => NoerdUser::class],
    ]);

    $tenant = Tenant::factory()->create();
    $this->user = NoerdUser::factory()->create(['name' => 'Guarded User']);
    $this->user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('SETUP');

    // A DIFFERENT account is logged in on the host's default guard. This is the
    // decisive setup: reading through Auth::user() silently returns the host's
    // user, so any component that still does resolves the wrong identity
    // instead of merely failing.
    $this->hostUser = NoerdUser::factory()->create(['name' => 'Host User']);

    $this->actingAs($this->user, NoerdAuth::guardName());
    Auth::guard('web')->setUser($this->hostUser);
    app('auth')->shouldUse('web');
});

it('separates the two guards in this scenario', function (): void {
    expect(Auth::user()?->id)->toBe($this->hostUser->id)
        ->and(NoerdAuth::user()?->id)->toBe($this->user->id);
});

it('renders the profile screen against the noerd guard', function (): void {
    Livewire::test('noerd::profile.update-profile-information-form')
        ->assertOk()
        ->assertSet('name', 'Guarded User')
        ->assertSet('email', $this->user->email);
});

it('updates the profile of the noerd user', function (): void {
    Livewire::test('noerd::profile.update-profile-information-form')
        ->set('name', 'Renamed')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($this->user->fresh()->name)->toBe('Renamed')
        ->and($this->hostUser->fresh()->name)->toBe('Host User');
});

it('verifies the current password against the noerd guard', function (): void {
    $this->user->forceFill(['password' => Hash::make('known-secret')])->save();

    Livewire::test('noerd::profile.update-password-form')
        ->set('current_password', 'known-secret')
        ->set('password', 'a-new-password')
        ->set('password_confirmation', 'a-new-password')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('a-new-password', $this->user->fresh()->password))->toBeTrue();
    expect(Hash::check('a-new-password', $this->hostUser->fresh()->password))->toBeFalse();
});

it('rejects a wrong current password', function (): void {
    $this->user->forceFill(['password' => Hash::make('known-secret')])->save();

    Livewire::test('noerd::profile.update-password-form')
        ->set('current_password', 'not-the-password')
        ->set('password', 'a-new-password')
        ->set('password_confirmation', 'a-new-password')
        ->call('updatePassword')
        ->assertHasErrors('current_password');

    expect(Hash::check('known-secret', $this->user->fresh()->password))->toBeTrue();
});

it('reads the selected tenant of the noerd user in the language form', function (): void {
    Livewire::test('noerd::profile.update-language-form')->assertOk();
});
