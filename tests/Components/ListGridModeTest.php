<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

$rows = [
    ['id' => 1, 'name' => 'Alice', 'city' => 'Berlin'],
    ['id' => 2, 'name' => 'Bob', 'city' => ''],
];

$columns = [
    ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
    ['field' => 'city', 'label' => 'City', 'type' => 'text'],
];

it('renders cards instead of a table when displayMode grid is set in the config', function () use ($rows, $columns): void {
    $html = Livewire::test(TestableGridListComponent::class)
        ->set('rowsData', $rows)
        ->set('configData', ['displayMode' => 'grid', 'columns' => $columns])
        ->html();

    expect($html)->toContain('wire:key="grid-')
        ->toContain("wire:click=\"openListRow('1')\"")
        ->not->toContain('<table')
        ->not->toContain('<thead');
});

it('renders the table when the config does not set a display mode', function () use ($rows, $columns): void {
    $html = Livewire::test(TestableGridListComponent::class)
        ->set('rowsData', $rows)
        ->set('configData', ['columns' => $columns])
        ->html();

    expect($html)->toContain('<table')
        ->not->toContain('wire:key="grid-');
});

it('maps gridColumns onto the responsive grid classes with a fallback of four', function () use ($rows, $columns): void {
    $htmlFor = fn (array $config): string => Livewire::test(TestableGridListComponent::class)
        ->set('rowsData', $rows)
        ->set('configData', $config)
        ->html();

    $base = ['displayMode' => 'grid', 'columns' => $columns];

    assertElementHasClasses($htmlFor([...$base, 'gridColumns' => 3]), ['grid', 'lg:grid-cols-3']);
    assertElementHasClasses($htmlFor($base), ['grid', 'xl:grid-cols-4']);
    assertElementHasClasses($htmlFor([...$base, 'gridColumns' => 9]), ['grid', 'xl:grid-cols-4']);
});

it('uses the first non-empty column value as the card title', function () use ($columns): void {
    $html = Livewire::test(TestableGridListComponent::class)
        ->set('rowsData', [['id' => 5, 'name' => '', 'city' => 'Hamburg']])
        ->set('configData', ['displayMode' => 'grid', 'columns' => $columns])
        ->html();

    expect(preg_match('/font-medium[^>]*>\s*Hamburg\s*</', $html))->toBe(1);
});

it('skips empty secondary values on the card', function () use ($rows, $columns): void {
    $html = Livewire::test(TestableGridListComponent::class)
        ->set('rowsData', $rows)
        ->set('configData', ['displayMode' => 'grid', 'columns' => $columns])
        ->html();

    expect($html)->toContain('Berlin')
        ->and(preg_match('/text-gray-500[^>]*>\s*<\/span>/', $html))->toBe(0);
});

it('renders badge columns as translated pills instead of the raw value', function (): void {
    $html = Livewire::test(TestableGridListComponent::class)
        ->set('rowsData', [['id' => 1, 'name' => 'Alice', 'status' => 'draft']])
        ->set('configData', [
            'displayMode' => 'grid',
            'columns' => [
                ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
                [
                    'field' => 'status',
                    'label' => 'Status',
                    'type' => 'badge',
                    'options' => [
                        ['value' => 'draft', 'label' => 'Draft label'],
                    ],
                ],
            ],
        ])
        ->html();

    expect($html)->toContain('rounded-full')
        ->toContain('Draft label')
        ->not->toContain('>draft<');
});

it('renders the empty state when the grid has no rows', function () use ($columns): void {
    $html = Livewire::test(TestableGridListComponent::class)
        ->set('rowsData', [])
        ->set('configData', ['displayMode' => 'grid', 'columns' => $columns])
        ->html();

    expect($html)->toContain(__('No entries yet'));
});

it('renders per-card checkboxes wired to toggleRecordSelection in multi-select mode', function () use ($rows, $columns): void {
    $html = Livewire::test(TestableGridListComponent::class)
        ->set('rowsData', $rows)
        ->set('configData', ['displayMode' => 'grid', 'multiSelect' => true, 'columns' => $columns])
        ->html();

    expect($html)->toContain('wire:key="grid-cb-')
        ->toContain("toggleRecordSelection('1')");
});

/**
 * Minimal list component that renders the generic noerd list view from a directly
 * provided array config, so the grid display mode can be tested in isolation.
 */
class TestableGridListComponent extends Component
{
    use NoerdList;

    /** @var array<int, array<string, mixed>> */
    public array $rowsData = [];

    /** @var array<string, mixed> */
    public array $configData = [];

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'listConfig' => $this->buildList($this->rowsData, $this->configData),
        ];
    }

    public function render(): string
    {
        return '<div><x-noerd::list /></div>';
    }
}
