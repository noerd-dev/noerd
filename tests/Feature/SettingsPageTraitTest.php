<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdSettingsPage;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Mechanics of the NoerdSettingsPage trait: settings YAML resolution
 | (settings/{name}.yml), the forced `settings` theme, the no-override
 | guarantee, tenant-singleton hydration and persistence across MULTIPLE
 | declared models. Everything runs against a runtime-written fixture YAML
 | and a synthetic component — never against shipped configuration.
 */

beforeEach(function (): void {
    ensureZzSettingsProfilesTable();

    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('ZZSETTINGSAPP');
    $this->actingAs($user);

    File::ensureDirectoryExists(base_path('app-configs/zzsettingsapp/settings'));
    File::put(base_path('app-configs/zzsettingsapp/settings/zz-settings-test-page.yml'), implode("\n", [
        'title: Settings Fixture',
        'theme: numbered',
        'tabs:',
        '  - number: 1',
        '    label: General',
        'fields:',
        '  - name: detailData.currency',
        '    label: Currency',
        '    type: text',
        '    required: true',
        '  - name: profileData.name',
        '    label: Profile Name',
        '    type: text',
        '  - name: profileData.key',
        '    label: Profile Key',
        '    type: text',
    ]));

    // Synthetic settings page editing TWO tenant-singleton models at once.
    Livewire::component('zz-settings-test-page', new class extends Component {
        use NoerdSettingsPage;

        public array $settingsModels = [
            'detailData' => NoerdSettings::class,
            'profileData' => ZzSettingsProfile::class,
        ];

        public array $profileData = [];

        public function render(): string
        {
            return '<div>zz-settings-test</div>';
        }
    });
});

afterEach(function (): void {
    File::deleteDirectory(base_path('app-configs/zzsettingsapp'));
});

it('resolves the settings YAML and forces the settings theme', function (): void {
    $layout = StaticConfigHelper::getSettingsFields('zz-settings-test-page');

    // The fixture declares `theme: numbered` — settings pages always render in
    // the built-in settings theme regardless.
    expect($layout['title'])->toBe('Settings Fixture')
        ->and($layout['theme'])->toBe('settings')
        ->and(array_column($layout['fields'], 'name'))
        ->toBe(['detailData.currency', 'profileData.name', 'profileData.key']);

    Livewire::test('zz-settings-test-page')
        ->assertSet('pageLayout.theme', 'settings')
        ->assertSet('pageLayout.title', 'Settings Fixture');
});

it('never consults the layout-override hook for a settings config', function (): void {
    $spy = new class {
        public array $calls = [];

        public function apply(string $viewType, string $component, array $config, ?string $modelClass = null): array
        {
            $this->calls[] = [$viewType, $component];
            $config['title'] = 'OVERRIDDEN';

            return $config;
        }
    };
    app()->instance(StaticConfigHelper::LAYOUT_OVERRIDES_BINDING, $spy);

    $layout = StaticConfigHelper::getSettingsFields('zz-settings-test-page');

    expect($layout['title'])->toBe('Settings Fixture')
        ->and($spy->calls)->toBe([]);
});

it('hydrates every declared model from the tenant singleton row', function (): void {
    $tenantId = TenantHelper::getSelectedTenantId();
    NoerdSettings::create(['tenant_id' => $tenantId, 'currency' => 'USD']);
    ZzSettingsProfile::create(['tenant_id' => $tenantId, 'key' => 'zz', 'name' => 'Tenant Profile']);

    Livewire::test('zz-settings-test-page')
        ->assertSet('detailData.currency', 'USD')
        ->assertSet('profileData.name', 'Tenant Profile');
});

it('stores every declared model as the tenant singleton and leaves other tenants alone', function (): void {
    $tenantId = TenantHelper::getSelectedTenantId();
    NoerdSettings::create(['tenant_id' => $tenantId, 'currency' => 'EUR']);
    ZzSettingsProfile::create(['tenant_id' => $tenantId, 'key' => 'zz', 'name' => 'Old Name']);

    $otherTenant = Tenant::factory()->create();
    NoerdSettings::create(['tenant_id' => $otherTenant->id, 'currency' => 'GBP']);

    Livewire::test('zz-settings-test-page')
        ->set('detailData.currency', 'CHF')
        ->set('profileData.name', 'New Name')
        ->call('store')
        ->assertHasNoErrors()
        ->assertSet('showSuccessIndicator', true);

    expect(NoerdSettings::where('tenant_id', $tenantId)->count())->toBe(1)
        ->and(NoerdSettings::where('tenant_id', $tenantId)->first()->currency)->toBe('CHF')
        ->and(ZzSettingsProfile::where('tenant_id', $tenantId)->first()->name)->toBe('New Name')
        ->and(NoerdSettings::where('tenant_id', $otherTenant->id)->first()->currency)->toBe('GBP');
});

it('creates missing singleton rows on store', function (): void {
    $tenantId = TenantHelper::getSelectedTenantId();

    Livewire::test('zz-settings-test-page')
        ->set('detailData.currency', 'USD')
        ->set('profileData.key', 'zz')
        ->set('profileData.name', 'Fresh Profile')
        ->call('store')
        ->assertHasNoErrors();

    expect(NoerdSettings::where('tenant_id', $tenantId)->first()->currency)->toBe('USD')
        ->and(ZzSettingsProfile::where('tenant_id', $tenantId)->first()->name)->toBe('Fresh Profile');
});

it('validates required fields from the settings layout', function (): void {
    $component = Livewire::test('zz-settings-test-page')
        ->set('detailData.currency', '')
        ->call('store');

    $component->assertHasErrors(requiredLayoutFields($component));
});

it('binds no model URL parameter', function (): void {
    $component = Livewire::test('zz-settings-test-page');

    expect($component->instance()->queryStringNoerdPage())->toBe([]);
});

it('throws a clear error when settingsModels is not declared', function (): void {
    Livewire::component('zz-settings-undeclared-page', new class extends Component {
        use NoerdSettingsPage;

        public function render(): string
        {
            return '<div>zz</div>';
        }
    });

    // Livewire wraps the mount-time RuntimeException in a ViewException —
    // assert on the message, which survives the wrapping.
    expect(fn() => Livewire::test('zz-settings-undeclared-page'))
        ->toThrow('must declare its tenant-singleton models');
});

it('blocks reading when the object-read gate denies a declared settings model', function (): void {
    Illuminate\Support\Facades\Gate::define(
        Noerd\Helpers\AccessHelper::OBJECT_READ_GATE,
        fn(?Illuminate\Contracts\Auth\Authenticatable $user, string $modelClass): bool => $modelClass !== NoerdSettings::class,
    );

    NoerdSettings::create(['tenant_id' => TenantHelper::getSelectedTenantId(), 'currency' => 'EUR']);

    Livewire::test('zz-settings-test-page')
        ->assertSet('objectReadBlocked', true)
        ->assertSet('detailData', []);
});

it('ignores store() when the object-write gate denies a declared settings model', function (): void {
    Illuminate\Support\Facades\Gate::define(
        Noerd\Helpers\AccessHelper::OBJECT_WRITE_GATE,
        fn(?Illuminate\Contracts\Auth\Authenticatable $user, string $modelClass): bool => $modelClass !== ZzSettingsProfile::class,
    );

    $tenantId = TenantHelper::getSelectedTenantId();

    Livewire::test('zz-settings-test-page')
        ->set('detailData.currency', 'USD')
        ->call('store')
        ->assertSet('showSuccessIndicator', false);

    expect(NoerdSettings::where('tenant_id', $tenantId)->exists())->toBeFalse()
        ->and(ZzSettingsProfile::where('tenant_id', $tenantId)->exists())->toBeFalse();
});
