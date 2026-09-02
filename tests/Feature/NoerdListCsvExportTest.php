<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Helpers\AccessHelper;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class, RefreshDatabase::class);

/*
 | CSV export mechanics of NoerdList: the streamed response, the per-column
 | value formatting, the formula-injection neutralisation and the read guard.
 */

enum ZzCsvExportStatus: string
{
    case Active = 'active';
}

it('streams a BOM-prefixed CSV with translated headers and typed values', function (): void {
    config([
        'noerd.format.csv_delimiter' => ';',
        'noerd.format.datetime' => 'Y-m-d H:i',
    ]);

    NoerdUser::factory()->create([
        'name' => 'Alice Export',
        'super_admin' => true,
        'created_at' => '2026-01-05 10:30:00',
    ]);

    $response = Livewire::test(ZzCsvExportListComponent::class)
        ->instance()
        ->exportCsv();

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($response->headers->get('content-disposition'))->toContain('zz-export.csv')
        ->and($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain("Name;Admin;Created\n")
        ->and($csv)->toContain("\"Alice Export\";Yes;\"2026-01-05 10:30\"\n");
});

it('formats CSV values by column type', function (): void {
    config([
        'noerd.format.date' => 'Y-m-d',
        'noerd.format.datetime' => 'Y-m-d H:i',
    ]);

    $list = new class {
        use NoerdList;

        public function format(mixed $value, array $column): string
        {
            return $this->formatCsvValue($value, $column);
        }
    };

    $options = [
        ['value' => 'active', 'label' => 'Active Label'],
    ];

    expect($list->format(true, ['type' => 'bool']))->toBe('Yes')
        ->and($list->format(false, ['type' => 'bool']))->toBe('No')
        ->and($list->format('2026-01-05 10:30:00', ['type' => 'date']))->toBe('2026-01-05')
        ->and($list->format('2026-01-05 10:30:00', ['type' => 'datetime']))->toBe('2026-01-05 10:30')
        ->and($list->format(null, ['type' => 'date']))->toBe('')
        // badge resolves the option label — for raw values and BackedEnums alike
        ->and($list->format('active', ['type' => 'badge', 'options' => $options]))->toBe('Active Label')
        ->and($list->format(ZzCsvExportStatus::Active, ['type' => 'badge', 'options' => $options]))->toBe('Active Label')
        ->and($list->format('unknown', ['type' => 'badge', 'options' => $options]))->toBe('unknown')
        // a non-numeric value in a currency column is neutralized, not formatted
        ->and($list->format('=EVIL()', ['type' => 'currency']))->toBe("'=EVIL()");
});

it('neutralizes CSV formula-triggering values in the export', function (): void {
    $list = new class {
        use NoerdList;

        public function format(mixed $value, array $column): string
        {
            return $this->formatCsvValue($value, $column);
        }
    };

    foreach (['=HYPERLINK("x")', '+1', '-cmd', '@SUM(A1)', "\tx", "\rx"] as $dangerous) {
        expect($list->format($dangerous, ['type' => 'text']))->toStartWith("'");
    }

    // Ordinary text is untouched.
    expect($list->format('Acme GmbH', ['type' => 'text']))->toBe('Acme GmbH');
    // Numbers keep their formatting (a negative number is not a formula here).
    config(['noerd.format.decimal_separator' => ',', 'noerd.format.thousands_separator' => '.']);
    expect($list->format(-5, ['type' => 'number']))->toBe('-5,00');
});

it('refuses the export for a read-denied user', function (): void {
    Gate::define(AccessHelper::OBJECT_READ_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);

    $component = Livewire::test(ZzCsvExportListComponent::class);

    expect(fn() => $component->instance()->exportCsv())
        ->toThrow(HttpException::class);
});

/**
 * List component with CSV export enabled through the prepareCsvExport()
 * extension contract.
 */
class ZzCsvExportListComponent extends Component
{
    use NoerdList;

    public function with(): array
    {
        return [
            'listConfig' => $this->buildList(
                $this->listQuery(NoerdUser::class)->paginate($this->perPage),
            ),
        ];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-csv-export-list';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Zz Csv Export',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
                ['field' => 'super_admin', 'label' => 'Admin', 'type' => 'bool'],
                ['field' => 'created_at', 'label' => 'Created', 'type' => 'datetime'],
            ],
        ];
    }

    protected function prepareCsvExport(): array
    {
        return [
            $this->listQuery(NoerdUser::class)->orderBy('id'),
            $this->getListConfig()['columns'],
            'zz-export.csv',
        ];
    }
}
