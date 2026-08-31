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

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    // Installed modules register themselves at boot, so start from a clean registry.
    app()->instance(HeaderActionsRegistry::class, new HeaderActionsRegistry());
    Livewire::addNamespace('header-actions-test', viewPath: __DIR__ . '/../Feature/fixtures/header-actions');
});

it('injects search and the YAML actions into a custom header of a list host', function (): void {
    $html = Livewire::test(CustomHeaderListComponent::class)->assertOk()->html();

    expect($html)->toContain('Custom Header')
        ->toContain('wire:model.live.debounce.300ms="search"')
        ->toContain('$wire.listAction(null, [])')
        ->toContain('New Thing');
});

it('renders every control exactly once for a standard list', function (): void {
    $html = Livewire::test(StandardHeaderListComponent::class)->assertOk()->html();

    // The generic header renders the controls itself (:listControls="false" on its
    // modal-title) — the counts prove the modal-title injection did not add a second
    // copy on top, and that the drawer reuses the same elements rather than cloning them.
    expect(mb_substr_count($html, 'wire:model.live.debounce.300ms="search"'))->toBe(1)
        ->and(mb_substr_count($html, 'wire:key="list-search"'))->toBe(1)
        ->and(mb_substr_count($html, '$wire.listAction(null, [])'))->toBe(1);
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

/** Standard list: the header comes from the generic list-header — controls must not double up. */
class StandardHeaderListComponent extends Component
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
        return '<x-noerd::page><x-noerd::list /></x-noerd::page>';
    }
}
