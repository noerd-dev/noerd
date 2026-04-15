<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

uses(Tests\TestCase::class, RefreshDatabase::class);

function createUserWithSetupTenant(): NoerdUser
{
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->tenants()->attach($tenant->id);
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

beforeEach(function (): void {
    CurrencyHelper::clearCache();
});

describe('CurrencyHelper tenant-aware config', function (): void {
    it('returns EUR config by default when no tenant setting exists', function (): void {
        $user = createUserWithSetupTenant();

        $config = CurrencyHelper::configForTenant($user->selected_tenant_id);

        expect($config['symbol'])->toBe('€')
            ->and($config['decimal_separator'])->toBe(',')
            ->and($config['thousands_separator'])->toBe('.')
            ->and($config['symbol_position'])->toBe('after');
    });

    it('returns correct config for USD tenant setting', function (): void {
        $user = createUserWithSetupTenant();

        NoerdSettings::create([
            'tenant_id' => $user->selected_tenant_id,
            'currency' => 'USD',
        ]);

        $config = CurrencyHelper::configForTenant($user->selected_tenant_id);

        expect($config['symbol'])->toBe('$')
            ->and($config['decimal_separator'])->toBe('.')
            ->and($config['thousands_separator'])->toBe(',')
            ->and($config['symbol_position'])->toBe('before');
    });

    it('returns correct config for GBP tenant setting', function (): void {
        $user = createUserWithSetupTenant();

        NoerdSettings::create([
            'tenant_id' => $user->selected_tenant_id,
            'currency' => 'GBP',
        ]);

        $config = CurrencyHelper::configForTenant($user->selected_tenant_id);

        expect($config['symbol'])->toBe('£')
            ->and($config['decimal_separator'])->toBe('.')
            ->and($config['thousands_separator'])->toBe(',')
            ->and($config['symbol_position'])->toBe('before');
    });

    it('formats currency correctly per tenant setting', function (): void {
        $user = createUserWithSetupTenant();

        NoerdSettings::create([
            'tenant_id' => $user->selected_tenant_id,
            'currency' => 'USD',
        ]);

        expect(CurrencyHelper::format(1234.56, $user->selected_tenant_id))->toBe('$ 1,234.56');
    });
});

describe('NoerdSettings component', function (): void {
    it('defaults to EUR when no setting exists', function (): void {
        $user = createUserWithSetupTenant();

        Livewire::actingAs($user)
            ->test('setup.system-settings-detail')
            ->assertSet('settingsData.currency', 'EUR');
    });

    it('can save currency setting', function (): void {
        $user = createUserWithSetupTenant();

        Livewire::actingAs($user)
            ->test('setup.system-settings-detail')
            ->set('settingsData.currency', 'USD')
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
            ->test('setup.system-settings-detail')
            ->assertSet('settingsData.currency', 'GBP');
    });

    it('validates currency must be valid', function (): void {
        $user = createUserWithSetupTenant();

        Livewire::actingAs($user)
            ->test('setup.system-settings-detail')
            ->set('settingsData.currency', 'INVALID')
            ->call('store')
            ->assertHasErrors(['settingsData.currency']);
    });
});
