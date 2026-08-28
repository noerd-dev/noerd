<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Profile;
use Noerd\Models\SetupCollection;
use Noerd\Models\Tenant;
use Noerd\Support\ComponentAccessGuard;
use Noerd\Support\ComponentAccessHook;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Attach $user to $tenant with an ADMIN or MEMBER profile so isAdmin() reflects
 * the intended role. Created without an active auth context, so BelongsToTenant's
 * creating hook does not re-tenant the fixtures.
 */
function makeTenantUser(Tenant $tenant, bool $admin = false, bool $super = false): NoerdUser
{
    $user = NoerdUser::factory()->create(['super_admin' => $super]);

    $profile = Profile::factory()->create([
        'tenant_id' => $tenant->id,
        'key' => $admin ? 'ADMIN' : 'MEMBER',
    ]);
    $user->tenants()->attach($tenant->id, ['profile_id' => $profile->id]);

    return $user;
}

// ---------------------------------------------------------------------------
// Fix 1 — dynamic-mount authorization
// ---------------------------------------------------------------------------

it('denies a non-admin from mounting an admin component through the guard', function (): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(makeTenantUser($tenant, admin: false));
    TenantHelper::setSelectedTenantId($tenant->id);

    expect(ComponentAccessGuard::allows('noerd::noerd-user-detail'))->toBeFalse();
    expect(ComponentAccessGuard::allows('noerd::system-settings-page'))->toBeFalse();
    expect(ComponentAccessGuard::allows('noerd::tenant-detail'))->toBeFalse();
});

it('permits an admin to mount an admin component, and anyone a non-admin component', function (): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(makeTenantUser($tenant, admin: true));
    TenantHelper::setSelectedTenantId($tenant->id);

    expect(ComponentAccessGuard::allows('noerd::noerd-user-detail'))->toBeTrue();
    // Non-admin components are never blocked by this guard.
    expect(ComponentAccessGuard::allows('crm::accounts-list'))->toBeTrue();
    expect(ComponentAccessGuard::allows('noerd::dashboard'))->toBeTrue();
    expect(ComponentAccessGuard::allows(null))->toBeTrue();
});

it('honours module-registered admin components', function (): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(makeTenantUser($tenant, admin: false));
    TenantHelper::setSelectedTenantId($tenant->id);

    ComponentAccessGuard::registerAdminComponents(['plus::user-role-detail']);

    expect(ComponentAccessGuard::allows('plus::user-role-detail'))->toBeFalse();
});

it('forbids a non-admin from mounting the user editor (component self-guard)', function (): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(makeTenantUser($tenant, admin: false));
    TenantHelper::setSelectedTenantId($tenant->id);

    Livewire::test('noerd::noerd-user-detail')->assertForbidden();
});

it('aborts through the dynamic-mount guard for a non-admin', function (): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(makeTenantUser($tenant, admin: false));
    TenantHelper::setSelectedTenantId($tenant->id);

    expect(fn(): mixed => ComponentAccessGuard::authorize('noerd::noerd-user-detail'))
        ->toThrow(HttpException::class);
});

it('enforces the admin guard from the component boot hook, regardless of mount path', function (): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(makeTenantUser($tenant, admin: false));
    TenantHelper::setSelectedTenantId($tenant->id);

    // Any mount (route, modal stack, generic page) boots this hook — an admin
    // component with no self-guard of its own is still rejected here, so the
    // modal system needs no knowledge of noerd's authorization.
    $hook = new ComponentAccessHook();
    $hook->setComponent(new class {
        public function getName(): string
        {
            return 'noerd::tenants-list';
        }
    });

    expect(fn(): mixed => $hook->boot())->toThrow(HttpException::class);
});

it('lets the boot hook through for a non-admin component', function (): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(makeTenantUser($tenant, admin: false));
    TenantHelper::setSelectedTenantId($tenant->id);

    $hook = new ComponentAccessHook();
    $hook->setComponent(new class {
        public function getName(): string
        {
            return 'crm::accounts-list';
        }
    });

    $hook->boot();
    expect(true)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Fix 2 — tenant scope fails closed
// ---------------------------------------------------------------------------

it('scopes tenant-owned queries to the selected tenant', function (): void {
    config()->set('noerd.features.multi_tenant', true);

    $t1 = Tenant::factory()->create();
    $t2 = Tenant::factory()->create();
    $mine = SetupCollection::factory()->create(['tenant_id' => $t1->id]);
    $other = SetupCollection::factory()->create(['tenant_id' => $t2->id]);

    $this->actingAs(makeTenantUser($t1, admin: false));
    TenantHelper::setSelectedTenantId($t1->id);

    $ids = SetupCollection::query()->pluck('id');
    expect($ids)->toContain($mine->id)->not->toContain($other->id);
});

it('returns no rows for an authenticated user without a resolved tenant (fail closed)', function (): void {
    config()->set('noerd.features.multi_tenant', true);

    $t1 = Tenant::factory()->create();
    SetupCollection::factory()->create(['tenant_id' => $t1->id]);

    $this->actingAs(makeTenantUser($t1, admin: false));
    TenantHelper::clear();

    expect(SetupCollection::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Fix 3 — mass-assignment whitelist (tenant_id / id / injected columns stripped)
// ---------------------------------------------------------------------------

it('reduces a detail payload to declared layout keys and always drops identity/tenant columns', function (): void {
    $component = new class {
        use NoerdDetail;

        /** @param array<int, array<string, mixed>> $fields */
        public function collectKeys(array $fields): array
        {
            return $this->writableKeysFromFields($fields);
        }

        /**
         * @param  array<string, mixed>  $data
         * @param  array<int, string>  $allowed
         */
        public function reduce(array $data, array $allowed): array
        {
            return $this->reduceToWritableKeys($data, $allowed);
        }
    };

    $fields = [
        ['name' => 'detailData.name', 'type' => 'text'],
        ['type' => 'block', 'fields' => [
            ['name' => 'detailData.custom_attributes.sap', 'type' => 'text'],
            ['name' => 'detailData.price', 'type' => 'number'],
        ]],
        ['name' => 'relationTitles.customer_id', 'type' => 'text'], // not detailData → ignored
    ];

    expect($component->collectKeys($fields))
        ->toEqualCanonicalizing(['name', 'custom_attributes', 'price']);

    $reduced = $component->reduce(
        [
            'id' => 5,
            'tenant_id' => 99,
            'name' => 'ok',
            'price' => 10,
            'is_admin' => true,      // injected, not in layout
            'custom_attributes' => ['sap' => 'A1'],
            'created_at' => 'now',
            'updated_at' => 'now',
        ],
        ['name', 'custom_attributes', 'price'],
    );

    expect($reduced)->toBe([
        'name' => 'ok',
        'price' => 10,
        'custom_attributes' => ['sap' => 'A1'],
    ]);
});
