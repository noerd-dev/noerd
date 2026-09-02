<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Helpers\TenantHelper;
use Noerd\Middleware\EnsureTenantMembership;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The selected tenant is validated wherever it is WRITTEN (login, tenant
 | switcher, setup middleware) but was never re-checked afterwards, so revoking
 | a user's access only took effect at their next login: until then the tenant
 | scope kept scoping every query to the revoked tenant. The noerd middleware
 | group now re-asserts membership per request.
 */

function zzRunMembershipMiddleware(): void
{
    app(EnsureTenantMembership::class)->handle(request(), fn($request) => response('ok'));
}

it('keeps a tenant the user still belongs to', function (): void {
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create();
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    $this->actingAs($user);

    zzRunMembershipMiddleware();

    expect(TenantHelper::getSelectedTenantId())->toBe($tenant->id);
});

it('falls back to a remaining tenant when access is revoked mid-session', function (): void {
    $revoked = Tenant::factory()->create();
    $remaining = Tenant::factory()->create();
    $user = NoerdUser::factory()->create();
    $user->tenants()->attach([$revoked->id, $remaining->id]);
    TenantHelper::setSelectedTenantId($revoked->id);
    TenantHelper::setSelectedApp('SETUP');
    $this->actingAs($user);

    // An admin removes the user from the tenant they are currently working in.
    $user->tenants()->detach($revoked->id);

    zzRunMembershipMiddleware();

    expect(TenantHelper::getSelectedTenantId())->toBe($remaining->id)
        // The app selection belonged to the revoked tenant's assignment.
        ->and(TenantHelper::getSelectedApp())->toBeNull();
});

it('clears the tenant entirely when the last membership is revoked', function (): void {
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create();
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    $this->actingAs($user);

    $user->tenants()->detach($tenant->id);

    zzRunMembershipMiddleware();

    expect(TenantHelper::getSelectedTenantId())->toBeNull();
});

it('keeps a tenant a super admin works in without a membership', function (): void {
    $foreign = Tenant::factory()->create();
    $superAdmin = NoerdUser::factory()->create(['super_admin' => true]);
    TenantHelper::setSelectedTenantId($foreign->id);
    $this->actingAs($superAdmin);

    zzRunMembershipMiddleware();

    expect(TenantHelper::getSelectedTenantId())->toBe($foreign->id);
});

it('falls back to another tenant for a super admin when the selected one is deleted', function (): void {
    $remaining = Tenant::factory()->create();
    $deleted = Tenant::factory()->create();
    $superAdmin = NoerdUser::factory()->create(['super_admin' => true]);
    TenantHelper::setSelectedTenantId($deleted->id);
    $this->actingAs($superAdmin);

    $deleted->delete();

    zzRunMembershipMiddleware();

    expect(TenantHelper::getSelectedTenantId())->toBe($remaining->id);
});
