<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Enums\Profile;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Support\Impersonation;
use Noerd\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->admin = NoerdUser::factory()->create();
    $this->admin->tenants()->attach($this->tenant->id, ['profile_key' => Profile::Admin->value]);
    TenantHelper::setSelectedTenantId($this->tenant->id);
});

it('lets a tenant admin impersonate a member of its tenant', function (): void {
    $member = NoerdUser::factory()->create();
    $member->tenants()->attach($this->tenant->id, ['profile_key' => Profile::User->value]);

    expect(Impersonation::allows($this->admin, $member))->toBeTrue();
});

it('confines a tenant admin to the members of its tenants', function (): void {
    $foreign = NoerdUser::factory()->create();
    $foreign->tenants()->attach(Tenant::factory()->create()->id);

    expect(Impersonation::allows($this->admin, $foreign))->toBeFalse();
});

it('binds a super admin to the members of the selected tenant as well', function (): void {
    $super = NoerdUser::factory()->create(['super_admin' => true]);
    $member = NoerdUser::factory()->create();
    $member->tenants()->attach($this->tenant->id, ['profile_key' => Profile::User->value]);

    expect(Impersonation::allows($super, $member))->toBeTrue()
        ->and(Impersonation::allows($super, NoerdUser::factory()->create()))->toBeFalse();
});

it('never impersonates a super admin', function (): void {
    $super = NoerdUser::factory()->create(['super_admin' => true]);
    $super->tenants()->attach($this->tenant->id);

    expect(Impersonation::allows($this->admin, $super))->toBeFalse();
});

it('refuses an impersonator who is no admin', function (): void {
    $user = NoerdUser::factory()->create();
    $user->tenants()->attach($this->tenant->id, ['profile_key' => Profile::User->value]);
    $member = NoerdUser::factory()->create();
    $member->tenants()->attach($this->tenant->id);

    expect(Impersonation::allows($user, $member))->toBeFalse();
});

it('never nests an impersonation', function (): void {
    $member = NoerdUser::factory()->create();
    $member->tenants()->attach($this->tenant->id);

    session(['impersonating_from' => $this->admin->id]);

    expect(Impersonation::allows($this->admin, $member))->toBeFalse();
});

it('signs in as the target and back again', function (): void {
    $member = NoerdUser::factory()->create();
    $member->tenants()->attach($this->tenant->id);
    $this->actingAs($this->admin);

    Impersonation::start($member);

    expect(NoerdAuth::id())->toBe($member->id)
        ->and(Impersonation::impersonatorId())->toBe($this->admin->id);

    Impersonation::stop();

    expect(NoerdAuth::id())->toBe($this->admin->id)
        ->and(Impersonation::active())->toBeFalse();
});

it('refuses to start a forbidden impersonation without touching the session', function (): void {
    $foreign = NoerdUser::factory()->create();
    $this->actingAs($this->admin);

    expect(fn() => Impersonation::start($foreign))->toThrow(HttpException::class);

    expect(NoerdAuth::id())->toBe($this->admin->id)
        ->and(Impersonation::active())->toBeFalse();
});
