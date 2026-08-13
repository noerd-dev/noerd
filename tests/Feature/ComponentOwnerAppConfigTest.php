<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | A module reached from ANOTHER app — linked from its navigation, embedded in
 | its detail, or opened as a modal from one of its tabs — must still resolve its
 | own list/detail/page YAML, even when the tenant was never granted that module
 | as an app. The namespace of the rendered component ("communication::…") is what
 | makes that reference explicit; without this fallback such a component renders
 | with no title, no columns and no fields.
 |
 | Runtime fixture YAMLs, never the shipped app-configs: those are per-installation
 | configuration and may change at any time.
 */

beforeEach(function (): void {
    $tenant = Tenant::factory()->create();

    $grantedApp = TenantApp::create([
        'name' => 'ZZHOSTAPP',
        'title' => 'Host App',
        'icon' => 'zzhostapp::icons.app',
        'route' => 'zzhostapp',
        'is_active' => true,
    ]);
    $tenant->tenantApps()->attach($grantedApp->id);

    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('ZZHOSTAPP');

    foreach (['lists', 'details', 'pages'] as $dir) {
        File::ensureDirectoryExists(base_path("app-configs/zzguestapp/{$dir}"));
    }

    File::put(base_path('app-configs/zzguestapp/lists/zz-guests-list.yml'), implode("\n", [
        'title: Guest List',
        'columns:',
        '  - field: guest_column',
        '    label: Guest Column',
    ]));
    File::put(base_path('app-configs/zzguestapp/details/zz-guest-detail.yml'), implode("\n", [
        'title: Guest Detail',
        'fields:',
        '  - name: detailData.guest_field',
        '    label: Guest Field',
        '    type: text',
    ]));
    File::put(base_path('app-configs/zzguestapp/pages/zz-guest-page.yml'), implode("\n", [
        'title: Guest Page',
        'detail: zzguestapp::zz-guest-detail',
    ]));

    StaticConfigHelper::flushRuntimeCaches();
});

afterEach(function (): void {
    File::deleteDirectory(base_path('app-configs/zzguestapp'));
    File::deleteDirectory(base_path('app-configs/zzhostapp'));
    StaticConfigHelper::flushRuntimeCaches();
});

it('resolves the list config of a namespaced component whose app the tenant does not have', function (): void {
    $config = StaticConfigHelper::getListConfig('zzguestapp::zz-guests-list');

    expect($config['title'])->toBe('Guest List')
        ->and(array_column($config['columns'], 'field'))->toBe(['guest_column']);
});

it('resolves the detail config of a namespaced component whose app the tenant does not have', function (): void {
    $layout = StaticConfigHelper::getComponentFields('zzguestapp::zz-guest-detail');

    expect($layout['title'])->toBe('Guest Detail')
        ->and(array_column($layout['fields'], 'name'))->toBe(['detailData.guest_field']);
});

it('resolves the page config of a namespaced component whose app the tenant does not have', function (): void {
    $layout = StaticConfigHelper::getPageFields('zzguestapp::zz-guest-page');

    expect($layout['title'])->toBe('Guest Page');
});

it('does not resolve a foreign app config for an unnamespaced component', function (): void {
    expect(StaticConfigHelper::getListConfig('zz-guests-list'))->toBe([])
        ->and(StaticConfigHelper::getComponentFields('zz-guest-detail'))->toBe([]);
});

it('lets the current app override the owning module config', function (): void {
    File::ensureDirectoryExists(base_path('app-configs/zzhostapp/lists'));
    File::put(base_path('app-configs/zzhostapp/lists/zz-guests-list.yml'), implode("\n", [
        'title: Host Override',
        'columns:',
        '  - field: host_column',
        '    label: Host Column',
    ]));
    StaticConfigHelper::flushRuntimeCaches();

    $config = StaticConfigHelper::getListConfig('zzguestapp::zz-guests-list');

    expect($config['title'])->toBe('Host Override')
        ->and(array_column($config['columns'], 'field'))->toBe(['host_column']);
});
