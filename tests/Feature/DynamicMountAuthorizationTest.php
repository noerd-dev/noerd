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
 | Components reachable through the DYNAMIC mount seams — the client-dispatchable
 | `noerdModal` event and GET /noerd/component-page/{component} — receive
 | attacker-chosen mount arguments. Livewire assigns those to matching public
 | properties INCLUDING #[Locked] ones (Locked only vetoes the update path), so
 | every component that acts on such an argument must authorize the target
 | itself. Each test below is a previously working exploit.
 */

beforeEach(function (): void {
    $tenant = Tenant::factory()->create();
    $attacker = NoerdUser::factory()->create(['super_admin' => false]);
    $attacker->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('SETUP');
    $this->actingAs($attacker);
    $this->attacker = $attacker;

    expect($attacker->isAdmin())->toBeFalse();
});

it('refuses to set a foreign account password from a crafted mount', function (): void {
    $victim = NoerdUser::factory()->create([
        'super_admin' => true,
        'password' => Hash::make('original-secret'),
    ]);

    try {
        Livewire::test('noerd::user-update-password', ['userId' => $victim->id])
            ->set('password', 'attacker-choice')
            ->set('password_confirmation', 'attacker-choice')
            ->call('updatePassword');
    } catch (Throwable) {
        // Either shape is fine — the password surviving is the assertion.
    }

    expect(Hash::check('original-secret', $victim->refresh()->password))->toBeTrue();
});

it('does not expose the password form through the generic component page', function (): void {
    $victim = NoerdUser::factory()->create();

    $this->get('/noerd/component-page/noerd::user-update-password?userId=' . $victim->id)
        ->assertForbidden();
});

it('never invokes a non-relation method from a relation box tile', function (): void {
    $victim = Tenant::factory()->create(['name' => 'Foreign Tenant']);

    // 'delete' exists on every model — the tile resolver used to call whatever
    // method_exists() accepted, so this deleted the record outright.
    try {
        Livewire::test('noerd::relation-box', [
            'modelClass' => Tenant::class,
            'modelId' => $victim->id,
            'relations' => [['relation' => 'delete', 'label' => 'x', 'heroicon' => 'x']],
        ]);
    } catch (Throwable) {
        // A render failure is acceptable; the record surviving is what matters.
    }

    expect(Tenant::find($victim->id))->not->toBeNull();
});

it('still counts a genuine relation in the relation box', function (): void {
    $tenant = Tenant::factory()->create();
    NoerdUser::factory()->create()->tenants()->attach($tenant->id);

    $component = Livewire::test('noerd::relation-box', [
        'modelClass' => Tenant::class,
        'modelId' => $tenant->id,
        'relations' => [['relation' => 'users', 'label' => 'Users', 'heroicon' => 'users']],
    ]);

    expect($component->get('resolvedRelations')[0]['count'])->toBe(1);
});

it('refuses to create a tenant from the inner component for a non-admin', function (): void {
    try {
        Livewire::test('noerd::create-new-tenant')
            ->set('name', 'escalation')
            ->call('createTenant');
    } catch (Throwable) {
        // Either shape is fine — no tenant and no admin grant is the assertion.
    }

    expect(Tenant::where('name', 'escalation')->exists())->toBeFalse()
        ->and(NoerdUser::find($this->attacker->id)->isAdmin())->toBeFalse();
});

it('keeps the admin path working for an admin of the edited user', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = NoerdUser::factory()->create();
    $admin->tenants()->attach($tenant->id, ['profile_key' => Profile::Admin->value]);

    $member = NoerdUser::factory()->create(['password' => Hash::make('old')]);
    $member->tenants()->attach($tenant->id, ['profile_key' => Profile::Admin->value]);

    TenantHelper::setSelectedTenantId($tenant->id);
    $this->actingAs($admin);

    Livewire::test('noerd::user-update-password', ['userId' => $member->id])
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password', $member->refresh()->password))->toBeTrue();
});
