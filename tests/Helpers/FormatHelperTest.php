<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Noerd\Helpers\FormatHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    FormatHelper::clearCache();
    NoerdSettings::clearCache();
});

/**
 * A tenant-scoped, authenticated user; null keeps the user without a locale.
 */
function zzFormatUser(?string $formatLocale = null): NoerdUser
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

describe('locale resolution', function (): void {
    it('prefers the user locale over the tenant locale', function (): void {
        $user = zzFormatUser('en-GB');
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'locale' => 'de-DE']);

        expect(FormatHelper::locale())->toBe('en-GB')
            ->and(FormatHelper::tenantLocale())->toBe('de-DE');
    });

    it('falls back to the tenant locale for a user without one', function (): void {
        $user = zzFormatUser();
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'locale' => 'fr-FR']);

        expect(FormatHelper::locale())->toBe('fr-FR');
    });

    it('falls back to the configured locale when the tenant has none', function (): void {
        zzFormatUser();
        config()->set('noerd.format.locale', 'nl-NL');

        expect(FormatHelper::locale())->toBe('nl-NL')
            ->and(FormatHelper::tenantLocale())->toBe('nl-NL');
    });

    it('derives the locale from the interface language as the last resort', function (): void {
        zzFormatUser();
        config()->set('noerd.format.locale', null);
        app()->setLocale('de');

        expect(FormatHelper::locale())->toBe('de-DE');

        app()->setLocale('xx');
        FormatHelper::clearCache();

        expect(FormatHelper::locale())->toBe('en-US');
    });

    it('ignores an unsupported user or tenant locale', function (): void {
        $user = zzFormatUser('klingon');
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'locale' => 'xx-XX']);
        app()->setLocale('en');

        expect(FormatHelper::locale())->toBe('en-US');
    });

    it('normalizes an underscore locale', function (): void {
        zzFormatUser('de_de');

        expect(FormatHelper::locale())->toBe('de-DE');
    });

    it('sees a saved tenant locale without an explicit cache flush', function (): void {
        $user = zzFormatUser();
        app()->setLocale('en');
        expect(FormatHelper::tenantLocale())->toBe('en-US');

        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'locale' => 'da-DK']);

        expect(FormatHelper::tenantLocale())->toBe('da-DK');
    });
});

describe('dates and times', function (): void {
    it('writes a date the way the locale does', function (string $locale, string $expected): void {
        zzFormatUser($locale);

        expect(FormatHelper::date('2026-09-03'))->toBe($expected);
    })->with([
        ['de-DE', '03.09.2026'],
        ['en-US', '09/03/2026'],
        ['en-GB', '03/09/2026'],
        ['sv-SE', '2026-09-03'],
    ]);

    it('writes date-time and time in the locale', function (): void {
        zzFormatUser('en-US');
        $value = Carbon::create(2026, 9, 3, 14, 5);

        expect(FormatHelper::dateTime($value))->toBe('09/03/2026 2:05 PM')
            ->and(FormatHelper::time($value))->toBe('2:05 PM');

        FormatHelper::clearCache();
        zzFormatUser('de-DE');

        expect(FormatHelper::dateTime($value))->toBe('03.09.2026 14:05')
            ->and(FormatHelper::time($value))->toBe('14:05');
    });

    it('renders nothing for an empty value', function (): void {
        zzFormatUser('en-US');

        expect(FormatHelper::date(null))->toBe('')
            ->and(FormatHelper::dateTime(''))->toBe('')
            ->and(FormatHelper::time(null))->toBe('');
    });

    it('lets a pinned format win over the locale', function (): void {
        zzFormatUser('en-US');
        config()->set('noerd.format.date', 'Y-m-d');
        config()->set('noerd.format.datetime', 'Y-m-d H:i');

        expect(FormatHelper::date('2026-09-03'))->toBe('2026-09-03')
            ->and(FormatHelper::dateTime('2026-09-03 14:05:00'))->toBe('2026-09-03 14:05');
    });

    it('writes document dates in the tenant locale, not the user locale', function (): void {
        $user = zzFormatUser('en-US');
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'locale' => 'de-DE']);

        expect(FormatHelper::documentDate('2026-09-03', $user->selected_tenant_id))->toBe('03.09.2026')
            ->and(FormatHelper::documentDateTime('2026-09-03 14:05', $user->selected_tenant_id))->toBe('03.09.2026 14:05')
            ->and(FormatHelper::date('2026-09-03'))->toBe('09/03/2026');
    });
});

describe('numbers', function (): void {
    it('writes decimals, quantities and percentages in the locale', function (): void {
        zzFormatUser('de-DE');

        expect(FormatHelper::decimal(1234.5))->toBe('1.234,50')
            ->and(FormatHelper::number(2.5, 3))->toBe('2,5')
            ->and(zzNormalizeSpaces(FormatHelper::percent(19)))->toBe('19 %');

        FormatHelper::clearCache();
        zzFormatUser('en-US');

        expect(FormatHelper::decimal(1234.5))->toBe('1,234.50')
            ->and(FormatHelper::number(2.5, 3))->toBe('2.5')
            ->and(FormatHelper::percent(19))->toBe('19%');
    });

    it('lets pinned separators win over the locale', function (): void {
        zzFormatUser('en-US');
        config()->set('noerd.format.decimal_separator', ',');
        config()->set('noerd.format.thousands_separator', '.');

        expect(FormatHelper::decimal(1234.5))->toBe('1.234,50');
    });

    it('completes a single pinned separator from the locale', function (): void {
        zzFormatUser('en-US');
        config()->set('noerd.format.decimal_separator', ',');

        expect(FormatHelper::decimal(1234.5))->toBe('1,234,50');
    });

    it('writes document decimals in the tenant locale', function (): void {
        $user = zzFormatUser('en-US');
        NoerdSettings::create(['tenant_id' => $user->selected_tenant_id, 'locale' => 'de-DE']);

        expect(FormatHelper::documentDecimal(1234.5, 2, $user->selected_tenant_id))->toBe('1.234,50');
    });
});
