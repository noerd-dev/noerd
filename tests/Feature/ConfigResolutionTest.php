<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | How a component's list/detail/page/navigation YAML is resolved: from the
 | selected app, from the app owning a namespaced component, and from the module
 | source mapping. Everything runs against runtime-written fixture YAMLs — the
 | shipped app-configs are per-installation configuration and may change any time.
 */

/**
 * Which entries the installed setup navigation carries — and which of them are
 * gated by `feature:` or `superAdmin:` — is configuration. So these compare the
 * two resolutions against each other instead of naming any entry: gating may only
 * ever REMOVE entries, never add or reorder them.
 *
 * @return array<int, string>
 */
function setupNavigationTitles(): array
{
    StaticConfigHelper::flushRuntimeCaches();

    return collect(StaticConfigHelper::getNavigationStructure()[0]['block_menus'] ?? [])
        ->flatMap(fn(array $block): array => collect($block['navigations'] ?? [])->pluck('title')->all())
        ->all();
}

describe('per-app resolution', function (): void {
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
});

describe('component owner app', function (): void {
    /*
     | A module reached from ANOTHER app — linked from its navigation, embedded in
     | its detail, or opened as a modal from one of its tabs — must still resolve its
     | own list/detail/page YAML, even when the tenant was never granted that module
     | as an app. The namespace of the rendered component ("communication::…") is what
     | makes that reference explicit; without this fallback such a component renders
     | with no title, no columns and no fields.
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
});

describe('module source mapping', function (): void {
    /*
     | The module-to-app-config mapping is discovered by scanning base_path('app-modules').
     | One Pest process runs package tests (testbench skeleton, tiny app-modules) next to
     | host tests (full app-modules), so the memo must never outlive its base path.
     */

    it('rediscovers the module source mapping when the base path changes', function (): void {
        StaticConfigHelper::clearModuleSourceCache();

        $mapping = new ReflectionMethod(StaticConfigHelper::class, 'getModuleSourceMapping');
        $mapping->setAccessible(true);

        $skeletonBasePath = base_path();
        $skeletonMapping = $mapping->invoke(null);

        $otherBasePath = $skeletonBasePath . '/tests-other-root';
        File::ensureDirectoryExists($otherBasePath . '/app-modules/zz-fixture/app-configs/zzapp');
        app()->setBasePath($otherBasePath);

        try {
            expect($mapping->invoke(null))->toBe(['zzapp' => ['zz-fixture']])
                ->and($skeletonMapping)->not->toHaveKey('zzapp');
        } finally {
            app()->setBasePath($skeletonBasePath);
            File::deleteDirectory($otherBasePath);
        }

        expect($mapping->invoke(null))->toBe($skeletonMapping);
    });
});

describe('missing configs', function (): void {
    it('returns empty array and logs warning for non-existing table config', function (): void {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn(string $message) => str_contains($message, 'lists/___not_existing___.yml'));

        $user = NoerdUser::factory()->withExampleTenant()->withSelectedApp('noerdApp')->create();
        $this->actingAs($user);

        $config = StaticConfigHelper::getListConfig('___not_existing___');
        expect($config)->toBeArray()->toBeEmpty();
    });

    it('returns empty array and logs warning for non-existing model config', function (): void {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn(string $message) => str_contains($message, 'details/___not_existing___.yml'));

        $user = NoerdUser::factory()->withExampleTenant()->withSelectedApp('noerdApp')->create();
        $this->actingAs($user);

        $fields = StaticConfigHelper::getComponentFields('___not_existing___');
        expect($fields)->toBeArray()->toBeEmpty();
    });
});

describe('navigation resolution', function (): void {
    it('loads the navigation structure from the app navigation YAML', function (): void {
        $user = NoerdUser::factory()->withExampleTenant()->withSelectedApp('setup')->create();
        $this->actingAs($user);

        // Fixture round-trip: what the shipped navigation contains is configuration,
        // so the resolution is proven against a temporary navigation.yml.
        $navigationPath = base_path('app-configs/setup/navigation.yml');
        $backup = file_exists($navigationPath) ? file_get_contents($navigationPath) : null;
        @mkdir(dirname($navigationPath), 0755, true);
        file_put_contents($navigationPath, <<<'YAML'
-
  title: Setup
  name: setup
  block_menus:
    -
      title: Zz Block
      navigations:
        -
          title: 'Zz Probe Entry'
          route: noerd.setup
YAML);

        try {
            StaticConfigHelper::flushRuntimeCaches();
            $navigation = StaticConfigHelper::getNavigationStructure();

            expect($navigation[0]['block_menus'][0]['navigations'][0]['title'])->toBe('Zz Probe Entry');
        } finally {
            $backup === null ? @unlink($navigationPath) : file_put_contents($navigationPath, $backup);
            StaticConfigHelper::flushRuntimeCaches();
        }
    });

    it('returns null for navigation when no app selected', function (): void {
        $user = NoerdUser::factory()->withExampleTenant()->create();
        $this->actingAs($user);

        $navigation = StaticConfigHelper::getNavigationStructure();
        expect($navigation)->toBeNull();
    });

    it('hides feature-gated navigation items when the config value is false', function (): void {
        $user = NoerdUser::factory()->withExampleTenant()->withSelectedApp('setup')->create();
        $this->actingAs($user);

        // Synthetic fixture: which shipped entries are gated is configuration, so the
        // gating MECHANIC is proven against a temporary navigation.yml instead.
        $navigationPath = base_path('app-configs/setup/navigation.yml');
        $backup = file_exists($navigationPath) ? file_get_contents($navigationPath) : null;
        @mkdir(dirname($navigationPath), 0755, true);
        file_put_contents($navigationPath, <<<'YAML'
-
  title: Setup
  name: setup
  block_menus:
    -
      title: Administration
      navigations:
        -
          title: 'Zz Ungated Entry'
          route: noerd.setup
        -
          title: 'Zz Gated Entry'
          route: noerd.setup
          config: noerd.testing.synthetic_gate
YAML);

        try {
            config()->set('noerd.testing.synthetic_gate', true);
            $enabled = setupNavigationTitles();

            config()->set('noerd.testing.synthetic_gate', false);
            $disabled = setupNavigationTitles();

            expect($enabled)->toBe(['Zz Ungated Entry', 'Zz Gated Entry'])
                ->and($disabled)->toBe(['Zz Ungated Entry']);
        } finally {
            $backup === null ? @unlink($navigationPath) : file_put_contents($navigationPath, $backup);
        }
    });

    it('hides superAdmin navigation items from non-super admins', function (): void {
        $tenant = NoerdUser::factory()->withExampleTenant()->withSelectedApp('setup');

        // Synthetic fixture: whether any SHIPPED entry carries `superAdmin:` is
        // configuration, so the gating MECHANIC is proven against a temporary
        // navigation.yml instead.
        $navigationPath = base_path('app-configs/setup/navigation.yml');
        $backup = file_exists($navigationPath) ? file_get_contents($navigationPath) : null;
        @mkdir(dirname($navigationPath), 0755, true);
        file_put_contents($navigationPath, <<<'YAML'
-
  title: Setup
  name: setup
  block_menus:
    -
      title: Administration
      navigations:
        -
          title: 'Zz Ungated Entry'
          route: noerd.setup
        -
          title: 'Zz Super Admin Entry'
          route: noerd.setup
          superAdmin: true
YAML);

        try {
            $this->actingAs($tenant->create(['super_admin' => true]));
            $superAdmin = setupNavigationTitles();

            $this->actingAs($tenant->create(['super_admin' => false]));
            $regular = setupNavigationTitles();

            expect($superAdmin)->toBe(['Zz Ungated Entry', 'Zz Super Admin Entry'])
                ->and($regular)->toBe(['Zz Ungated Entry']);
        } finally {
            $backup === null ? @unlink($navigationPath) : file_put_contents($navigationPath, $backup);
            StaticConfigHelper::flushRuntimeCaches();
        }
    });
});
