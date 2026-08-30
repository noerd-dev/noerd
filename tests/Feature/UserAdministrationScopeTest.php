<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | A tenant admin administers THEIR tenants — never the installation. $modelId
 | is URL-bound and the header filters round-trip through the session, so every
 | one of these paths is client-steerable and must re-check its target. Each
 | case was a working exploit.
 */

function zzTenantAdmin(Tenant $tenant): NoerdUser
{
    $user = NoerdUser::factory()->create(['super_admin' => false]);
    $user->tenants()->attach($tenant->id, ['profile_key' => Profile::Admin->value]);

    return $user;
}

beforeEach(function (): void {
    $this->tenantA = Tenant::factory()->create(['name' => 'A']);
    $this->admin = zzTenantAdmin($this->tenantA);
    TenantHelper::setSelectedTenantId($this->tenantA->id);
    TenantHelper::setSelectedApp('SETUP');
    $this->actingAs($this->admin);
});

it('never deletes an account that belongs to no tenant of this admin', function (): void {
    $orphanSuperAdmin = NoerdUser::factory()->create(['super_admin' => true]);

    try {
        Livewire::test('noerd::noerd-user-detail', ['modelId' => $orphanSuperAdmin->id])->call('delete');
    } catch (Throwable) {
        // A 403 may surface as an exception or a response; survival is the assertion.
    }

    expect(NoerdUser::find($orphanSuperAdmin->id))->not->toBeNull();
});

it('never lists the users of a foreign tenant through the header filter', function (): void {
    $tenantB = Tenant::factory()->create(['name' => 'B']);
    $foreign = NoerdUser::factory()->create();
    $foreign->tenants()->attach($tenantB->id);

    // storeActiveListFilters() persists client input into the session, which
    // rendering() reloads — emulated directly, as one crafted request does.
    session(['listFilters' => ['tenant_id' => $tenantB->id]]);

    $visible = Livewire::test('noerd::noerd-users-list')->instance()->visibleRowIds();

    expect($visible)->not->toContain($foreign->id);
});

it('never captures a super admin through the existing-email branch', function (): void {
    $super = NoerdUser::factory()->create(['super_admin' => true, 'email' => 'super@victim.tld']);

    $component = Livewire::test('noerd::noerd-user-detail');
    $possible = $component->get('possibleTenants');
    foreach ($possible as $tenantId => $row) {
        $possible[$tenantId]['hasAccess'] = true;
    }

    try {
        $component->set('detailData.name', 'x')
            ->set('detailData.email', 'super@victim.tld')
            ->set('possibleTenants', $possible)
            ->call('store');
    } catch (Throwable) {
    }

    expect($super->fresh()->tenants->contains($this->tenantA->id))->toBeFalse();
});

it('never impersonates a super admin', function (): void {
    $super = NoerdUser::factory()->create(['super_admin' => true]);
    $super->tenants()->attach($this->tenantA->id);

    try {
        Livewire::test('noerd::noerd-users-list')->call('loginAsUser', $super->id);
    } catch (Throwable) {
    }

    expect(NoerdAuth::id())->toBe($this->admin->id);
});

it('still administers a user of its own tenant', function (): void {
    $member = NoerdUser::factory()->create();
    $member->tenants()->attach($this->tenantA->id, ['profile_key' => Profile::User->value]);

    Livewire::test('noerd::noerd-user-detail', ['modelId' => $member->id])
        ->set('detailData.name', 'Renamed')
        ->call('store')
        ->assertHasNoErrors();

    expect($member->fresh()->name)->toBe('Renamed');

    Livewire::test('noerd::noerd-users-list')->call('loginAsUser', $member->id);
    expect(NoerdAuth::id())->toBe($member->id);
});
