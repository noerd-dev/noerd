<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Records the (viewType, component) pairs and the model classes the helper hands the override hook. */
class RecordingLayoutOverrideResolver
{
    /** @var array<int, string> */
    public static array $seen = [];

    /** @var array<int, string|null> */
    public static array $seenModels = [];

    public function apply(string $viewType, string $component, array $config, ?string $modelClass = null): array
    {
        static::$seen[] = $viewType . '|' . $component;
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

/** The helper only forwards the class string — any module-local class proves the pass-through. */
class LayoutOverrideFixtureWidget extends Model
{
    protected $table = 'fixture_widgets';

    protected $guarded = [];
}

beforeEach(function (): void {
    RecordingLayoutOverrideResolver::$seen = [];
    RecordingLayoutOverrideResolver::$seenModels = [];
    app()->singleton(StaticConfigHelper::LAYOUT_OVERRIDES_BINDING, RecordingLayoutOverrideResolver::class);

    $user = NoerdUser::factory()->create(['super_admin' => true]);
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('SETUP');
    $this->actingAs($user);

    // The override resolver only runs for configs that actually resolve to a
    // YAML file, so the tests work against module-local fixtures.
    File::ensureDirectoryExists(base_path('app-configs/setup/lists'));
    File::ensureDirectoryExists(base_path('app-configs/setup/details'));
    File::put(base_path('app-configs/setup/lists/zz-widgets-list.yml'), "title: Fixture\ncolumns: []\n");
    File::put(base_path('app-configs/setup/details/zz-widget-detail.yml'), "title: Fixture\nfields: []\n");
});

afterEach(function (): void {
    File::delete(base_path('app-configs/setup/lists/zz-widgets-list.yml'));
    File::delete(base_path('app-configs/setup/details/zz-widget-detail.yml'));
});

/**
 * Overrides must be keyed by the config's own identity, not by whichever component
 * happens to render it. Callers pass their namespaced livewire name — the key has to
 * survive that, or an override saved against 'zz-widgets-list' is never found again.
 */
it('keys a list override by the canonical component, not the namespaced caller', function (): void {
    StaticConfigHelper::getListConfig('noerd::zz-widgets-list');

    expect(RecordingLayoutOverrideResolver::$seen)->toContain('list|zz-widgets-list')
        ->not->toContain('list|noerd::zz-widgets-list');
});

it('keys a detail override by the canonical component', function (): void {
    StaticConfigHelper::getComponentFields('noerd::zz-widget-detail');

    expect(RecordingLayoutOverrideResolver::$seen)->toContain('detail|zz-widget-detail')
        ->not->toContain('detail|noerd::zz-widget-detail');
});

it('leaves an already-canonical component untouched', function (): void {
    StaticConfigHelper::getListConfig('zz-widgets-list');

    expect(RecordingLayoutOverrideResolver::$seen)->toContain('list|zz-widgets-list');
});

/**
 * Config YAML almost never declares a `model:` key, so the model class is the only thing that lets a
 * resolver key off the object rather than the component. It has to reach the resolver intact.
 */
it('hands the resolver the model class a detail was mounted with', function (): void {
    StaticConfigHelper::getComponentFields('noerd::zz-widget-detail', LayoutOverrideFixtureWidget::class);

    expect(RecordingLayoutOverrideResolver::$seenModels)->toContain(LayoutOverrideFixtureWidget::class);
});

it('hands the resolver the model class a list was resolved with', function (): void {
    StaticConfigHelper::getListConfig('noerd::zz-widgets-list', LayoutOverrideFixtureWidget::class);

    expect(RecordingLayoutOverrideResolver::$seenModels)->toContain(LayoutOverrideFixtureWidget::class);
});

/** Callers that resolve a config out of context have no model — that must stay legal, not fatal. */
it('passes null when the caller has no model', function (): void {
    StaticConfigHelper::getComponentFields('noerd::zz-widget-detail');

    expect(RecordingLayoutOverrideResolver::$seenModels)->toContain(null);
});

/**
 * List configs always live flat in lists/ — a nested component name (dots from the
 * blade sub-folder) must resolve the flat YAML and key its override by the flat
 * name, so every caller of the same config shares one override.
 */
it('flattens dotted component names to the flat list key', function (): void {
    $fixturePath = base_path('app-configs/setup/lists/zz-dotted-list.yml');
    File::put($fixturePath, "title: Fixture\ncolumns: []\n");

    try {
        StaticConfigHelper::getListConfig('noerd::zz-fixture.zz-dotted-list');

        expect(RecordingLayoutOverrideResolver::$seen)
            ->toContain('list|zz-dotted-list')
            ->not->toContain('list|zz-fixture.zz-dotted-list');
    } finally {
        File::delete($fixturePath);
    }
});
