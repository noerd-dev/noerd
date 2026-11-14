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
 | A list header action opens a modal through modalRoute: (with modalComponent:
 | as the fallback) or modalComponent: alone — the same vocabulary as every other
 | modal target. An action that opens nothing is not offered.
 */

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
    registerTestLivewireRoute('zz-list-action/{modelId}', 'noerd-test::theme-test', 'zz.list.action.detail');
    Livewire::component('zz-modal-target-list', ZzModalTargetListComponent::class);
});

it('opens a modalRoute action as a route modal with the component as fallback', function (): void {
    $html = Livewire::test('zz-modal-target-list')->html();

    expect($html)->toContain('$modalRoute(')
        ->toContain('zz.list.action.detail')
        ->toContain('zz::fallback-detail')
        ->toContain('New Record');
});

it('opens a modalComponent action as a component modal', function (): void {
    expect(Livewire::test('zz-modal-target-list')->html())
        ->toContain('$modal(')
        ->toContain('zz::import-modal')
        ->toContain('Import Records');
});

it('falls back to the component when the modalRoute is not registered', function (): void {
    $controls = \Livewire\invade(Livewire::test('zz-modal-target-list')->instance())->headerControls();

    expect(array_column($controls['primary'], 'label'))->toContain('Fallback Only');
});

it('drops an action that opens nothing and keeps the YAML position of the others', function (): void {
    $controls = \Livewire\invade(Livewire::test('zz-modal-target-list')->instance())->headerControls();
    $labels = array_column([...$controls['primary'], ...$controls['secondary']], 'label');

    expect($labels)->not->toContain('Ghost Route')
        ->not->toContain('Earlier Vocabulary')
        ->and(array_keys($controls['primary']))->toBe([0, 1, 2]);
});

class ZzModalTargetListComponent extends Component
{
    use NoerdList;

    /**
     * @return array<string, mixed>
     */
    public function listData(): array
    {
        return $this->buildList([['id' => 1, 'name' => 'Alice']], [
            'title' => 'Things',
            'actions' => [
                ['label' => 'New Record', 'modalRoute' => 'zz.list.action.detail', 'modalComponent' => 'zz::fallback-detail'],
                ['label' => 'Import Records', 'modalComponent' => 'zz::import-modal', 'heroicon' => 'arrow-up-tray'],
                ['label' => 'Fallback Only', 'modalRoute' => 'zz.route.that.does.not.exist', 'modalComponent' => 'zz::fallback-detail'],
                ['label' => 'Ghost Route', 'modalRoute' => 'zz.route.that.does.not.exist'],
                ['label' => 'Earlier Vocabulary', 'route' => 'zz.list.action.detail'],
            ],
            'columns' => [['field' => 'name', 'label' => 'Name']],
        ]);
    }

    public function render(): string
    {
        return '<x-noerd::page><x-noerd::list /></x-noerd::page>';
    }
}
