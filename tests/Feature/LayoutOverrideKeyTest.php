<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Noerd\Contracts\LayoutOverrideResolver;
use Noerd\Customer\Models\Customer;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Records the (viewType, component) pairs and the model classes the helper hands the resolver. */
class RecordingLayoutOverrideResolver implements LayoutOverrideResolver
{
    /** @var array<int, string> */
    public static array $seen = [];

    /** @var array<int, string|null> */
    public static array $seenModels = [];

    public function apply(string $viewType, string $component, array $config, ?string $modelClass = null): array
    {
        static::$seen[] = $viewType.'|'.$component;
        static::$seenModels[] = $modelClass;

        return $config;
    }

    public function filterListViews(string $component, array $views): array
    {
        return $views;
    }

    public function listViews(string $component): array
    {
        return [];
    }
}

beforeEach(function (): void {
    RecordingLayoutOverrideResolver::$seen = [];
    RecordingLayoutOverrideResolver::$seenModels = [];
    app()->singleton(LayoutOverrideResolver::class, RecordingLayoutOverrideResolver::class);

    $user = NoerdUser::factory()->create(['super_admin' => true]);
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('STORE');
    $this->actingAs($user);
});

/**
 * Overrides must be keyed by the config's own identity, not by whichever component
 * happens to render it. Callers pass their namespaced livewire name — the key has to
 * survive that, or an override saved against 'customers-list' is never found again.
 */
it('keys a list override by the canonical component, not the namespaced caller', function (): void {
    StaticConfigHelper::getListConfig('customer::customers-list');

    expect(RecordingLayoutOverrideResolver::$seen)->toContain('list|customers-list')
        ->not->toContain('list|customer::customers-list');
});

it('keys a detail override by the canonical component', function (): void {
    StaticConfigHelper::getComponentFields('customer::customer-detail');

    expect(RecordingLayoutOverrideResolver::$seen)->toContain('detail|customer-detail')
        ->not->toContain('detail|customer::customer-detail');
});

it('leaves an already-canonical component untouched', function (): void {
    StaticConfigHelper::getListConfig('customers-list');

    expect(RecordingLayoutOverrideResolver::$seen)->toContain('list|customers-list');
});

/**
 * Config YAML almost never declares a `model:` key, so the model class is the only thing that lets a
 * resolver key off the object rather than the component. It has to reach the resolver intact.
 */
it('hands the resolver the model class a detail was mounted with', function (): void {
    StaticConfigHelper::getComponentFields('customer::customer-detail', Customer::class);

    expect(RecordingLayoutOverrideResolver::$seenModels)->toContain(Customer::class);
});

it('hands the resolver the model class a list was resolved with', function (): void {
    StaticConfigHelper::getListConfig('customer::customers-list', Customer::class);

    expect(RecordingLayoutOverrideResolver::$seenModels)->toContain(Customer::class);
});

/** Callers that resolve a config out of context have no model — that must stay legal, not fatal. */
it('passes null when the caller has no model', function (): void {
    StaticConfigHelper::getComponentFields('customer::customer-detail');

    expect(RecordingLayoutOverrideResolver::$seenModels)->toContain(null);
});

/** Sub-folder configs keep their dots — that is the hub's key format too. */
it('keeps dotted sub-paths in the canonical key', function (): void {
    // A fixture config in a sub-folder of the always-searchable setup app, so the dotted-key
    // behaviour is tested without depending on any module shipping a sub-folder config.
    TenantHelper::setSelectedApp('SETUP');

    $fixtureDir = base_path('app-configs/setup/lists/zz-fixture');
    File::ensureDirectoryExists($fixtureDir);
    File::put($fixtureDir.'/zz-dotted-list.yml', "title: Fixture\ncolumns: []\n");

    try {
        StaticConfigHelper::getListConfig('noerd::zz-fixture.zz-dotted-list');

        expect(RecordingLayoutOverrideResolver::$seen)
            ->toContain('list|zz-fixture.zz-dotted-list');
    } finally {
        File::deleteDirectory($fixtureDir);
    }
});
