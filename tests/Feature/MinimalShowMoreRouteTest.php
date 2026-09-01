<?php

declare(strict_types=1);

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class);

/**
 * @param  array<string, mixed>  $params
 */
function renderMinimalList(array $params): string
{
    return html_entity_decode(
        Livewire::test(ZzShowMoreListComponent::class, $params)->html(),
    );
}

it('renders show more as $modalRoute without rewriting the url', function (): void {
    registerTestLivewireRoute('zz-show-more-list', 'noerd-test::theme-test', 'zz.show.more');

    $html = renderMinimalList([
        'showMoreRoute' => 'zz.show.more',
        'showMoreComponent' => 'zz::tasks-list',
        'showMoreArguments' => ['accountId' => 9],
    ]);

    expect($html)->toContain('$modalRoute(')
        ->toContain('zz.show.more')
        ->toContain('rewriteUrl: false')
        ->toContain('fallbackComponent');
});

it('keeps the component behaviour when no showMoreRoute is set', function (): void {
    $html = renderMinimalList([
        'showMoreComponent' => 'zz::tasks-list',
        'showMoreArguments' => ['accountId' => 9],
    ]);

    expect($html)->toContain('$modal(')
        ->toContain('zz::tasks-list')
        ->not->toContain('$modalRoute(');
});

it('falls back to the component when the showMoreRoute is not registered', function (): void {
    $html = renderMinimalList([
        'showMoreRoute' => 'zz.show.more.route.that.does.not.exist',
        'showMoreComponent' => 'zz::tasks-list',
    ]);

    expect($html)->toContain('$modal(')
        ->toContain('zz::tasks-list')
        ->not->toContain('$modalRoute(');
});

class ZzShowMoreListComponent extends Component
{
    use NoerdList;

    public function mount(): void
    {
        $this->minimal = true;
        $this->minimalColumns = ['name'];
    }

    public function with(): array
    {
        return [
            'listConfig' => [
                'listId' => 'zz-show-more',
                // A paginator with more total rows than shown is what makes the
                // minimal list render its "Show more" control.
                'rows' => new LengthAwarePaginator([['id' => 1, 'name' => 'First']], 2, 1),
                'listSettings' => ['columns' => [['field' => 'name', 'label' => 'Name']]],
            ],
        ];
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                @include('noerd::components.list.minimal')
            </div>
        BLADE;
    }

    protected function componentName(): string
    {
        return 'zz-show-more-test-list';
    }
}
