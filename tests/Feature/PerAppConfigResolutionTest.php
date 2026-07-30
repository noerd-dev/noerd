<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Functional coverage for the per-app YAML resolution: a config change in ONE
 | app's YAML must take effect exactly there — and only there. Uses
 | runtime-written fixture YAMLs instead of asserting the content of real app
 | configs; real configs are per-installation settings and may change any time.
 */

beforeEach(function (): void {
    $tenant = Tenant::factory()->create();
    TenantHelper::setSelectedTenantId($tenant->id);

    foreach (['appalpha', 'appbeta'] as $app) {
        File::ensureDirectoryExists(base_path("app-configs/{$app}/details"));
        File::ensureDirectoryExists(base_path("app-configs/{$app}/lists"));
    }

    File::put(base_path('app-configs/appalpha/details/zz-resolution-detail.yml'), implode("\n", [
        'title: Alpha Detail',
        'view: compact',
        'fields:',
        '  - name: detailData.alpha_only',
        '    label: Alpha Field',
        '    type: text',
    ]));
    File::put(base_path('app-configs/appbeta/details/zz-resolution-detail.yml'), implode("\n", [
        'title: Beta Detail',
        'view: numbered',
        'fields:',
        '  - name: detailData.beta_only',
        '    label: Beta Field',
        '    type: text',
    ]));

    File::put(base_path('app-configs/appalpha/lists/zz-resolutions-list.yml'), implode("\n", [
        'title: Alpha List',
        'columns:',
        '  - field: alpha_column',
        '    label: Alpha Column',
    ]));
    File::put(base_path('app-configs/appbeta/lists/zz-resolutions-list.yml'), implode("\n", [
        'title: Beta List',
        'columns:',
        '  - field: beta_column',
        '    label: Beta Column',
    ]));
});

afterEach(function (): void {
    File::deleteDirectory(base_path('app-configs/appalpha'));
    File::deleteDirectory(base_path('app-configs/appbeta'));
});

it('resolves the detail YAML of the selected app', function (): void {
    TenantHelper::setSelectedApp('APPALPHA');
    $layout = StaticConfigHelper::getComponentFields('zz-resolution-detail');

    expect($layout['title'])->toBe('Alpha Detail')
        ->and($layout['view'])->toBe('compact')
        ->and(array_column($layout['fields'], 'name'))->toBe(['detailData.alpha_only']);

    TenantHelper::setSelectedApp('APPBETA');
    $layout = StaticConfigHelper::getComponentFields('zz-resolution-detail');

    expect($layout['title'])->toBe('Beta Detail')
        ->and($layout['view'])->toBe('numbered')
        ->and(array_column($layout['fields'], 'name'))->toBe(['detailData.beta_only']);
});

it('resolves the list YAML of the selected app', function (): void {
    TenantHelper::setSelectedApp('APPALPHA');
    expect(array_column(StaticConfigHelper::getListConfig('zz-resolutions-list')['columns'], 'field'))
        ->toBe(['alpha_column']);

    TenantHelper::setSelectedApp('APPBETA');
    expect(array_column(StaticConfigHelper::getListConfig('zz-resolutions-list')['columns'], 'field'))
        ->toBe(['beta_column']);
});

it('applies an edit to one app YAML only in that app', function (): void {
    TenantHelper::setSelectedApp('APPALPHA');
    expect(StaticConfigHelper::getComponentFields('zz-resolution-detail')['view'])->toBe('compact');

    $alphaDetail = base_path('app-configs/appalpha/details/zz-resolution-detail.yml');
    File::put($alphaDetail, str_replace('view: compact', 'view: numbered', File::get($alphaDetail)));
    touch($alphaDetail, time() + 2);
    clearstatcache(true, $alphaDetail);

    expect(StaticConfigHelper::getComponentFields('zz-resolution-detail')['view'])->toBe('numbered');

    TenantHelper::setSelectedApp('APPBETA');
    expect(StaticConfigHelper::getComponentFields('zz-resolution-detail')['title'])->toBe('Beta Detail');
});
