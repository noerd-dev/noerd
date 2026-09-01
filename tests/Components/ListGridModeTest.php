<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
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
    $htmlFor = fn(array $config): string => Livewire::test(TestableGridListComponent::class)
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

it('renders a labeled funnel button per filterable column above the cards', function (): void {
    NoerdUser::factory()->create();

    $html = Livewire::test(TestableGridFilterListComponent::class)->html();

    expect($html)->toContain('wire:key="column-filter-name-')
        ->toContain('wire:key="column-filter-email-')
        ->toContain('<span>Name</span>')
        ->toContain('<span>Email</span>')
        ->not->toContain('wire:key="column-filter-custom_attributes.color-');
});

it('renders no control bar for a compact grid list', function (): void {
    NoerdUser::factory()->create();

    $html = Livewire::test(TestableGridFilterListComponent::class, ['compact' => true])->html();

    expect($html)->toContain('wire:key="grid-')
        ->not->toContain('wire:key="column-filter-name-')
        ->not->toContain('wire:key="grid-sort-');
});

it('marks the funnel button of an active grid filter', function (): void {
    NoerdUser::factory()->create(['name' => 'Rotkohl']);

    $html = Livewire::test(TestableGridFilterListComponent::class)
        ->call('setColumnFilter', 'name', 'rot')
        ->html();

    expect(preg_match('/wire:key="column-filter-name-.*?border-brand-primary/s', $html))->toBe(1);
});

it('offers only real, undotted columns in the grid sort dropdown', function (): void {
    NoerdUser::factory()->create();

    $html = Livewire::test(TestableGridFilterListComponent::class)->html();

    expect($html)->toContain('wire:key="grid-sort-')
        ->toContain("sortBy('name')")
        ->toContain("sortBy('email')")
        ->toContain('setSortDirection(true)')
        ->toContain('setSortDirection(false)')
        ->not->toContain("sortBy('custom_attributes.color')");
});

it('names the active sort column on the grid sort trigger', function (): void {
    NoerdUser::factory()->create();

    $html = Livewire::test(TestableGridFilterListComponent::class)
        ->call('sortBy', 'email')
        ->html();

    expect($html)->toContain(__('Sort by') . ': Email');
});

it('honors notSortableColumns of an array config in the grid sort dropdown', function () use ($rows): void {
    $html = Livewire::test(TestableGridListComponent::class)
        ->set('rowsData', $rows)
        ->set('configData', [
            'displayMode' => 'grid',
            'notSortableColumns' => ['city'],
            'columns' => [
                ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['field' => 'city', 'label' => 'City', 'type' => 'text'],
            ],
        ])
        ->html();

    expect($html)->toContain("sortBy('name')")
        ->not->toContain("sortBy('city')");
});

it('renders no grid sort dropdown when no column is sortable', function () use ($rows): void {
    $html = Livewire::test(TestableGridListComponent::class)
        ->set('rowsData', $rows)
        ->set('configData', [
            'displayMode' => 'grid',
            'columns' => [
                ['field' => 'owner.name', 'label' => 'Name', 'type' => 'text'],
                ['field' => 'action'],
            ],
        ])
        ->html();

    expect($html)->not->toContain('wire:key="grid-sort-');
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

/**
 * Grid list backed by a real model, so filterableColumns resolves and the grid
 * filter bar has something to render. The dotted column is deliberately neither
 * a JSON nor a relation path — it must stay unfilterable.
 */
class TestableGridFilterListComponent extends Component
{
    use NoerdList;

    /**
     * @return array<string, mixed>
     */
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
        return '<div><x-noerd::list /></div>';
    }

    protected function componentName(): string
    {
        return 'testable-grid-filter-list';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Testable Grid Users',
            'displayMode' => 'grid',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
                ['field' => 'email', 'label' => 'Email'],
                ['field' => 'custom_attributes.color', 'label' => 'Color'],
            ],
        ];
    }
}
