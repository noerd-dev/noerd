<?php

declare(strict_types=1);

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Tests\TestCase;

uses(TestCase::class);

class ZzActionMessageHost extends Component
{
    public function render(): string
    {
        return <<<'BLADE'
            <div><x-noerd::action-message on="sa'ved">Done</x-noerd::action-message></div>
            BLADE;
    }
}

/*
 | Values rendered into a Livewire or Alpine directive are JavaScript: each is
 | encoded as a literal, so a quote in a page name, an event name or a filter
 | value can never end the string and start code.
 */

it('encodes the page name of the pagination buttons', function (): void {
    $html = view('noerd::components.table.list-pagination-nav', [
        'paginator' => new LengthAwarePaginator(range(1, 10), 30, 10, 2, ['pageName' => "pa'ge"]),
        'pageName' => "pa'ge",
    ])->render();

    expect($html)->not->toContain("previousPage('pa'ge')")
        ->and($html)->toContain('previousPage(')
        ->and($html)->toContain('\\u0027');
});

it('encodes the event name of the action message', function (): void {
    $html = Livewire::test(ZzActionMessageHost::class)->html();

    expect($html)->not->toContain("@this.on('sa'ved'")
        ->and($html)->toContain('\\u0027');
});

it('encodes the column and the option keys of the date filter', function (): void {
    $html = view('noerd::components.filters.date-dropdown', [
        'filter' => ['column' => "created'at", 'label' => 'Created', 'options' => ["to'day" => 'Today']],
        'value' => null,
        'label' => null,
        'active' => false,
        'full' => false,
        'isCustomDate' => false,
    ])->render();

    expect($html)->not->toContain("listFilters.created'at'")
        ->and($html)->not->toContain("'to'day'")
        ->and($html)->toContain('\\u0027');
});
