<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Traits\NoerdList;

uses(Tests\TestCase::class, RefreshDatabase::class);

$rows = [
    ['id' => 1, 'name' => 'Alice'],
    ['id' => 2, 'name' => 'Bob'],
];

$columns = [
    ['field' => 'name', 'label' => 'Name', 'type' => 'text'],
];

it('renders a leading line-number column when showLineNumbers is set in the config', function () use ($rows, $columns): void {
    Livewire::test(TestableLineNumbersListComponent::class)
        ->set('rowsData', $rows)
        ->set('configData', ['showLineNumbers' => true, 'columns' => $columns])
        ->assertSeeHtml('select-none">1</td>')
        ->assertSeeHtml('select-none">2</td>');
});

it('does not render line numbers when the config does not enable them', function () use ($rows, $columns): void {
    Livewire::test(TestableLineNumbersListComponent::class)
        ->set('rowsData', $rows)
        ->set('configData', ['columns' => $columns])
        ->assertDontSeeHtml('select-none">1</td>');
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
