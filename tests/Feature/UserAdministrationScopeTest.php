<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Support\ComponentAccessGuard;
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

it('lists every account in the installation for a super admin', function (): void {
    $tenantB = Tenant::factory()->create(['name' => 'B']);
    $foreign = NoerdUser::factory()->create();
    $foreign->tenants()->attach($tenantB->id);
    $orphan = NoerdUser::factory()->create();

    $super = NoerdUser::factory()->create(['super_admin' => true]);
    $super->tenants()->attach($this->tenantA->id, ['profile_key' => Profile::Admin->value]);
    $this->actingAs($super);

    $visible = Livewire::test('noerd::noerd-users-list')->instance()->visibleRowIds();

    expect($visible)->toContain($foreign->id)
        ->toContain($orphan->id)
        ->toContain($this->admin->id);
});

it('narrows the super admin list to the tenant chosen in the header filter', function (): void {
    $tenantB = Tenant::factory()->create(['name' => 'B']);
    $foreign = NoerdUser::factory()->create();
    $foreign->tenants()->attach($tenantB->id);

    $super = NoerdUser::factory()->create(['super_admin' => true]);
    $super->tenants()->attach($this->tenantA->id, ['profile_key' => Profile::Admin->value]);
    $this->actingAs($super);

    session(['listFilters' => ['tenant_id' => $tenantB->id]]);

    $visible = Livewire::test('noerd::noerd-users-list')->instance()->visibleRowIds();

    expect($visible)->toContain($foreign->id)
        ->not->toContain($this->admin->id);
});

it('refuses to delete the administrator\'s own account', function (): void {
    // $modelId is URL-bound: an admin may reach their own record on the user
    // page, but must never remove their own access from it.
    Livewire::test('noerd::noerd-user-page', ['modelId' => $this->admin->id])
        ->call('delete')
        ->assertStatus(403);

    expect(NoerdUser::find($this->admin->id))->not->toBeNull();
});

it('scopes admin rights to the tenant of the current request', function (): void {
    $administered = Tenant::factory()->create();
    $memberOnly = Tenant::factory()->create();

    $user = zzTenantAdmin($administered);
    // Same user is a plain member of a second tenant.
    $user->tenants()->attach($memberOnly->id, ['profile_key' => Profile::User->value]);
    $this->actingAs($user);

    TenantHelper::setSelectedTenantId($administered->id);
    expect($user->fresh()->isAdmin())->toBeTrue();

    // Switching to the tenant they only belong to must NOT carry admin rights —
    // that is what made the setup area of any co-tenant reachable.
    TenantHelper::setSelectedTenantId($memberOnly->id);
    expect($user->fresh()->isAdmin())->toBeFalse()
        ->and(ComponentAccessGuard::allows('noerd::tenants-list'))->toBeFalse();

    // The cross-tenant variant stays available for console/reporting contexts.
    expect($user->fresh()->isAdminOfAnyTenant())->toBeTrue();
});

it('keeps a super admin unrestricted across tenants', function (): void {
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create(['super_admin' => true]);
    $user->tenants()->attach($tenant->id, ['profile_key' => Profile::User->value]);
    $this->actingAs($user);

    TenantHelper::setSelectedTenantId(Tenant::factory()->create()->id);

    expect($user->fresh()->isAdmin())->toBeTrue();
});
