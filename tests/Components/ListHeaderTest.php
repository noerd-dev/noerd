<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Services\HeaderActionsRegistry;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The generic list header: which controls it computes (headerControls()), how a
 | list host with its OWN header slot gets them injected, and how the two rows
 | are laid out — buttons on the title row, search + filters + pagination on the
 | filter row, whose filter strip scrolls instead of wrapping or collapsing.
 */

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    // Installed modules register themselves at boot, so start from a clean registry.
    app()->instance(HeaderActionsRegistry::class, new HeaderActionsRegistry());
    Livewire::addNamespace('header-actions-test', viewPath: __DIR__ . '/../Feature/fixtures/header-actions');
});

describe('control injection', function (): void {
    it('injects search and the YAML actions into a custom header of a list host', function (): void {
        $html = Livewire::test(CustomHeaderListComponent::class)->assertOk()->html();

        expect($html)->toContain('Custom Header')
            ->toContain('wire:model.live.debounce.300ms="search"')
            ->toContain('$wire.listAction(null, [])')
            ->toContain('New Thing');
    });

    it('suppresses the injection with listControls=false', function (): void {
        $html = Livewire::test(CustomHeaderListComponent::class, ['controls' => false])
            ->assertOk()
            ->html();

        expect($html)->toContain('Custom Header')
            ->not->toContain('wire:model.live.debounce.300ms="search"')
            ->not->toContain('New Thing');
    });

    it('keeps the search but hides the actions in picker mode', function (): void {
        $html = Livewire::test(CustomHeaderListComponent::class, ['returnsSelection' => true])
            ->assertOk()
            ->html();

        expect($html)->toContain('wire:model.live.debounce.300ms="search"')
            ->not->toContain('New Thing');
    });

    it('mounts registry list actions through a custom header', function (): void {
        app(HeaderActionsRegistry::class)->registerListAction('header-actions-test::probe');

        Livewire::test(CustomHeaderListComponent::class)
            ->assertOk()
            ->assertSee('HA-PROBE:');
    });

    it('wraps the injected controls in the listControlsShow expression', function (): void {
        $html = Livewire::test(CustomHeaderListComponent::class, ['controlsShow' => 'currentTab === 2'])
            ->assertOk()
            ->html();

        expect($html)->toContain('x-show="currentTab === 2"')
            ->toContain('wire:model.live.debounce.300ms="search"');
    });

    it('keeps room for focus rings inside the scrolling filter strip', function (): void {
        // The strip is a scroll container, which clips BOTH axes; without padding
        // the focus ring of a filter control is cut off. It is built exactly like
        // the quick-menu: a reserved 6px track pulled out of the layout, and the
        // idle class that hides the thumb while nothing overflows.
        $html = Livewire::test(CollapsingHeaderListComponent::class)->assertOk()->html();

        preg_match_all('/class="([^"]*overflow-x-scroll[^"]*)"/', $html, $matches);

        expect($matches[1])->not->toBeEmpty()
            ->and($html)->toContain('x-data="noerdScrollShadow()"');

        foreach ($matches[1] as $classAttribute) {
            expect($classAttribute)->toContain('noerd-scrollbar')
                ->toContain('noerd-scrollbar-idle')
                ->toContain('-mb-[6px]')
                ->toContain('p-1')
                ->toContain('min-w-0')
                ->toContain('flex-1');
        }
    });
});

describe('headerControls', function (): void {
    it('splits the YAML actions into a primary and a secondary group', function (): void {
        $controls = Livewire::test(CollapsingHeaderListComponent::class)->instance()->headerControls();

        expect(array_column($controls['primary'], 'action'))->toBe(['listAction'])
            ->and(array_column($controls['secondary'], 'action'))->toBe(['exportSecondary']);
    });

    it('keeps the YAML position as the array key so the shortcut assignment survives the split', function (): void {
        $controls = Livewire::test(CollapsingHeaderListComponent::class)->instance()->headerControls();

        // 'Export' is the SECOND YAML action, so it must not inherit the first
        // action's implicit `n` shortcut after being moved into its own group.
        expect(array_keys($controls['secondary']))->toBe([1])
            ->and(array_keys($controls['primary']))->toBe([0]);
    });

    it('reports the search field and the CSV export', function (): void {
        $controls = Livewire::test(CollapsingHeaderListComponent::class)->instance()->headerControls();

        expect($controls['search'])->toBeTrue()
            ->and($controls['csv'])->toBeTrue();
    });

    it('drops the search field when the list disables it', function (): void {
        $controls = Livewire::test(BareHeaderListComponent::class)->instance()->headerControls();

        expect($controls['search'])->toBeFalse()
            ->and($controls['csv'])->toBeFalse()
            ->and($controls['secondary'])->toBe([])
            ->and($controls['primary'])->toBe([]);
    });

    it('strips every action for a picker, which only selects rows', function (): void {
        $controls = Livewire::test(CollapsingHeaderListComponent::class, ['returnsSelection' => true])
            ->instance()
            ->headerControls();

        expect($controls['primary'])->toBe([])
            ->and($controls['secondary'])->toBe([])
            ->and($controls['registry'])->toBe([]);
    });

    it('answers whether the list has a search field, a CSV export or secondary actions', function (): void {
        expect(Livewire::test(CollapsingHeaderListComponent::class)->instance()->hasCollapsibleControls())->toBeTrue()
            ->and(Livewire::test(BareHeaderListComponent::class)->instance()->hasCollapsibleControls())->toBeFalse();
    });

    it('counts the primary actions towards the header controls but not towards the injected group', function (): void {
        $host = Livewire::test(PrimaryOnlyHeaderListComponent::class)->instance();

        expect($host->hasCollapsibleControls())->toBeFalse()
            ->and($host->hasHeaderControls())->toBeTrue();
    });
});

describe('header rendering', function (): void {
    it('renders every control exactly once, so no wire:key or shortcut is duplicated', function (): void {
        $html = Livewire::test(CollapsingHeaderListComponent::class)->assertOk()->html();

        expect(mb_substr_count($html, 'wire:key="list-search"'))->toBe(1)
            ->and(mb_substr_count($html, 'wire:key="list-csv-export"'))->toBe(1)
            ->and(mb_substr_count($html, '$wire.exportSecondary(null, [])'))->toBe(1)
            ->and(mb_substr_count($html, '$wire.listAction(null, [])'))->toBe(1)
            ->and(mb_substr_count($html, '$refs.searchInput.focus()'))->toBe(1);
    });

    it('keeps the title row on a single line instead of stacking below lg', function (): void {
        $html = Livewire::test(CollapsingHeaderListComponent::class)->assertOk()->html();

        // x-noerd::title stacks below `lg` for detail headers; the list header opts
        // out via `row`, so its title element is flex at EVERY width — the buttons
        // stay on the title line and nothing wraps.
        assertElementHasClasses($html, ['font-semibold', 'text-slate-900', 'flex', 'h-[30px]']);
        assertNoElementHasClasses($html, ['font-semibold', 'text-slate-900', 'lg:flex', 'lg:h-[30px]']);
    });

    it('renders the filters on a second row that scrolls instead of wrapping or collapsing', function (): void {
        $html = Livewire::test(CollapsingHeaderListComponent::class)->assertOk()->html();

        expect($html)->toContain('wire:key="list-filter-row"')
            ->and($html)->not->toContain('drawer')
            ->and($html)->not->toContain('flex-wrap')
            ->and($html)->not->toContain('max-xl:')
            ->and($html)->not->toContain('xl:overflow-x-auto');
    });

    it('keeps the search field outside the scrolling strip and the filters inside it', function (): void {
        app(HeaderActionsRegistry::class)->registerListAction('header-actions-test::probe');

        $html = Livewire::test(CollapsingHeaderListComponent::class)->assertOk()->html();

        // Filter row order: search | scrolling strip with the filters | registry
        // actions — all of it above the table. (The pagination nav needs a real
        // paginator and is covered by ListPaginationTest.)
        $positions = array_map(
            static fn(string $needle): int|false => mb_strpos($html, $needle),
            ['wire:key="list-search"', 'overflow-x-scroll', 'listFilters.color', 'HA-PROBE:', '<table'],
        );

        expect($positions)->each->not->toBeFalse();
        expect($positions)->toBe(collect($positions)->sort()->values()->all());
    });

    it('puts CSV and the secondary actions on the title row next to the primary buttons', function (): void {
        $html = Livewire::test(CollapsingHeaderListComponent::class)->assertOk()->html();

        $filterRow = mb_strpos($html, 'wire:key="list-filter-row"');

        expect(mb_strpos($html, 'wire:key="list-csv-export"'))->toBeLessThan($filterRow)
            ->and(mb_strpos($html, '$wire.exportSecondary(null, [])'))->toBeLessThan($filterRow)
            ->and(mb_strpos($html, '$wire.listAction(null, [])'))->toBeLessThan($filterRow);
    });

    it('renders no filter row when there is nothing to put in it', function (): void {
        $html = Livewire::test(BareHeaderListComponent::class)->assertOk()->html();

        expect($html)->not->toContain('wire:key="list-filter-row"')
            ->and($html)->not->toContain('overflow-x-scroll');
    });

    it('opens the filter row for registry actions alone', function (): void {
        app(HeaderActionsRegistry::class)->registerListAction('header-actions-test::probe');

        $html = Livewire::test(BareHeaderListComponent::class)->assertOk()->html();

        expect($html)->toContain('wire:key="list-filter-row"')
            ->and($html)->toContain('HA-PROBE:')
            ->and($html)->not->toContain('overflow-x-scroll');
    });

});

/**
 * List host with its OWN header slot (the tab-panel/object-manager pattern): the
 * generic list-header never renders, so every control must come from the
 * modal-title injection. The embedded list uses hideHead so its swallowed
 * header slot cannot contribute anything.
 */
class CustomHeaderListComponent extends Component
{
    use NoerdList;

    public bool $controls = true;

    public ?string $controlsShow = null;

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'listConfig' => $this->buildList([['id' => 1, 'name' => 'Alice']], [
                'title' => 'Things',
                'actions' => [
                    [
                        'label' => 'New Thing',
                        'action' => 'listAction',
                    ],
                ],
                'columns' => [
                    ['field' => 'name', 'label' => 'Name'],
                ],
            ]),
        ];
    }

    public function render(): string
    {
        return <<<'BLADE'
            <x-noerd::page>
                <x-slot:header>
                    <x-noerd::modal-title :listControls="$controls" :listControlsShow="$controlsShow">Custom Header</x-noerd::modal-title>
                </x-slot:header>
                <x-noerd::list hideHead />
            </x-noerd::page>
            BLADE;
    }
}

/** List with a search field, a header filter, CSV export and both a secondary and a primary action. */
class CollapsingHeaderListComponent extends Component
{
    use NoerdList;

    /**
     * @return array{column: string, label: string, options: array<string, string>}
     */
    public function getColorListFilter(): array
    {
        return ['column' => 'color', 'label' => 'Color', 'options' => ['red' => 'Red']];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $this->enableCsvExport = true;

        return [
            'listConfig' => $this->buildList([['id' => 1, 'name' => 'Alice']], [
                'title' => 'Things',
                'actions' => [
                    ['label' => 'New Thing', 'action' => 'listAction'],
                    ['label' => 'Export', 'action' => 'exportSecondary', 'style' => 'secondary'],
                ],
                'columns' => [
                    ['field' => 'name', 'label' => 'Name'],
                ],
            ]),
        ];
    }

    public function exportSecondary(mixed $modelId = null, array $relations = []): void {}

    public function render(): string
    {
        return '<x-noerd::page><x-noerd::list /></x-noerd::page>';
    }
}

/** List whose only control is the primary action — nothing can collapse. */
class PrimaryOnlyHeaderListComponent extends Component
{
    use NoerdList;

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'listConfig' => $this->buildList([['id' => 1, 'name' => 'Alice']], [
                'title' => 'Things',
                'listSettings' => ['disableSearch' => true],
                'disableSearch' => true,
                'actions' => [['label' => 'New Thing', 'action' => 'listAction']],
                'columns' => [['field' => 'name', 'label' => 'Name']],
            ]),
        ];
    }

    public function render(): string
    {
        return '<x-noerd::page><x-noerd::list /></x-noerd::page>';
    }
}

/** List without filters, search, CSV or actions — nothing can collapse. */
class BareHeaderListComponent extends Component
{
    use NoerdList;

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'listConfig' => $this->buildList([['id' => 1, 'name' => 'Alice']], [
                'title' => 'Things',
                'listSettings' => ['disableSearch' => true],
                'disableSearch' => true,
                'columns' => [
                    ['field' => 'name', 'label' => 'Name'],
                ],
            ]),
        ];
    }

    public function render(): string
    {
        return '<x-noerd::page><x-noerd::list /></x-noerd::page>';
    }
}
