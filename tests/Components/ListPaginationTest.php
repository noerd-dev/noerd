<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The list's pagination navigation: one partial ("1-50 of 150" + icon-only
 | previous/next buttons) rendered in the header's filter row AND in the footer,
 | plus the footer-only rows-per-page select capped at MAX_PER_PAGE.
 */

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
    NoerdUser::factory()->count(2)->create();
    $this->total = NoerdUser::count();
});

it('renders the same summary and page buttons in the filter row and in the footer', function (): void {
    $html = Livewire::test(PaginatedListComponent::class)->set('perPage', 1)->html();

    expect(mb_substr_count($html, "1-1 of {$this->total}"))->toBe(2)
        ->and(mb_substr_count($html, "previousPage('page')"))->toBe(2)
        ->and(mb_substr_count($html, "nextPage('page')"))->toBe(2)
        ->and($html)->not->toContain('Showing')
        ->and($html)->not->toContain('results')
        // Icon-only buttons: labelled through aria-label, never an sr-only span
        // (position:absolute would stretch the document past the scroll container).
        ->and(mb_substr_count($html, 'aria-label="Previous"'))->toBe(2)
        ->and(mb_substr_count($html, 'aria-label="Next"'))->toBe(2)
        ->and($html)->not->toContain('sr-only');

    // Byte-identical markup: the two copies come from one partial.
    preg_match_all('/<div class="flex shrink-0 items-center gap-2">\s*<span class="hidden[^§]*?<\/button>\s*<\/div>/u', $html, $navs);

    expect($navs[0])->toHaveCount(2)
        ->and($navs[0][0])->toBe($navs[0][1]);
});

it('shows the nav in the filter row above the table and only in the footer below it', function (): void {
    $html = Livewire::test(PaginatedListComponent::class)->set('perPage', 1)->html();

    $filterRow = mb_strpos($html, 'wire:key="list-filter-row"');
    $table = mb_strpos($html, '<table');
    $firstNav = mb_strpos($html, "previousPage('page')");
    $secondNav = mb_strpos($html, "previousPage('page')", $firstNav + 1);

    expect($filterRow)->toBeLessThan($firstNav)
        ->and($firstNav)->toBeLessThan($table)
        ->and($table)->toBeLessThan($secondNav);
});

it('hides the nav while the list is empty but keeps the filter row', function (): void {
    // Pagination alone never opens the filter row, and an empty result has
    // nothing to page through — only the search field stays.
    $html = Livewire::test(PaginatedListComponent::class)->set('search', 'zz-no-such-user')->html();

    expect($html)->toContain('wire:key="list-filter-row"')
        ->and($html)->toContain('wire:key="list-search"')
        ->and($html)->not->toContain("previousPage('page')");
});

it('disables the previous button on the first page and the next button on the last', function (): void {
    $component = Livewire::test(PaginatedListComponent::class)->set('perPage', 1);

    $navButtons = static function (string $html): array {
        preg_match_all('/<button[^>]*wire:click="(previousPage|nextPage)\(\'page\'\)"[^>]*>/', $html, $buttons, PREG_SET_ORDER);

        return array_map(static fn(array $button): array => [$button[1], str_contains($button[0], 'disabled="disabled"')], $buttons);
    };

    expect($navButtons($component->html()))->toBe([
        ['previousPage', true], ['nextPage', false],
        ['previousPage', true], ['nextPage', false],
    ]);

    $component->call('nextPage', 'page');

    expect($component->html())->toContain("2-2 of {$this->total}")
        ->and($navButtons($component->html()))->toBe([
            ['previousPage', false], ['nextPage', false],
            ['previousPage', false], ['nextPage', false],
        ]);

    $component->call('gotoPage', $this->total, 'page');

    expect($navButtons($component->html()))->toBe([
        ['previousPage', false], ['nextPage', true],
        ['previousPage', false], ['nextPage', true],
    ]);
});

it('offers a labelled rows-per-page select in the footer only, with no option above MAX_PER_PAGE', function (): void {
    $html = Livewire::test(PaginatedListComponent::class)->html();

    expect(mb_substr_count($html, 'wire:model.live="perPage"'))->toBe(1)
        ->and($html)->toContain('Rows per page');

    preg_match_all('/<option value="(\d+)">/', $html, $options);
    $sizes = array_map('intval', $options[1]);

    expect($sizes)->toBe([10, 25, 50, 100, 200])
        ->and(max($sizes))->toBe(200);
});

it('clamps a stored page size above MAX_PER_PAGE when mounting', function (): void {
    session(['listPerPage.paginated-list' => 500]);

    expect(Livewire::test(PaginatedListComponent::class)->get('perPage'))->toBe(200);
});

/** A model-backed list over noerd_users with the generic header and footer. */
class PaginatedListComponent extends Component
{
    use NoerdList;

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'listConfig' => $this->buildList(
                $this->listQuery(NoerdUser::class)->orderBy('id')->paginate($this->perPage),
                'paginated-list',
            ),
        ];
    }

    public function render(): string
    {
        return '<x-noerd::page><x-noerd::list /></x-noerd::page>';
    }

    protected function componentName(): string
    {
        return 'paginated-list';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Users',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
            ],
        ];
    }
}
