<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Noerd\Enums\Profile;
use Noerd\Events\TenantMemberAdded;
use Noerd\Events\TenantMemberRemoved;
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
