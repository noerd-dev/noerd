<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Helpers\ThemeHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The system-wide theme an admin configures under Setup -> System Settings.
 | Everything runs against runtime-written fixture YAMLs: which theme a shipped
 | config declares is per-installation configuration and must never be asserted.
 */

function writeThemeFixture(string $name, array $lines): void
{
    File::ensureDirectoryExists(base_path('app-configs/zzthemeapp/details'));
    File::put(base_path("app-configs/zzthemeapp/details/{$name}.yml"), implode("\n", $lines));
}

function setSystemTheme(?string $theme, bool $enforced = false): void
{
    NoerdSettings::updateOrCreate(
        ['tenant_id' => TenantHelper::getSelectedTenantId()],
        ['detail_theme' => $theme, 'detail_theme_enforced' => $enforced],
    );

    ThemeHelper::clearCache();
}

beforeEach(function (): void {
    $tenant = Tenant::factory()->create();
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('ZZTHEMEAPP');
    ThemeHelper::clearCache();

    File::ensureDirectoryExists(base_path('app-configs/zzthemeapp/lists'));

    writeThemeFixture('zz-plain-detail', [
        'title: Plain',
        'fields:',
        '  - name: detailData.name',
        '    label: Name',
        '    type: text',
    ]);

    writeThemeFixture('zz-opinionated-detail', [
        'title: Opinionated',
        'theme: numbered',
        'fields:',
        '  - name: detailData.name',
        '    label: Name',
        '    type: text',
        '    theme: default',
        '  - type: block',
        '    title: Nested',
        '    theme: compact',
        '    fields:',
        '      - name: detailData.nested',
        '        label: Nested',
        '        type: text',
        '        theme: compact',
    ]);

    File::put(base_path('app-configs/zzthemeapp/lists/zz-things-list.yml'), implode("\n", [
        'title: Things',
        'columns:',
        '  - field: name',
        '    label: Name',
    ]));
});

afterEach(function (): void {
    File::deleteDirectory(base_path('app-configs/zzthemeapp'));
    ThemeHelper::clearCache();
});

describe('system theme default', function (): void {
    it('falls back to the config default when no tenant setting exists', function (): void {
        expect(StaticConfigHelper::getComponentFields('zz-plain-detail')['theme'])->toBe('default');

        config()->set('noerd.theme.default', 'compact');
        ThemeHelper::clearCache();

        expect(StaticConfigHelper::getComponentFields('zz-plain-detail')['theme'])->toBe('compact');
    });

    it('fills the theme of a config that declares none', function (): void {
        setSystemTheme('compact');

        expect(StaticConfigHelper::getComponentFields('zz-plain-detail')['theme'])->toBe('compact');
    });

    it('lets a deviating YAML theme win when enforcement is off', function (): void {
        setSystemTheme('compact');

        expect(StaticConfigHelper::getComponentFields('zz-opinionated-detail')['theme'])->toBe('numbered');
    });

    it('falls back to default when the configured theme is not registered', function (): void {
        setSystemTheme('does-not-exist');

        expect(StaticConfigHelper::getComponentFields('zz-plain-detail')['theme'])->toBe('default');
    });

    it('never touches list configs', function (): void {
        setSystemTheme('compact');

        expect(StaticConfigHelper::getListConfig('zz-things-list'))->not->toHaveKey('theme');
    });
});

describe('enforced system theme', function (): void {
    it('overrides a deviating YAML theme', function (): void {
        setSystemTheme('compact', enforced: true);

        expect(StaticConfigHelper::getComponentFields('zz-opinionated-detail')['theme'])->toBe('compact');
    });

    it('drops per-field and nested block theme overrides', function (): void {
        setSystemTheme('compact', enforced: true);

        $layout = StaticConfigHelper::getComponentFields('zz-opinionated-detail');
        [$field, $block] = $layout['fields'];

        expect($field)->not->toHaveKey('theme')
            ->and($block)->not->toHaveKey('theme')
            ->and($block['fields'][0])->not->toHaveKey('theme');
    });

    it('can be switched on through the config fallback alone', function (): void {
        config()->set('noerd.theme.default', 'compact');
        config()->set('noerd.theme.enforced', true);
        TenantHelper::setSelectedTenantId(null);
        ThemeHelper::clearCache();

        expect(StaticConfigHelper::getComponentFields('zz-opinionated-detail')['theme'])->toBe('compact');
    });
});

describe('rendered output', function (): void {
    it('renders a config without a theme in the configured system theme', function (): void {
        setSystemTheme('compact');

        Livewire::test('noerd-test::theme-setting-test', ['detailComponent' => 'zz-plain-detail'])
            ->assertSuccessful()
            ->assertSeeHtml('data-theme="compact"');
    });

    it('renders the YAML theme when enforcement is off', function (): void {
        setSystemTheme('compact');

        Livewire::test('noerd-test::theme-setting-test', ['detailComponent' => 'zz-opinionated-detail'])
            ->assertSuccessful()
            ->assertSeeHtml('data-theme="numbered"');
    });

    it('renders the enforced theme even where the YAML deviates', function (): void {
        setSystemTheme('compact', enforced: true);

        Livewire::test('noerd-test::theme-setting-test', ['detailComponent' => 'zz-opinionated-detail'])
            ->assertSuccessful()
            ->assertSeeHtml('data-theme="compact"')
            ->assertDontSeeHtml('data-theme="numbered"');
    });
});
