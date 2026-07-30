<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

$rows = [
    ['id' => 1, 'name' => 'Alice'],
    ['id' => 2, 'name' => 'Bob'],
];

$columns = [
    ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
];

/**
 * Matches the line-number cell content whitespace-insensitively so Blade
 * reformatting of the list view cannot break the assertion.
 */
function lineNumberCellPattern(int $number): string
{
    return '/select-none"\s*>\s*' . $number . '\s*<\/td>/';
}

it('renders a leading line-number column when showLineNumbers is set in the config', function () use ($rows, $columns): void {
    $html = Livewire::test(TestableLineNumbersListComponent::class)
        ->set('rowsData', $rows)
        ->set('configData', ['showLineNumbers' => true, 'columns' => $columns])
        ->html();

    expect(preg_match(lineNumberCellPattern(1), $html))->toBe(1)
        ->and(preg_match(lineNumberCellPattern(2), $html))->toBe(1);
});

it('does not render line numbers when the config does not enable them', function () use ($rows, $columns): void {
    $html = Livewire::test(TestableLineNumbersListComponent::class)
        ->set('rowsData', $rows)
        ->set('configData', ['columns' => $columns])
        ->html();

    expect(preg_match(lineNumberCellPattern(1), $html))->toBe(0);
});

/**
 * Minimal list component that renders the generic noerd list view from a directly
 * provided array config, so the showLineNumbers rendering can be tested in isolation.
 */
class TestableLineNumbersListComponent extends Component
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
        return '<div><x-noerd::list :compact="true" /></div>';
    }
}
