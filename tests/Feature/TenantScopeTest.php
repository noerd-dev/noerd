<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Enums\Profile;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupCollection;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | TenantScope resolves the tenant through the SAME helper the creating hook
 | stamps from. Inside a tenant context (an authenticated noerd user) it must
 | fail CLOSED when no tenant resolves; outside one (console commands, queue
 | workers, unauthenticated requests) the query stays unscoped.
 */

function zzTenantScopeUser(Tenant $tenant): NoerdUser
{
    $user = NoerdUser::factory()->create(['super_admin' => false]);

    $user->tenants()->attach($tenant->id, ['profile_key' => Profile::User->value]);

    return $user;
}

it('scopes tenant-owned queries to the selected tenant', function (): void {
    config()->set('noerd.features.multi_tenant', true);

    $t1 = Tenant::factory()->create();
    $t2 = Tenant::factory()->create();
    $mine = SetupCollection::factory()->create(['tenant_id' => $t1->id]);
    $other = SetupCollection::factory()->create(['tenant_id' => $t2->id]);

    $this->actingAs(zzTenantScopeUser($t1));
    TenantHelper::setSelectedTenantId($t1->id);

    $ids = SetupCollection::query()->pluck('id');
    expect($ids)->toContain($mine->id)->not->toContain($other->id);
});

it('returns no rows for an authenticated user without a resolved tenant (fail closed)', function (): void {
    config()->set('noerd.features.multi_tenant', true);

    $t1 = Tenant::factory()->create();
    SetupCollection::factory()->create(['tenant_id' => $t1->id]);

    $this->actingAs(zzTenantScopeUser($t1));
    TenantHelper::clear();

    expect(SetupCollection::query()->count())->toBe(0);
});

it('leaves the query unscoped without an auth context (console, queue)', function (): void {
    config()->set('noerd.features.multi_tenant', true);

    $t1 = Tenant::factory()->create();
    $t2 = Tenant::factory()->create();
    $mine = SetupCollection::factory()->create(['tenant_id' => $t1->id]);
    $other = SetupCollection::factory()->create(['tenant_id' => $t2->id]);

    // Nobody is authenticated: the fail-closed branch is deliberately skipped so
    // a console command or queue worker still sees every tenant's rows.
    TenantHelper::clear();

    $ids = SetupCollection::query()->pluck('id');
    expect($ids)->toContain($mine->id)->toContain($other->id);
});
