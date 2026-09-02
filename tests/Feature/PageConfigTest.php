<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Records the (viewType, component) pairs the helper hands the override hook. */
class RecordingPageOverrideResolver
{
    /** @var array<int, string> */
    public static array $seen = [];

    public function apply(string $viewType, string $component, array $config, ?string $modelClass = null): array
    {
        static::$seen[] = $viewType . '|' . $component;

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
    RecordingPageOverrideResolver::$seen = [];

    $user = NoerdUser::factory()->create(['super_admin' => true]);
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('SETUP');
    $this->actingAs($user);

    File::ensureDirectoryExists(base_path('app-configs/setup/pages'));
    File::put(
        base_path('app-configs/setup/pages/zz-widget-page.yml'),
        "title: Fixture Page\ndetail: noerd::zz-widget-detail\nwidgets: []\n",
    );
});

afterEach(function (): void {
    File::delete(base_path('app-configs/setup/pages/zz-widget-page.yml'));
});

it('resolves a page yaml from the project pages directory', function (): void {
    $config = StaticConfigHelper::getPageFields('noerd::zz-widget-page');

    expect($config['title'])->toBe('Fixture Page')
        ->and($config['detail'])->toBe('noerd::zz-widget-detail');
});

it('returns an empty array silently when the page yaml is missing', function (): void {
    expect(StaticConfigHelper::getPageFields('noerd::does-not-exist-page'))->toBe([]);
});

it('applies overrides with the page view type and the canonical component key', function (): void {
    app()->singleton(StaticConfigHelper::LAYOUT_OVERRIDES_BINDING, RecordingPageOverrideResolver::class);

    StaticConfigHelper::getPageFields('noerd::zz-widget-page');

    expect(RecordingPageOverrideResolver::$seen)->toContain('page|zz-widget-page')
        ->not->toContain('page|noerd::zz-widget-page');
});

it('does not consult the override resolver for a missing page yaml', function (): void {
    app()->singleton(StaticConfigHelper::LAYOUT_OVERRIDES_BINDING, RecordingPageOverrideResolver::class);

    StaticConfigHelper::getPageFields('noerd::does-not-exist-page');

    expect(RecordingPageOverrideResolver::$seen)->toBe([]);
});

it('resolves a page config path for an explicit app', function (): void {
    $path = StaticConfigHelper::resolveConfigPath('setup', 'page', 'zz-widget-page');

    expect($path)->toBe(base_path('app-configs/setup/pages/zz-widget-page.yml'));
});

it('returns null from resolveConfigPath for a missing page yaml', function (): void {
    expect(StaticConfigHelper::resolveConfigPath('setup', 'page', 'zz-missing-page'))->toBeNull();
});

it('rejects a traversal app segment when resolving a config path', function (): void {
    expect(StaticConfigHelper::resolveConfigPath('../../etc', 'list', 'x'))->toBeNull();
    expect(StaticConfigHelper::resolveConfigPath('foo/bar', 'list', 'x'))->toBeNull();
    expect(StaticConfigHelper::resolveConfigPath('..', 'detail', 'x'))->toBeNull();
});
