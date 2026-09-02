<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns empty array and logs warning for non-existing table config', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn(string $message) => str_contains($message, 'lists/___not_existing___.yml'));

    $user = NoerdUser::factory()->withExampleTenant()->withSelectedApp('noerdApp')->create();
    $this->actingAs($user);

    $config = StaticConfigHelper::getListConfig('___not_existing___');
    expect($config)->toBeArray()->toBeEmpty();
});

it('loads the list config from the app YAML', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->withSelectedApp('setup')->create();
    $this->actingAs($user);

    // Fixture round-trip instead of a shipped config: proves the YAML on disk is
    // what getListConfig() resolves for the selected app.
    $path = base_path('app-configs/setup/lists/zz-config-probe-list.yml');
    file_put_contents($path, "title: Zz Probe\ncolumns:\n  - field: name\n    label: Name\n");

    try {
        StaticConfigHelper::flushRuntimeCaches();
        $config = StaticConfigHelper::getListConfig('zz-config-probe-list');

        expect($config['title'])->toBe('Zz Probe')
            ->and($config['columns'])->toHaveCount(1);
    } finally {
        @unlink($path);
    }
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

it('loads the component fields from the app YAML', function (): void {
    $user = NoerdUser::factory()->withExampleTenant()->withSelectedApp('setup')->create();
    $this->actingAs($user);

    $path = base_path('app-configs/setup/details/zz-config-probe-detail.yml');
    file_put_contents($path, "title: Zz Probe Detail\nfields:\n  - name: detailData.zz_probe\n    label: Probe\n    type: text\n");

    try {
        StaticConfigHelper::flushRuntimeCaches();
        $fields = StaticConfigHelper::getComponentFields('zz-config-probe-detail');

        expect($fields['title'])->toBe('Zz Probe Detail')
            ->and(array_column($fields['fields'], 'name'))->toContain('detailData.zz_probe');
    } finally {
        @unlink($path);
    }
});

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
