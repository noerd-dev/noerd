<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\TenantHelper;
use Noerd\Middleware\SetupMiddleware;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | A super admin administers the INSTALLATION: it may work in every tenant
 | without a membership row, and the role is visible wherever the tenant
 | profile is shown. A tenant admin keeps the membership semantics.
 */

it('resolves every tenant of the installation as accessible for a super admin', function (): void {
    $member = Tenant::factory()->create();
    $foreign = Tenant::factory()->create();
    $superAdmin = NoerdUser::factory()->create(['super_admin' => true]);
    $superAdmin->tenants()->attach($member->id);

    expect($superAdmin->accessibleTenants()->pluck('id')->all())->toBe([$member->id, $foreign->id])
        ->and($superAdmin->canAccessTenant($foreign->id))->toBeTrue()
        ->and($superAdmin->canAccessTenant($foreign->id + 999))->toBeFalse()
        ->and($superAdmin->administeredTenants()->pluck('id')->all())->toBe([$member->id, $foreign->id]);
});

it('resolves only the membership tenants as accessible for a regular user', function (): void {
    $member = Tenant::factory()->create();
    $foreign = Tenant::factory()->create();
    $user = NoerdUser::factory()->create(['super_admin' => false]);
    $user->tenants()->attach($member->id, ['profile_key' => Profile::Admin->value]);

    expect($user->accessibleTenants()->pluck('id')->all())->toBe([$member->id])
        ->and($user->canAccessTenant($foreign->id))->toBeFalse()
        ->and($user->administeredTenants()->pluck('id')->all())->toBe([$member->id]);
});

it('labels a super admin in the profile column and keeps the tenant profile as text', function (): void {
    $tenant = Tenant::factory()->create();
    TenantHelper::setSelectedTenantId($tenant->id);

    $withProfile = NoerdUser::factory()->create(['super_admin' => true]);
    $withProfile->tenants()->attach($tenant->id, ['profile_key' => Profile::Admin->value]);

    $withoutMembership = NoerdUser::factory()->create(['super_admin' => true]);

    $tenantAdmin = NoerdUser::factory()->create(['super_admin' => false]);
    $tenantAdmin->tenants()->attach($tenant->id, ['profile_key' => Profile::Admin->value]);

    expect($withProfile->profile_for_tenant)->toBe(['badge' => __('Super Admin'), 'text' => Profile::Admin->label()])
        ->and($withoutMembership->profile_for_tenant)->toBe(['badge' => __('Super Admin'), 'text' => ''])
        ->and($tenantAdmin->profile_for_tenant)->toBe(['badge' => Profile::Admin->label(), 'text' => '']);
});

it('renders the super admin badge in the users list', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = NoerdUser::factory()->create(['super_admin' => true, 'name' => 'Zz Installation Admin']);
    $superAdmin->tenants()->attach($tenant->id, ['profile_key' => Profile::Admin->value]);
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('SETUP');
    $this->actingAs($superAdmin);

    Livewire::test('noerd::noerd-users-list')
        ->assertSee('Zz Installation Admin')
        ->assertSee(__('Super Admin'));
});

it('seeds the setup session with the first tenant for a super admin without any membership', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = NoerdUser::factory()->create(['super_admin' => true]);
    $this->actingAs($superAdmin);
    TenantHelper::clear();

    $response = app(SetupMiddleware::class)->handle(request(), fn($request) => response('ok'));

    expect($response->getContent())->toBe('ok')
        ->and(TenantHelper::getSelectedTenantId())->toBe($tenant->id);
});

it('still routes a tenant admin without any membership to the no-tenant screen', function (): void {
    Tenant::factory()->create();
    $user = NoerdUser::factory()->create(['super_admin' => false]);
    $this->actingAs($user);
    TenantHelper::clear();

    $response = app(SetupMiddleware::class)->handle(request(), fn($request) => response('ok'));

    expect($response->isRedirect(route('noerd.no-tenant')))->toBeTrue();
});

it('restores a super admin\'s saved tenant on authentication even without a membership', function (): void {
    $foreign = Tenant::factory()->create();
    $superAdmin = NoerdUser::factory()->create(['super_admin' => true]);
    $superAdmin->selected_tenant_id = $foreign->id;

    TenantHelper::clear();
    $this->actingAs($superAdmin);

    expect(TenantHelper::getSelectedTenantId())->toBe($foreign->id);
});
