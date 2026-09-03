<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Helpers\FormatHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    CurrencyHelper::clearCache();
    FormatHelper::clearCache();
});

/**
 * A tenant-scoped, authenticated user with an optional formatting locale.
 */
function zzCurrencyUser(?string $formatLocale = null): NoerdUser
{
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();

    $user->tenants()->attach($tenant->id);
    $user->selected_tenant_id = $tenant->id;
    TenantHelper::setSelectedTenantId($tenant->id);

    if ($formatLocale !== null) {
        $user->setting->update(['format_locale' => $formatLocale]);
    }

    test()->actingAs($user, NoerdAuth::guardName());

    return $user;
}

describe('tenant currency code', function (): void {
    it('falls back to the configured default currency when the tenant has no setting', function (): void {
        $user = zzCurrencyUser();
        config()->set('noerd.currency.default', 'GBP');

        expect(CurrencyHelper::codeForTenant($user->selected_tenant_id))->toBe('GBP');
    });

    it('ignores an unsupported stored code', function (): void {
        $user = zzCurrencyUser();
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'currency' => 'XXX']);

        expect(CurrencyHelper::codeForTenant($user->selected_tenant_id))->toBe('EUR');
    });

    it('resolves every supported currency from the tenant setting', function (string $code): void {
        $user = zzCurrencyUser();
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'currency' => $code]);

        expect(CurrencyHelper::codeForTenant($user->selected_tenant_id))->toBe($code);
    })->with(array_keys(CurrencyHelper::CURRENCIES));

    it('reads the settings row once per tenant and request', function (): void {
        $user = zzCurrencyUser();
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'currency' => 'USD']);
        CurrencyHelper::clearCache();

        DB::enableQueryLog();
        CurrencyHelper::codeForTenant($user->selected_tenant_id);
        CurrencyHelper::codeForTenant($user->selected_tenant_id);
        CurrencyHelper::format(1.0, $user->selected_tenant_id);
        $queries = collect(DB::getQueryLog())->filter(fn(array $query) => str_contains($query['query'], 'noerd_settings'));
        DB::disableQueryLog();

        expect($queries)->toHaveCount(1);
    });

    it('sees a saved currency without an explicit cache flush', function (): void {
        $user = zzCurrencyUser();
        expect(CurrencyHelper::codeForTenant($user->selected_tenant_id))->toBe('EUR');

        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'currency' => 'CHF']);

        expect(CurrencyHelper::codeForTenant($user->selected_tenant_id))->toBe('CHF');
    });
});

describe('formatting for the user', function (): void {
    it('writes the tenant currency in the reader locale', function (): void {
        $user = zzCurrencyUser('en-US');
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'currency' => 'EUR']);

        expect(zzNormalizeSpaces(CurrencyHelper::format(1234.56)))->toBe('€1,234.56');
    });

    it('writes the same amount differently for a German reader', function (): void {
        $user = zzCurrencyUser('de-DE');
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'currency' => 'USD']);

        expect(zzNormalizeSpaces(CurrencyHelper::format(1234.56)))->toBe('1.234,56 $');
    });

    it('accepts an explicit locale', function (): void {
        $user = zzCurrencyUser('de-DE');
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'currency' => 'USD']);

        expect(zzNormalizeSpaces(CurrencyHelper::format(1234.56, locale: 'en-US')))->toBe('$1,234.56');
    });

    it('formats an explicit currency independent of the tenant setting', function (): void {
        zzCurrencyUser('en-GB');

        expect(zzNormalizeSpaces(CurrencyHelper::formatIn(99.9, 'GBP')))->toBe('£99.90');
    });
});

describe('formatting for documents', function (): void {
    it('uses the tenant locale and ignores the user locale', function (): void {
        $user = zzCurrencyUser('de-DE');
        NoerdSettings::create([
            'tenant_id' => $user->selected_tenant_id,
            'currency' => 'USD',
            'locale' => 'en-US',
        ]);

        expect(zzNormalizeSpaces(CurrencyHelper::formatForDocument(1234.56, $user->selected_tenant_id)))->toBe('$1,234.56')
            ->and(zzNormalizeSpaces(CurrencyHelper::format(1234.56)))->toBe('1.234,56 $');
    });

    it('derives the document locale from the interface language when the tenant has none', function (): void {
        $user = zzCurrencyUser('en-US');
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'currency' => 'EUR']);
        app()->setLocale('de');

        expect(zzNormalizeSpaces(CurrencyHelper::formatForDocument(1234.56, $user->selected_tenant_id)))->toBe('1.234,56 €');
    });
});

describe('input configuration', function (): void {
    it('describes the euro for a German reader', function (): void {
        $user = zzCurrencyUser('de-DE');
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'currency' => 'EUR']);

        expect(CurrencyHelper::configForTenant())->toMatchArray([
            'code' => 'EUR',
            'symbol' => '€',
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'symbol_position' => 'after',
        ]);
    });

    it('describes the dollar for a US reader', function (): void {
        $user = zzCurrencyUser('en-US');
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'currency' => 'USD']);

        expect(CurrencyHelper::configForTenant())->toMatchArray([
            'code' => 'USD',
            'symbol' => '$',
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'symbol_position' => 'before',
        ])->and(CurrencyHelper::symbol())->toBe('$');
    });

    it('offers every supported currency with a sample in the reader locale', function (): void {
        zzCurrencyUser('en-US');

        $options = CurrencyHelper::options();

        expect(array_keys($options))->toBe(array_keys(CurrencyHelper::CURRENCIES))
            ->and(zzNormalizeSpaces($options['USD']))->toBe('USD - US Dollar ($1,234.56)');
    });
});
