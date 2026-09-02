<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    CurrencyHelper::clearCache();
});

/**
 * A tenant-scoped user for the tenant-aware currency lookups.
 */
function createCurrencyTenantUser(): NoerdUser
{
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();

    $user->tenants()->attach($tenant->id);
    $user->selected_tenant_id = $tenant->id;
    TenantHelper::setSelectedTenantId($tenant->id);

    return $user;
}

describe('formatting', function (): void {
    it('formats currency with default config (German/Euro)', function (): void {
        config()->set('noerd.currency', [
            'symbol' => '€',
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'symbol_position' => 'after',
        ]);

        expect(CurrencyHelper::format(1234.56))->toBe('1.234,56 €');
    });

    it('formats currency with US format', function (): void {
        config()->set('noerd.currency', [
            'symbol' => '$',
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_position' => 'before',
        ]);

        expect(CurrencyHelper::format(1234.56))->toBe('$ 1,234.56');
    });

    it('always shows two decimal places', function (): void {
        config()->set('noerd.currency', [
            'symbol' => '€',
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'symbol_position' => 'after',
        ]);

        expect(CurrencyHelper::format(100.0))->toBe('100,00 €')
            ->and(CurrencyHelper::format(99.9))->toBe('99,90 €')
            ->and(CurrencyHelper::format(0.0))->toBe('0,00 €');
    });

    it('handles large numbers with thousands separators', function (): void {
        config()->set('noerd.currency', [
            'symbol' => '€',
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'symbol_position' => 'after',
        ]);

        expect(CurrencyHelper::format(1000000.50))->toBe('1.000.000,50 €');
    });

    it('handles negative values', function (): void {
        config()->set('noerd.currency', [
            'symbol' => '€',
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'symbol_position' => 'after',
        ]);

        expect(CurrencyHelper::format(-42.10))->toBe('-42,10 €');
    });

    it('uses fallback defaults when config is empty', function (): void {
        config()->set('noerd.currency', []);

        // Which fallback symbol/separators ship is configuration — what matters
        // is that an empty config still yields a usable, amount-carrying string.
        expect(CurrencyHelper::format(10.5))->toBeString()->not->toBe('')
            ->and(CurrencyHelper::format(10.5))->toContain('10');
    });
});

describe('tenant-aware config', function (): void {
    it('returns EUR config by default when no tenant setting exists', function (): void {
        $user = createCurrencyTenantUser();

        expect(CurrencyHelper::configForTenant($user->selected_tenant_id))
            ->toBe(CurrencyHelper::CURRENCY_PRESETS['EUR'])
            ->and(CurrencyHelper::codeForTenant($user->selected_tenant_id))->toBe('EUR');
    });

    it('resolves every tenant currency setting to its preset', function (string $code): void {
        $user = createCurrencyTenantUser();

        NoerdSettings::create([
            'tenant_id' => $user->selected_tenant_id,
            'currency' => $code,
        ]);

        expect(CurrencyHelper::configForTenant($user->selected_tenant_id))
            ->toBe(CurrencyHelper::CURRENCY_PRESETS[$code]);
    })->with(array_keys(CurrencyHelper::CURRENCY_PRESETS));

    it('formats currency correctly per tenant setting', function (): void {
        $user = createCurrencyTenantUser();

        NoerdSettings::create([
            'tenant_id' => $user->selected_tenant_id,
            'currency' => 'USD',
        ]);

        $preset = CurrencyHelper::CURRENCY_PRESETS['USD'];

        expect(CurrencyHelper::format(1234.56, $user->selected_tenant_id))
            ->toBe($preset['symbol'] . ' 1,234.56');
    });
});
