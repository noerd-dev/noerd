<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Models\NoerdUser;
use Noerd\Services\ProfileRegistry;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The ProfileRegistry: built-in profiles plus module-registered ones drive
 | the profile pickers; labels resolve translated and lazily.
 */

it('offers the built-in profiles by default', function (): void {
    $options = app(ProfileRegistry::class)->options();

    expect(array_keys($options))->toBe([
        Profile::Admin->value,
        Profile::User->value,
        Profile::ReadOnly->value,
    ])
        ->and($options[Profile::ReadOnly->value])->toBe(Profile::ReadOnly->label());
});

it('appends registered profiles and resolves their labels', function (): void {
    $registry = app(ProfileRegistry::class);
    $registry->register('ZZ_MODULE_PROFILE', fn(): string => 'Zz Module Profile');

    expect(app(ProfileRegistry::class))->toBe($registry)
        ->and(array_key_last($registry->options()))->toBe('ZZ_MODULE_PROFILE')
        ->and($registry->label('ZZ_MODULE_PROFILE'))->toBe('Zz Module Profile')
        ->and($registry->label('UNKNOWN'))->toBeNull();
});

it('renders registered profiles in the user detail picker', function (): void {
    app(ProfileRegistry::class)->register('ZZ_MODULE_PROFILE', fn(): string => 'Zz Module Profile');

    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    // A synthetic detail whose profile select is fed by the registry — the
    // shipped user editor's layout is configuration and must not be asserted.
    Livewire::test('noerd-test::profile-picker-test')
        ->assertOk()
        ->assertSee('Zz Module Profile')
        ->assertSee(Profile::ReadOnly->label());
});

it('labels the profile badge of a registered profile', function (): void {
    app(ProfileRegistry::class)->register('ZZ_MODULE_PROFILE', fn(): string => 'Zz Module Profile');

    $user = createNoerdUserWithProfile(null);
    $user->tenants()->updateExistingPivot(
        Noerd\Helpers\TenantHelper::getSelectedTenantId(),
        ['profile_key' => 'ZZ_MODULE_PROFILE'],
    );

    expect($user->fresh()->profile_for_tenant['badge'])->toBe('Zz Module Profile');
});
