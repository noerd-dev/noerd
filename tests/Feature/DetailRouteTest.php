<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Enums\Profile;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Every named setup route resolves to a mountable screen for an admin and is
 | closed to everyone else. WHICH component a route renders is configuration
 | (a page YAML may point its `detail:` anywhere), so only the route contract is
 | asserted here — never the component behind it.
 |
 | The collection-DEFINITION routes are left out on purpose: they carry the
 | `setup.collections.ui` middleware and 404 in the default (yaml) mode; their
 | gate is proven in SetupCollectionDatabaseModeTest.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    SetupLanguage::ensureDefaultLanguagesForTenant($this->tenant->id);

    $this->user = NoerdUser::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, ['profile_key' => Profile::Admin->value]);

    // A member of the same tenant WITHOUT the admin profile — a non-member would
    // be redirected by the membership middleware before authorization is reached.
    $this->member = NoerdUser::factory()->create();
    $this->member->tenants()->attach($this->tenant->id, ['profile_key' => Profile::User->value]);

    TenantHelper::setSelectedTenantId($this->tenant->id);
    TenantHelper::setSelectedApp('SETUP');
});

it('serves a named setup route to an admin and forbids a non-admin', function (string $routeName): void {
    $parameters = match ($routeName) {
        'noerd.user.detail' => ['modelId' => $this->user->id],
        'noerd.tenant.detail' => ['modelId' => $this->tenant->id],
        'noerd.setup-language.detail' => ['modelId' => SetupLanguage::where('code', 'en')->first()->id],
        'noerd.setup-collection.detail' => ['modelId' => SetupCollection::factory()->create([
            'tenant_id' => $this->tenant->id,
            'collection_key' => 'ZZ_ROUTE_COLLECTION',
        ])->id],
        default => [],
    };

    $this->actingAs($this->user);
    $this->get(route($routeName, $parameters))->assertSuccessful();

    $this->actingAs($this->member);
    $this->get(route($routeName, $parameters))->assertForbidden();
})->with([
    'noerd.setup',
    'noerd.tenant-apps',
    'noerd.users',
    'noerd.user.detail',
    'noerd.tenants',
    'noerd.tenant.detail',
    'noerd.create-tenant',
    'noerd.setup-collections',
    'noerd.setup-collection.detail',
    'noerd.setup-languages',
    'noerd.setup-language.detail',
    'noerd.system-settings',
]);
