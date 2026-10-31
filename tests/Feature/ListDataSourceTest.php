<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\ViewException;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

/*
 | listData() is the one source of a list's rows and config. A with() carrying a
 | `listConfig` key is plain view data the list never reads, and a component
 | without a model has to say where its rows come from.
 */

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->create());
});

it('renders the rows of an overriding listData() and ignores a listConfig returned by with()', function (): void {
    Livewire::test(ZzManualRowsListComponent::class)
        ->assertSee('From listData')
        ->assertDontSee('From with');
});

it('refuses to render a list that neither declares a model nor overrides listData()', function (): void {
    // The view engine wraps the LogicException the trait throws; the message is what counts.
    expect(fn() => Livewire::test(ZzModelLessListComponent::class))
        ->toThrow(ViewException::class, 'declares no $listModel');
});

/** Rows assembled in PHP — a repository-style list without a model. */
class ZzManualRowsListComponent extends Component
{
    use NoerdList;

    public function listData(): array
    {
        return $this->buildList(
            new LengthAwarePaginator([['id' => 1, 'name' => 'From listData']], 1, 10),
            ['title' => 'Zz Rows', 'columns' => [['field' => 'name', 'label' => 'Name']]],
        );
    }

    /** Plain view data in the shape the view used to read — it no longer does. */
    public function with(): array
    {
        return [
            'listConfig' => [
                'listId' => 'zz-from-with',
                'rows' => [['id' => 2, 'name' => 'From with']],
                'listSettings' => ['title' => 'Zz Rows', 'columns' => [['field' => 'name', 'label' => 'Name']]],
            ],
        ];
    }

    public function render(): string
    {
        return '<x-noerd::page><x-noerd::list /></x-noerd::page>';
    }
}

class ZzModelLessListComponent extends Component
{
    use NoerdList;

    public function render(): string
    {
        return '<x-noerd::page><x-noerd::list /></x-noerd::page>';
    }
}
