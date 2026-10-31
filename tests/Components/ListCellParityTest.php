<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\Tenant;
use Noerd\Support\CsvListExporter;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Every rendering mode of a list — the table, the card grid, the minimal widget
 | — and the CSV export write a column type the same way, including the
 | array-shaped values of colored_text and badge_with_text, which used to reach
 | the grid and the widget as raw arrays. Every pill is the one badge component.
 */

class ZzCellParityListComponent extends Component
{
    use NoerdList;

    /** @var array<int, array<string, mixed>> */
    public array $rowsData = [];

    /** @var array<string, mixed> */
    public array $configData = [];

    /**
     * @return array<string, mixed>
     */
    public function listData(): array
    {
        return $this->buildList($this->rowsData, $this->configData);
    }

    public function render(): string
    {
        return '<div><x-noerd::list /></div>';
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function zzParityColumns(): array
{
    return [
        ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
        ['field' => 'status', 'label' => 'Status', 'type' => 'badge', 'options' => [['value' => 'open', 'label' => 'Open label']]],
        ['field' => 'owner', 'label' => 'Owner', 'type' => 'badge_with_text'],
        ['field' => 'path', 'label' => 'Path', 'type' => 'colored_text'],
        ['field' => 'tenant_id', 'label' => 'Tenant', 'type' => 'relationBadge'],
        ['field' => 'meta', 'label' => 'Meta', 'type' => 'customAttribute'],
    ];
}

/**
 * @return array<string, mixed>
 */
function zzParityRow(int $tenantId): array
{
    return [
        'id' => 1,
        'name' => 'Alice',
        'status' => 'open',
        'owner' => ['badge' => 'Team A', 'text' => 'Bob', 'variant' => 'success'],
        'path' => ['prefix' => '↳', 'text' => 'Child page', 'class' => 'bg-green-100'],
        'tenant_id' => $tenantId,
        'meta' => ['de' => 'Haus', 'en' => 'House'],
    ];
}

/**
 * @param  array<string, mixed>  $config
 * @param  array<string, mixed>  $params
 */
function zzParityHtml(array $config, array $params = []): string
{
    return html_entity_decode(
        Livewire::test(ZzCellParityListComponent::class, $params)
            ->set('rowsData', [zzParityRow(test()->tenant->id)])
            ->set('configData', $config)
            ->html(),
    );
}

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['name' => 'Zz Parity Tenant']);
});

it('renders every typed cell of the table through the formatter and the badge component', function (): void {
    $html = zzParityHtml(['columns' => zzParityColumns()]);

    expect($html)->toContain('Open label', 'Team A', 'Bob', '↳', 'Child page', 'Zz Parity Tenant', 'House')
        ->not->toContain('Array');

    // The success badge of the badge_with_text cell and the neutral pills of badge + relationBadge.
    assertElementHasClasses($html, ['rounded-full', 'bg-green-100', 'text-green-800']);
    assertElementHasClasses($html, ['rounded-full', 'bg-gray-100', 'text-gray-700']);
    // The coloured chip of the colored_text cell.
    assertElementHasClasses($html, ['truncate', 'rounded', 'px-2', 'bg-green-100']);
});

it('renders the same values on the cards of the grid', function (): void {
    $html = zzParityHtml(['displayMode' => 'grid', 'columns' => zzParityColumns()]);

    expect($html)->toContain('Open label', 'Team A Bob', '↳ Child page', 'Zz Parity Tenant', 'House')
        ->not->toContain('Array');

    assertElementHasClasses($html, ['rounded-full', 'text-[11px]', 'bg-gray-100']);
});

it('renders the same values in the minimal widget list', function (): void {
    $html = zzParityHtml(['columns' => zzParityColumns()], ['minimal' => true]);

    expect($html)->toContain('<table', 'Open label', 'Team A Bob', '↳ Child page', 'Zz Parity Tenant', 'House')
        ->not->toContain('Array');

    assertElementHasClasses($html, ['rounded-full', 'text-[11px]', 'bg-gray-100']);
});

it('writes the same text into the CSV export', function (): void {
    $row = zzParityRow($this->tenant->id);

    $cells = array_map(
        fn(array $column): string => CsvListExporter::formatValue($row[$column['field']], $column),
        zzParityColumns(),
    );

    expect($cells)->toBe(['Alice', 'Open label', 'Team A Bob', '↳ Child page', 'Zz Parity Tenant', 'House']);
});
