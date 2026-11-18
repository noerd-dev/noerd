<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Noerd\Enums\Profile;
use Noerd\Events\TenantMemberAdded;
use Noerd\Events\TenantMemberRemoved;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Services\ProfileRegistry;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->user = NoerdUser::factory()->create();
});

it('adds a member once and announces it once', function (): void {
    Event::fake([TenantMemberAdded::class]);

    expect($this->tenant->addMember($this->user, Profile::User->value))->toBeTrue()
        ->and($this->tenant->addMember($this->user, Profile::Admin->value))->toBeFalse()
        ->and($this->user->tenants()->first()->pivot->profile_key)->toBe(Profile::User->value);

    Event::assertDispatchedTimes(TenantMemberAdded::class, 1);
    Event::assertDispatched(
        TenantMemberAdded::class,
        fn(TenantMemberAdded $event): bool => $event->tenantId === $this->tenant->id && $event->userId === $this->user->id,
    );
});

it('removes a member once, announces it once and keeps the account', function (): void {
    $this->tenant->addMember($this->user, Profile::User->value);

    Event::fake([TenantMemberRemoved::class]);

    expect($this->tenant->removeMember($this->user))->toBeTrue()
        ->and($this->tenant->removeMember($this->user))->toBeFalse()
        ->and(NoerdUser::find($this->user->id))->not->toBeNull();

    Event::assertDispatchedTimes(TenantMemberRemoved::class, 1);
});

it('changes the profile of a member only', function (): void {
    expect($this->tenant->setMemberProfile($this->user, Profile::Admin->value))->toBeFalse();

    $this->tenant->addMember($this->user, Profile::User->value);

    expect($this->tenant->setMemberProfile($this->user, Profile::ReadOnly->value))->toBeTrue()
        ->and($this->user->fresh()->tenants->first()->pivot->profile_key)->toBe(Profile::ReadOnly->value);
});

it('accepts every registered profile and no profile, but refuses an unknown one', function (): void {
    app(ProfileRegistry::class)->register('ZZ_PROFILE', 'Zz Profile');

    expect($this->tenant->addMember($this->user, 'ZZ_PROFILE'))->toBeTrue()
        ->and($this->tenant->setMemberProfile($this->user, null))->toBeTrue();

    expect(fn() => $this->tenant->setMemberProfile($this->user, 'NOT_A_PROFILE'))
        ->toThrow(InvalidArgumentException::class);
});

it('knows the only administrator of a tenant', function (): void {
    $other = NoerdUser::factory()->create();
    $this->tenant->addMember($this->user, Profile::Admin->value);
    $this->tenant->addMember($other, Profile::User->value);

    expect($this->tenant->isLastAdmin($this->user))->toBeTrue()
        ->and($this->tenant->isLastAdmin($other))->toBeFalse()
        ->and($this->tenant->wouldLoseLastAdmin($this->user, Profile::User->value))->toBeTrue()
        ->and($this->tenant->wouldLoseLastAdmin($this->user))->toBeTrue()
        ->and($this->tenant->wouldLoseLastAdmin($this->user, Profile::Admin->value))->toBeFalse();

    $this->tenant->setMemberProfile($other, Profile::Admin->value);

    expect($this->tenant->isLastAdmin($this->user))->toBeFalse()
        ->and($this->tenant->wouldLoseLastAdmin($this->user, Profile::User->value))->toBeFalse();
});

it('forgets the tenant as the persisted selection when the membership ends', function (): void {
    $other = Tenant::factory()->create();
    $colleague = NoerdUser::factory()->create();
    $this->tenant->addMember($this->user, Profile::User->value);
    $this->tenant->addMember($colleague, Profile::User->value);
    $other->addMember($this->user, Profile::User->value);
    $this->user->selected_tenant_id = $this->tenant->id;
    $colleague->selected_tenant_id = $this->tenant->id;

    $this->tenant->removeMember($this->user);

    expect(NoerdUser::find($this->user->id)->selected_tenant_id)->toBeNull()
        ->and($this->user->selected_tenant_id)->toBeNull()
        ->and(NoerdUser::find($colleague->id)->selected_tenant_id)->toBe($this->tenant->id);
});

it('keeps a selection of another tenant when the membership ends', function (): void {
    $other = Tenant::factory()->create();
    $this->tenant->addMember($this->user, Profile::User->value);
    $other->addMember($this->user, Profile::User->value);
    $this->user->selected_tenant_id = $other->id;

    $this->tenant->removeMember($this->user);

    expect(NoerdUser::find($this->user->id)->selected_tenant_id)->toBe($other->id);
});

it('keeps the selection of a super admin, who may work in every tenant', function (): void {
    $admin = NoerdUser::factory()->superAdmin()->create();
    $this->tenant->addMember($admin, Profile::Admin->value);
    $admin->selected_tenant_id = $this->tenant->id;

    $this->tenant->removeMember($admin);

    expect(NoerdUser::find($admin->id)->selected_tenant_id)->toBe($this->tenant->id);
});

it('forgets the session selection of the signed-in account, which then falls back to a tenant it still belongs to', function (): void {
    $other = Tenant::factory()->create();
    $this->tenant->addMember($this->user, Profile::User->value);
    $other->addMember($this->user, Profile::User->value);
    $this->user->selected_tenant_id = $this->tenant->id;

    $this->actingAs($this->user, NoerdAuth::guardName());
    TenantHelper::setSelectedTenantId($this->tenant->id);
    TenantHelper::setSelectedApp('ZZAPP');

    $this->tenant->removeMember($this->user);

    expect(TenantHelper::getSelectedTenantId())->toBeNull()
        ->and(TenantHelper::getSelectedApp())->toBeNull()
        ->and($this->user->userSetting()->value('selected_tenant_id'))->toBeNull();

    event(new Authenticated(NoerdAuth::guardName(), $this->user));

    expect(TenantHelper::getSelectedTenantId())->toBe($other->id);
});
