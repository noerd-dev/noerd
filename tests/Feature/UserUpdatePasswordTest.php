<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The password tab of the user editor: it names the account it changes and
 | generates a strong password on request — never on its own.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->member = NoerdUser::factory()->create([
        'name' => 'Zz Target Person',
        'email' => 'zz-target@example.test',
        'password' => bcrypt('unchanged-secret'),
    ]);
    $this->member->tenants()->attach($this->tenant->id, ['profile_key' => Profile::User->value]);

    TenantHelper::setSelectedTenantId($this->tenant->id);
    TenantHelper::setSelectedApp('SETUP');
});

function actingAsPasswordAdmin(Tenant $tenant, bool $superAdmin): NoerdUser
{
    $admin = NoerdUser::factory()->create(['super_admin' => $superAdmin]);
    $admin->tenants()->attach($tenant->id, ['profile_key' => Profile::Admin->value]);
    test()->actingAs($admin);

    return $admin;
}

it('names the account whose password is set', function (): void {
    actingAsPasswordAdmin($this->tenant, superAdmin: true);

    Livewire::test('noerd::user-update-password', ['userId' => $this->member->id])
        ->assertSee('Zz Target Person')
        ->assertSee('zz-target@example.test');
});

it('suggests no password until one is generated', function (): void {
    actingAsPasswordAdmin($this->tenant, superAdmin: true);

    Livewire::test('noerd::user-update-password', ['userId' => $this->member->id])
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');
});

it('generates a strong password that can be saved', function (): void {
    actingAsPasswordAdmin($this->tenant, superAdmin: true);

    $component = Livewire::test('noerd::user-update-password', ['userId' => $this->member->id])
        ->call('generatePassword');

    $generated = $component->get('password');

    expect($generated)->toHaveLength(16)
        ->and($component->get('password_confirmation'))->toBe($generated);

    $component->call('generatePassword');
    expect($component->get('password'))->not->toBe($generated);

    $generated = $component->get('password');
    $component->call('updatePassword')
        ->assertHasNoErrors()
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');

    expect(Hash::check($generated, $this->member->fresh()->password))->toBeTrue();
});

it('neither generates a password nor names the account for a tenant admin', function (): void {
    actingAsPasswordAdmin($this->tenant, superAdmin: false);

    Livewire::test('noerd::user-update-password', ['userId' => $this->member->id])
        ->assertDontSee('zz-target@example.test')
        ->call('generatePassword')
        ->assertForbidden();

    expect(Hash::check('unchanged-secret', $this->member->fresh()->password))->toBeTrue();
});
