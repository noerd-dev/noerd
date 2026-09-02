<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Enums\Profile;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Helpers\FormatHelper;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Support\Locales;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createUserWithSetupTenant(): NoerdUser
{
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();

    // The system-settings page is admin-only and enforces it on mount, so the
    // test user needs an ADMIN profile.
    $user->tenants()->attach($tenant->id, ['profile_key' => Profile::Admin->value]);
    $user->selected_tenant_id = $tenant->id;
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('SETUP');

    $app = TenantApp::firstOrCreate(
        ['name' => 'SETUP'],
        [
            'title' => 'Setup',
            'icon' => 'noerd::icons.app',
            'route' => 'setup',
            'is_active' => true,
        ],
    );
    $tenant->tenantApps()->syncWithoutDetaching([$app->id]);

    return $user;
}

/**
 * The settings layout the page renders, reduced to the field names — what the
 * currency feature flag adds to or removes from the form.
 *
 * @return array<int, string>
 */
function zzSettingsFieldNames(NoerdUser $user): array
{
    $layout = Livewire::actingAs($user)->test('noerd::system-settings-page')->get('pageLayout');

    return array_column($layout['fields'] ?? [], 'name');
}

/**
 * Run the callback against a synthetic settings YAML: which fields the shipped
 * system-settings page carries is configuration, so the feature gating is proven
 * against a fixture layout instead.
 */
function withZzSettingsLayout(Closure $callback): void
{
    $path = base_path('app-configs/setup/settings/system-settings-page.yml');
    $backup = file_exists($path) ? file_get_contents($path) : null;
    @mkdir(dirname($path), 0755, true);
    file_put_contents($path, <<<'YAML'
title: Zz System Settings
fields:
  - name: detailData.currency
    label: Zz Currency
    type: select
    optionsMethod: currencyOptions
  - name: detailData.locale
    label: Zz Locale
    type: select
    optionsMethod: localeOptions
  - name: detailData.detail_theme
    label: Zz Theme
    type: select
    optionsMethod: themeOptions
YAML);
    StaticConfigHelper::flushRuntimeCaches();

    try {
        $callback();
    } finally {
        $backup === null ? @unlink($path) : file_put_contents($path, $backup);
        StaticConfigHelper::flushRuntimeCaches();
    }
}


beforeEach(function (): void {
    CurrencyHelper::clearCache();
});

describe('NoerdSettings component', function (): void {
    it('defaults to EUR when no setting exists', function (): void {
        $user = createUserWithSetupTenant();

        Livewire::actingAs($user)
            ->test('noerd::system-settings-page')
            ->assertSet('detailData.currency', 'EUR');
    });

    it('can save currency setting', function (): void {
        $user = createUserWithSetupTenant();

        Livewire::actingAs($user)
            ->test('noerd::system-settings-page')
            ->set('detailData.currency', 'USD')
            ->call('store');

        $this->assertDatabaseHas('noerd_settings', [
            'tenant_id' => $user->selected_tenant_id,
            'currency' => 'USD',
        ]);
    });

    it('loads existing setting from database', function (): void {
        $user = createUserWithSetupTenant();

        NoerdSettings::create([
            'tenant_id' => $user->selected_tenant_id,
            'currency' => 'GBP',
        ]);

        Livewire::actingAs($user)
            ->test('noerd::system-settings-page')
            ->assertSet('detailData.currency', 'GBP');
    });

    it('validates currency must be valid', function (): void {
        $user = createUserWithSetupTenant();

        Livewire::actingAs($user)
            ->test('noerd::system-settings-page')
            ->set('detailData.currency', 'INVALID')
            ->call('store')
            ->assertHasErrors(['detailData.currency']);
    });
});

describe('Currency feature flag', function (): void {
    it('shows currency section when feature is enabled', function (): void {
        config()->set('noerd.features.currency', true);

        withZzSettingsLayout(function (): void {
            // The enforce field is shipped but NOT part of the fixture layout —
            // its absence proves the synthetic YAML is what the page rendered.
            expect(zzSettingsFieldNames(createUserWithSetupTenant()))
                ->toContain('detailData.currency')
                ->toContain('detailData.detail_theme')
                ->not->toContain('detailData.detail_theme_enforced');
        });
    });

    it('hides currency section when feature is disabled', function (): void {
        config()->set('noerd.features.currency', false);

        withZzSettingsLayout(function (): void {
            expect(zzSettingsFieldNames(createUserWithSetupTenant()))
                ->not->toContain('detailData.currency')
                ->toContain('detailData.detail_theme');
        });
    });

    it('does not save the currency when the currency feature is disabled', function (): void {
        config()->set('noerd.features.currency', false);
        $user = createUserWithSetupTenant();

        Livewire::actingAs($user)
            ->test('noerd::system-settings-page')
            ->set('detailData.currency', 'USD')
            ->set('detailData.detail_theme', 'compact')
            ->call('store')
            ->assertHasNoErrors();

        // The remaining settings still persist — only the currency is left untouched.
        $this->assertDatabaseHas('noerd_settings', [
            'tenant_id' => $user->selected_tenant_id,
            'currency' => 'EUR',
            'detail_theme' => 'compact',
        ]);
    });
});

describe('Theme setting', function (): void {
    it('defaults to the config fallback when no setting exists', function (): void {
        config()->set('noerd.theme.default', 'numbered');
        $user = createUserWithSetupTenant();

        Livewire::actingAs($user)
            ->test('noerd::system-settings-page')
            ->assertSet('detailData.detail_theme', 'numbered')
            ->assertSet('detailData.detail_theme_enforced', false);
    });

    it('loads an existing setting from the database', function (): void {
        $user = createUserWithSetupTenant();

        NoerdSettings::create([
            'tenant_id' => $user->selected_tenant_id,
            'detail_theme' => 'compact',
            'detail_theme_enforced' => true,
        ]);

        Livewire::actingAs($user)
            ->test('noerd::system-settings-page')
            ->assertSet('detailData.detail_theme', 'compact')
            ->assertSet('detailData.detail_theme_enforced', true);
    });

    it('saves the theme and the enforce flag', function (): void {
        $user = createUserWithSetupTenant();

        Livewire::actingAs($user)
            ->test('noerd::system-settings-page')
            ->set('detailData.detail_theme', 'numbered')
            ->set('detailData.detail_theme_enforced', true)
            ->call('store')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('noerd_settings', [
            'tenant_id' => $user->selected_tenant_id,
            'detail_theme' => 'numbered',
            'detail_theme_enforced' => true,
        ]);
    });

    it('rejects a theme that is not registered', function (): void {
        $user = createUserWithSetupTenant();

        Livewire::actingAs($user)
            ->test('noerd::system-settings-page')
            ->set('detailData.detail_theme', 'does-not-exist')
            ->call('store')
            ->assertHasErrors(['detailData.detail_theme']);
    });
});

describe('Tenant locale setting', function (): void {
    it('defaults to the locale of the interface language when no setting exists', function (): void {
        $user = createUserWithSetupTenant();
        app()->setLocale('de');

        Livewire::actingAs($user)
            ->test('noerd::system-settings-page')
            ->assertSet('detailData.locale', 'de-DE');
    });

    it('loads an existing locale from the database', function (): void {
        $user = createUserWithSetupTenant();
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'locale' => 'en-GB']);

        Livewire::actingAs($user)
            ->test('noerd::system-settings-page')
            ->assertSet('detailData.locale', 'en-GB');
    });

    it('saves the locale and makes it the document locale at once', function (): void {
        withZzSettingsLayout(function (): void {
            $user = createUserWithSetupTenant();

            Livewire::actingAs($user)
                ->test('noerd::system-settings-page')
                ->set('detailData.locale', 'en-US')
                ->call('store')
                ->assertHasNoErrors();

            $this->assertDatabaseHas('noerd_settings', [
                'tenant_id' => $user->selected_tenant_id,
                'locale' => 'en-US',
            ]);

            expect(FormatHelper::tenantLocale($user->selected_tenant_id))->toBe('en-US');
        });
    });

    it('rejects a locale outside the fixed list', function (): void {
        $user = createUserWithSetupTenant();

        Livewire::actingAs($user)
            ->test('noerd::system-settings-page')
            ->set('detailData.locale', 'xx-XX')
            ->call('store')
            ->assertHasErrors(['detailData.locale']);
    });

    it('offers exactly the supported locales', function (): void {
        $user = createUserWithSetupTenant();

        $options = Livewire::actingAs($user)
            ->test('noerd::system-settings-page')
            ->instance()
            ->localeOptions();

        expect(array_keys($options))->toBe(Locales::SUPPORTED);
    });
});
