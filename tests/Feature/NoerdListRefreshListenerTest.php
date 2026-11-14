<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The modal stack refreshes the opener with `refreshList-{source}`, where the
 | source is the opener's Livewire name shortened after the last dot. A list
 | that renders another list's YAML (listConfigComponent()) must still hear it.
 */

beforeEach(function (): void {
    Livewire::component('zz-refresh.zz-own-name-list', ZzRefreshForeignConfigList::class);
});

it('refreshes a list under its own name although it renders another config', function (string $event): void {
    Livewire::test('zz-refresh.zz-own-name-list')
        ->dispatch($event)
        ->assertDispatched('$refresh');
})->with([
    'the shortened name the modal stack sends' => 'refreshList-zz-own-name-list',
    'the full component name' => 'refreshList-zz-refresh.zz-own-name-list',
    'the config name a paired detail sends' => 'refreshList-zz-foreign-config-list',
]);

it('does not listen for the refresh of another list', function (): void {
    Livewire::test('zz-refresh.zz-own-name-list')
        ->dispatch('refreshList-zz-unrelated-list');
})->throws(\Livewire\Exceptions\EventHandlerDoesNotExist::class);

class ZzRefreshForeignConfigList extends Component
{
    use NoerdList;

    /**
     * @return array<string, mixed>
     */
    public function listData(): array
    {
        return ['rows' => []];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function listConfigComponent(): string
    {
        return 'zz-foreign-config-list';
    }
}
