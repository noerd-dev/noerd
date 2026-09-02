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
 | list host with its OWN header slot gets them injected, and how they collapse
 | into the drawer instead of wrapping onto a second row.
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

    it('keeps room for the focus ring inside the scrolling controls row', function (): void {
        // `xl:overflow-x-auto` makes the header row scroll rather than wrap, and a scroll
        // container clips BOTH axes. Without padding the row is exactly as tall as its
        // 32px controls, so the search field's focus ring is cut down to a black sliver.
        $html = Livewire::test(CollapsingHeaderListComponent::class)->assertOk()->html();

        preg_match_all('/class="([^"]*xl:overflow-x-auto[^"]*)"/', $html, $matches);

        expect($matches[1])->not->toBeEmpty();

        foreach ($matches[1] as $classAttribute) {
            expect($classAttribute)->toContain('xl:p-1.5');
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

    it('answers whether anything collapses into the drawer', function (): void {
        expect(Livewire::test(CollapsingHeaderListComponent::class)->instance()->hasCollapsibleControls())->toBeTrue()
            ->and(Livewire::test(BareHeaderListComponent::class)->instance()->hasCollapsibleControls())->toBeFalse();
    });

    it('counts the primary actions towards the header controls but not towards the drawer', function (): void {
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

    it('keeps the header on a single row instead of stacking below lg', function (): void {
        $html = Livewire::test(CollapsingHeaderListComponent::class)->assertOk()->html();

        // x-noerd::title stacks below `lg` for detail headers; the list header opts
        // out via `row`, so its title element is flex at EVERY width and the controls
        // collapse into the drawer instead of wrapping.
        assertElementHasClasses($html, ['font-semibold', 'text-slate-900', 'flex', 'h-[30px]']);
        assertNoElementHasClasses($html, ['font-semibold', 'text-slate-900', 'lg:flex', 'lg:h-[30px]']);
    });

    it('opens the drawer from a funnel button that only exists below lg', function (): void {
        $html = Livewire::test(CollapsingHeaderListComponent::class)->assertOk()->html();

        // Below `lg` the controls become a drawer opened by the funnel button; the
        // header row itself never wraps onto a second line.
        expect($html)->toContain('x-data="{ drawer: false }"')
            ->and($html)->toContain('drawer = true')
            ->and($html)->not->toContain('flex-wrap');
    });

    it('offers no drawer when there is nothing to collapse', function (): void {
        $html = Livewire::test(BareHeaderListComponent::class)->assertOk()->html();

        expect($html)->not->toContain('x-data="{ drawer: false }"')
            ->and($html)->not->toContain('drawer = true');
    });

    it('counts an active search in the funnel badge, which is all the drawer leaves visible', function (): void {
        $html = Livewire::test(CollapsingHeaderListComponent::class)->set('search', 'rot')->html();

        assertElementHasClasses($html, ['bg-brand-primary', 'px-1', 'text-[10px]']);
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

/** List with a search field, CSV export and both a secondary and a primary action. */
class CollapsingHeaderListComponent extends Component
{
    use NoerdList;

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
