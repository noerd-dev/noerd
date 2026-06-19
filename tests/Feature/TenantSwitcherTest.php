<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * @return array{user: NoerdUser, tenants: array<int, Tenant>}
 */
function createMemberWithTenants(int $count): array
{
    $user = NoerdUser::factory()->create();

    $tenants = [];
    foreach (range(1, $count) as $i) {
        $tenant = Tenant::factory()->create(['name' => "Tenant {$i}"]);
        $user->tenants()->attach($tenant->id);
        $tenants[] = $tenant;
    }

    TenantHelper::setSelectedTenantId($tenants[0]->id);

    return ['user' => $user, 'tenants' => $tenants];
}

it('shows the tenant switcher in the quick-menu for an admin with a single tenant', function (): void {
    $admin = NoerdUser::factory()->adminUser()->create();

    $this->actingAs($admin);

    Livewire::test('noerd::layout.quick-menu')
        ->assertSeeLivewire('noerd::layout.tenant-switcher');
});

it('hides the tenant switcher for a non-admin with a single tenant', function (): void {
    $member = createMemberWithTenants(1);

    $this->actingAs($member['user']);

    Livewire::test('noerd::layout.quick-menu')
        ->assertDontSeeLivewire('noerd::layout.tenant-switcher');
});

it('shows the tenant switcher for a non-admin with multiple tenants', function (): void {
    $member = createMemberWithTenants(2);

    $this->actingAs($member['user']);

    Livewire::test('noerd::layout.quick-menu')
        ->assertSeeLivewire('noerd::layout.tenant-switcher');
});

it('lists all tenants the user belongs to', function (): void {
    $user = NoerdUser::factory()->create();
    $alpha = Tenant::factory()->create(['name' => 'Alpha Tenant']);
    $beta = Tenant::factory()->create(['name' => 'Beta Tenant']);
    $user->tenants()->attach([$alpha->id, $beta->id]);
    TenantHelper::setSelectedTenantId($alpha->id);

    $this->actingAs($user);

    Livewire::test('noerd::layout.tenant-switcher')
        ->assertSee('Alpha Tenant')
        ->assertSee('Beta Tenant');
});

it('shows the create-tenant entry for admins', function (): void {
    $admin = NoerdUser::factory()->adminUser()->create();

    $this->actingAs($admin);

    Livewire::test('noerd::layout.tenant-switcher')
        ->assertSee(__('New Tenant'));
});

it('hides the create-tenant entry for non-admins', function (): void {
    $member = createMemberWithTenants(2);

    $this->actingAs($member['user']);

    Livewire::test('noerd::layout.tenant-switcher')
        ->assertDontSee(__('New Tenant'));
});

it('switches the selected tenant and redirects', function (): void {
    $member = createMemberWithTenants(2);
    [$first, $second] = $member['tenants'];

    $this->actingAs($member['user']);

    Livewire::test('noerd::layout.tenant-switcher')
        ->call('switchTenant', $second->id)
        ->assertRedirect('/');

    expect(TenantHelper::getSelectedTenantId())->toBe($second->id);
});

it('does not switch to a tenant the user has no access to', function (): void {
    $member = createMemberWithTenants(1);
    $foreign = Tenant::factory()->create();

    $this->actingAs($member['user']);

    Livewire::test('noerd::layout.tenant-switcher')
        ->call('switchTenant', $foreign->id);

    expect(TenantHelper::getSelectedTenantId())->toBe($member['tenants'][0]->id);
});
