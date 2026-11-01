<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Support\Impersonation;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | An impersonation is bound to the tenant the administrator had selected when
 | starting it. The administrator administers tenants A and C; the target
 | belongs to A, B and C — B is a tenant the administrator has no say in.
 */

beforeEach(function (): void {
    $this->tenantA = Tenant::factory()->create(['name' => 'A']);
    $this->tenantB = Tenant::factory()->create(['name' => 'B']);
    $this->tenantC = Tenant::factory()->create(['name' => 'C']);

    $this->admin = NoerdUser::factory()->create();
    $this->admin->tenants()->attach($this->tenantA->id, ['profile_key' => Profile::Admin->value]);
    $this->admin->tenants()->attach($this->tenantC->id, ['profile_key' => Profile::Admin->value]);

    $this->target = NoerdUser::factory()->create();
    $this->target->tenants()->attach($this->tenantA->id, ['profile_key' => Profile::User->value]);
    $this->target->tenants()->attach($this->tenantB->id, ['profile_key' => Profile::Admin->value]);
    $this->target->tenants()->attach($this->tenantC->id, ['profile_key' => Profile::User->value]);
    $this->target->setting->update(['selected_tenant_id' => $this->tenantB->id]);

    $this->actingAs($this->admin);
});

it('allows an impersonation only from a selected tenant the administrator administers', function (): void {
    expect(Impersonation::allows($this->admin, $this->target, $this->tenantA->id))->toBeTrue()
        ->and(Impersonation::allows($this->admin, $this->target, $this->tenantC->id))->toBeTrue()
        ->and(Impersonation::allows($this->admin, $this->target, $this->tenantB->id))->toBeFalse();

    // Without a selected tenant there is nothing to bind the impersonation to.
    TenantHelper::clear();

    expect(Impersonation::allows($this->admin, $this->target))->toBeFalse();
});

it('refuses a target that is no member of the selected tenant, for a super admin as well', function (): void {
    $super = NoerdUser::factory()->create(['super_admin' => true]);
    $memberOfBOnly = NoerdUser::factory()->create();
    $memberOfBOnly->tenants()->attach($this->tenantB->id, ['profile_key' => Profile::User->value]);

    expect(Impersonation::allows($super, $memberOfBOnly, $this->tenantA->id))->toBeFalse()
        ->and(Impersonation::allows($super, $memberOfBOnly, $this->tenantB->id))->toBeTrue();
});

it('confines the impersonated session to the tenant it was started in', function (): void {
    TenantHelper::setSelectedTenantId($this->tenantA->id);

    Impersonation::start($this->target);

    $impersonated = NoerdAuth::user();

    expect($impersonated->id)->toBe($this->target->id)
        ->and(TenantHelper::getSelectedTenantId())->toBe($this->tenantA->id)
        ->and(Impersonation::pinnedTenantId())->toBe($this->tenantA->id)
        ->and($impersonated->accessibleTenants()->pluck('id')->all())->toBe([$this->tenantA->id])
        ->and($impersonated->canAccessTenant($this->tenantB->id))->toBeFalse()
        ->and($impersonated->canAccessTenant($this->tenantC->id))->toBeFalse()
        // The target administers B — none of it is reachable while impersonated.
        ->and($impersonated->adminTenants()->exists())->toBeFalse()
        ->and($impersonated->administeredTenants())->toBeEmpty();

    Livewire::test('noerd::layout.tenant-switcher')
        ->call('switchTenant', $this->tenantC->id)
        ->assertForbidden();
});

it('never opens the setup of another tenant the impersonated account administers', function (): void {
    $coAdmin = NoerdUser::factory()->create();
    $coAdmin->tenants()->attach($this->tenantA->id, ['profile_key' => Profile::Admin->value]);
    $coAdmin->tenants()->attach($this->tenantB->id, ['profile_key' => Profile::Admin->value]);
    $memberOfBOnly = NoerdUser::factory()->create();
    $memberOfBOnly->tenants()->attach($this->tenantB->id, ['profile_key' => Profile::User->value]);
    TenantHelper::setSelectedTenantId($this->tenantA->id);

    Impersonation::start($coAdmin);
    TenantHelper::setSelectedApp('SETUP');

    $visible = \Livewire\invade(Livewire::test('noerd::noerd-users-list')->instance())->visibleRowIds();

    expect(NoerdAuth::user()->isAdmin($this->tenantB->id))->toBeFalse()
        ->and(NoerdAuth::user()->administeredTenants()->pluck('id')->all())->toBe([$this->tenantA->id])
        ->and($visible)->toContain($coAdmin->id)
        ->not->toContain($memberOfBOnly->id);
});

it('keeps the person\'s persisted tenant selection untouched while impersonating', function (): void {
    TenantHelper::setSelectedTenantId($this->tenantA->id);

    Impersonation::start($this->target);

    expect($this->target->userSetting()->first()->selected_tenant_id)->toBe($this->tenantB->id);
});

it('returns the administrator to the tenant the impersonation was bound to', function (): void {
    TenantHelper::setSelectedTenantId($this->tenantC->id);

    Impersonation::start($this->target);
    Impersonation::stop();

    expect(NoerdAuth::id())->toBe($this->admin->id)
        ->and(Impersonation::active())->toBeFalse()
        ->and(Impersonation::pinnedTenantId())->toBeNull()
        ->and(TenantHelper::getSelectedTenantId())->toBe($this->tenantC->id)
        ->and(NoerdAuth::user()->accessibleTenants()->pluck('id')->sort()->values()->all())
        ->toBe([$this->tenantA->id, $this->tenantC->id]);
});

it('closes the profile area while impersonating', function (): void {
    TenantHelper::setSelectedTenantId($this->tenantA->id);

    Impersonation::start($this->target);

    $this->get(route('noerd.profile'))->assertForbidden();

    Livewire::test('noerd::profile.update-profile-information-form')
        ->set('email', 'taken-over@example.com')
        ->call('updateProfileInformation')
        ->assertForbidden();

    expect($this->target->fresh()->email)->not->toBe('taken-over@example.com');
});

it('explains instead of impersonating a user outside the selected tenant', function (): void {
    $memberOfBOnly = NoerdUser::factory()->create();
    $memberOfBOnly->tenants()->attach($this->tenantB->id, ['profile_key' => Profile::User->value]);
    $memberOfBOnly->tenants()->attach($this->tenantC->id, ['profile_key' => Profile::User->value]);
    TenantHelper::setSelectedTenantId($this->tenantA->id);
    TenantHelper::setSelectedApp('SETUP');

    Livewire::test('noerd::noerd-users-list')
        ->call('loginAsUser', $memberOfBOnly->id)
        ->assertNoRedirect()
        ->assertSee(__('This user does not belong to the selected tenant. Select one of their tenants to sign in as them.'));

    expect(NoerdAuth::id())->toBe($this->admin->id)
        ->and(Impersonation::active())->toBeFalse();
});
