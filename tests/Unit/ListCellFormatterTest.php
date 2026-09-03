<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Helpers\FormatHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Support\ListCellFormatter;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    CurrencyHelper::clearCache();
    FormatHelper::clearCache();
});

/**
 * An authenticated user whose tenant carries a currency/locale setting and who
 * reads the backend in $formatLocale — the two inputs every locale-dependent
 * cell type resolves through.
 */
function zzCellFormatterUser(string $currency = 'EUR', string $tenantLocale = 'de-DE', ?string $formatLocale = null): NoerdUser
{
    $user = NoerdUser::factory()->create();
    $tenant = Tenant::factory()->create();

    $user->tenants()->attach($tenant->id);
    $user->selected_tenant_id = $tenant->id;
    TenantHelper::setSelectedTenantId($tenant->id);

    NoerdSettings::create([
        'tenant_id' => $tenant->id,
        'currency' => $currency,
        'locale' => $tenantLocale,
    ]);

    if ($formatLocale !== null) {
        $user->setting->update(['format_locale' => $formatLocale]);
    }

    test()->actingAs($user, config('noerd.auth.guard'));

    return $user;
}

enum ZzCellBackedStatus: string
{
    case Draft = 'draft';
}

enum ZzCellPureStatus
{
    case Draft;
}

describe('scalar()', function (): void {
    it('unwraps a backed enum to its value', function (): void {
        expect(ListCellFormatter::scalar(ZzCellBackedStatus::Draft))->toBe('draft');
    });

    it('unwraps a pure enum to its case name', function (): void {
        expect(ListCellFormatter::scalar(ZzCellPureStatus::Draft))->toBe('Draft');
    });

    it('passes every other value through unchanged', function (mixed $value): void {
        expect(ListCellFormatter::scalar($value))->toBe($value);
    })->with([null, '', 'draft', 0, 1.5, true, [['a' => 'b']]]);
});

describe('truthy()', function (): void {
    it('reads a raw column value as a boolean', function (mixed $value, bool $expected): void {
        expect(ListCellFormatter::truthy($value))->toBe($expected);
    })->with([
        [true, true],
        [false, false],
        [1, true],
        [0, false],
        ['1', true],
        ['0', false],
        ['true', true],
        ['false', false],
        [null, false],
        ['', false],
        ['anything else', false],
    ]);
});

describe('badgeLabel()', function (): void {
    $options = [
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 0, 'label' => 'Zero'],
    ];

    it('resolves a value to its option label', function () use ($options): void {
        expect(ListCellFormatter::badgeLabel('draft', $options))->toBe('Draft');
    });

    it('compares loosely typed option values as strings', function () use ($options): void {
        expect(ListCellFormatter::badgeLabel('0', $options))->toBe('Zero')
            ->and(ListCellFormatter::badgeLabel(0, $options))->toBe('Zero');
    });

    it('unwraps an enum before matching', function () use ($options): void {
        expect(ListCellFormatter::badgeLabel(ZzCellBackedStatus::Draft, $options))->toBe('Draft');
    });

    it('falls back to the raw value when no option matches', function () use ($options): void {
        expect(ListCellFormatter::badgeLabel('archived', $options))->toBe('archived');
    });

    it('returns an empty string for a null value and for no options', function () use ($options): void {
        expect(ListCellFormatter::badgeLabel(null, $options))->toBe('')
            ->and(ListCellFormatter::badgeLabel(null, []))->toBe('');
    });
});

describe('format()', function (): void {
    it('writes a currency cell in the tenant currency and the reader locale', function (): void {
        zzCellFormatterUser(currency: 'EUR', formatLocale: 'de-DE');

        expect(zzNormalizeSpaces(ListCellFormatter::format(1234.5, ['type' => 'currency'])))
            ->toBe('1.234,50 €');
    });

    it('follows the reader locale, not the tenant locale, for an amount', function (): void {
        zzCellFormatterUser(currency: 'EUR', tenantLocale: 'de-DE', formatLocale: 'en-US');

        expect(zzNormalizeSpaces(ListCellFormatter::format(1234.5, ['type' => 'currency'])))
            ->toBe('€1,234.50');
    });

    it('passes a non-numeric currency value through', function (): void {
        zzCellFormatterUser();

        expect(ListCellFormatter::format('n/a', ['type' => 'currency']))->toBe('n/a')
            ->and(ListCellFormatter::format(null, ['type' => 'currency']))->toBe('');
    });

    it('writes a date cell in the reader locale', function (): void {
        zzCellFormatterUser(formatLocale: 'de-DE');

        expect(ListCellFormatter::format('2026-09-03', ['type' => 'date']))->toBe('03.09.2026');
    });

    it('writes a datetime cell in the reader locale', function (): void {
        zzCellFormatterUser(formatLocale: 'de-DE');

        expect(zzNormalizeSpaces(ListCellFormatter::format('2026-09-03 14:05:00', ['type' => 'datetime'])))
            ->toContain('03.09.2026');
    });

    it('renders an empty date and datetime as an empty string', function (): void {
        zzCellFormatterUser();

        expect(ListCellFormatter::format(null, ['type' => 'date']))->toBe('')
            ->and(ListCellFormatter::format(null, ['type' => 'datetime']))->toBe('');
    });

    it('renders both boolean spellings as Yes / No', function (string $type): void {
        zzCellFormatterUser();

        expect(ListCellFormatter::format(1, ['type' => $type]))->toBe(__('Yes'))
            ->and(ListCellFormatter::format(0, ['type' => $type]))->toBe(__('No'))
            ->and(ListCellFormatter::format(null, ['type' => $type]))->toBe(__('No'));
    })->with(['bool', 'boolean']);

    it('renders a badge cell as the translated option label', function (): void {
        zzCellFormatterUser();

        $column = [
            'type' => 'badge',
            'options' => [['value' => 'draft', 'label' => 'Draft']],
        ];

        expect(ListCellFormatter::format('draft', $column))->toBe(__('Draft'))
            ->and(ListCellFormatter::format(ZzCellBackedStatus::Draft, $column))->toBe(__('Draft'));
    });

    it('passes a value through for text, an unknown type and a missing type', function (array $column): void {
        zzCellFormatterUser();

        expect(ListCellFormatter::format('Widget A', $column))->toBe('Widget A')
            ->and(ListCellFormatter::format(42, $column))->toBe('42')
            ->and(ListCellFormatter::format(null, $column))->toBe('');
    })->with([
        [['type' => 'text']],
        [['type' => 'number']],
        [['type' => 'colored_text']],
        [[]],
    ]);

    it('stringifies an enum value for a pass-through type', function (): void {
        zzCellFormatterUser();

        expect(ListCellFormatter::format(ZzCellBackedStatus::Draft, ['type' => 'text']))->toBe('draft');
    });
});
