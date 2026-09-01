<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Helpers\AccessHelper;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Mechanics of the generic multi-select/picker/bulk features on NoerdList:
 | select-all toggling, the picker confirmation events, and the YAML-guarded
 | generic deleteSelected() bulk action. Synthetic components with inline
 | configs — never assertions against shipped YAML.
 */

/** List whose YAML declares the generic bulk delete. */
class ZzBulkDeleteListComponent extends Component
{
    use NoerdList;

    public function with(): array
    {
        return [
            'listConfig' => $this->buildList(
                $this->listQuery(NoerdUser::class)->paginate($this->perPage),
            ),
        ];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-bulk-delete-list';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Bulk Users',
            'multiSelect' => true,
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
            ],
            'bulkActions' => [
                ['label' => 'Delete', 'action' => 'deleteSelected'],
            ],
        ];
    }
}

/** Identical list WITHOUT the bulk action declared — deleteSelected must refuse. */
class ZzNoBulkListComponent extends Component
{
    use NoerdList;

    public function with(): array
    {
        return [
            'listConfig' => $this->buildList(
                $this->listQuery(NoerdUser::class)->paginate($this->perPage),
            ),
        ];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-no-bulk-list';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Plain Users',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
            ],
        ];
    }
}

it('toggles a single record in and out of the selection', function (): void {
    $component = Livewire::test(ZzBulkDeleteListComponent::class)
        ->call('toggleRecordSelection', 7)
        ->assertSet('selectedRecordIds', [7])
        ->call('toggleRecordSelection', '7')
        ->assertSet('selectedRecordIds', []);
});

it('selects and deselects every visible row via toggleSelectAllVisible', function (): void {
    $users = NoerdUser::factory()->count(3)->create();

    $component = Livewire::test(ZzBulkDeleteListComponent::class)
        ->call('toggleSelectAllVisible');

    expect($component->get('selectedRecordIds'))
        ->toEqualCanonicalizing($users->pluck('id')->all());

    $component->call('toggleSelectAllVisible');

    expect($component->get('selectedRecordIds'))->toBe([]);
});

it('confirms a picker selection with ids, context and a modal close', function (): void {
    Livewire::test(ZzBulkDeleteListComponent::class, ['returnsSelection' => true, 'context' => 'zzPicker'])
        ->call('toggleRecordSelection', 3)
        ->call('toggleRecordSelection', 5)
        ->call('confirmRecordSelection')
        ->assertDispatched('recordsSelected', ids: [3, 5], context: 'zzPicker')
        ->assertDispatched('closeTopModal');
});

it('ticks a row instead of opening it while in picker mode', function (): void {
    $user = NoerdUser::factory()->create();

    Livewire::test(ZzBulkDeleteListComponent::class, ['returnsSelection' => true])
        ->call('openListRow', $user->id)
        ->assertSet('selectedRecordIds', [$user->id])
        ->assertNotDispatched('noerdModal');
});

it('bulk-deletes the selected records through the generic deleteSelected action', function (): void {
    $users = NoerdUser::factory()->count(3)->create();
    $keep = $users->pop();

    $component = Livewire::test(ZzBulkDeleteListComponent::class)
        ->set('selectedRecordIds', $users->pluck('id')->all())
        ->call('deleteSelected')
        ->assertSet('selectedRecordIds', []);

    expect(NoerdUser::query()->pluck('id')->all())->toBe([$keep->id]);
});

it('refuses deleteSelected when the list config declares no such bulk action', function (): void {
    $users = NoerdUser::factory()->count(2)->create();

    Livewire::test(ZzNoBulkListComponent::class)
        ->set('selectedRecordIds', $users->pluck('id')->all())
        ->call('deleteSelected');

    expect(NoerdUser::query()->count())->toBe(2);
});

it('refuses deleteSelected for a delete-denied user', function (): void {
    Gate::define(AccessHelper::OBJECT_DELETE_GATE, fn(?NoerdUser $user, string $modelClass): bool => false);

    $users = NoerdUser::factory()->count(2)->create();

    Livewire::test(ZzBulkDeleteListComponent::class)
        ->set('selectedRecordIds', $users->pluck('id')->all())
        ->call('deleteSelected');

    expect(NoerdUser::query()->count())->toBe(2);
});

it('fires the model events for every bulk-deleted record', function (): void {
    $deleted = [];
    NoerdUser::deleted(function (NoerdUser $model) use (&$deleted): void {
        $deleted[] = $model->id;
    });

    $users = NoerdUser::factory()->count(2)->create();

    Livewire::test(ZzBulkDeleteListComponent::class)
        ->set('selectedRecordIds', $users->pluck('id')->all())
        ->call('deleteSelected');

    expect($deleted)->toEqualCanonicalizing($users->pluck('id')->all());
});

it('clamps and persists an updated page size', function (): void {
    $component = Livewire::test(ZzBulkDeleteListComponent::class)
        ->set('perPage', 999999);

    expect($component->get('perPage'))->toBe(200)
        ->and(session('listPerPage.zz-bulk-delete-list'))->toBe(200);
});

it('re-renders when the refreshList event for the list name arrives', function (): void {
    $component = Livewire::test(ZzBulkDeleteListComponent::class);

    // The listener name embeds the component name (see NoerdList::getListeners()).
    $component->dispatch('refreshList-' . $component->instance()->getComponentName())
        ->assertDispatched('$refresh');
});

/** List narrowed in its OWN listData() — the shipped pattern of tenants-list. */
class ZzNarrowedBulkListComponent extends Component
{
    use NoerdList;

    public $listModel = NoerdUser::class;

    public array $allowedIds = [];

    public function listData(): array
    {
        return $this->buildList(
            $this->listQuery(NoerdUser::class)
                ->whereIn('id', $this->allowedIds)
                ->paginate($this->perPage),
        );
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-narrowed-bulk-list';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Narrowed Users',
            'multiSelect' => true,
            'columns' => [['field' => 'name', 'label' => 'Name']],
            'bulkActions' => [['label' => 'Delete', 'action' => 'deleteSelected']],
        ];
    }
}

it('never bulk-deletes a record the list itself does not yield', function (): void {
    $mine = NoerdUser::factory()->create();
    $foreign = NoerdUser::factory()->create();

    Livewire::test(ZzNarrowedBulkListComponent::class, ['allowedIds' => [$mine->id]])
        // A crafted selection containing an id the narrowed list never shows.
        ->set('selectedRecordIds', [$mine->id, $foreign->id])
        ->call('deleteSelected');

    expect(NoerdUser::find($mine->id))->toBeNull()
        ->and(NoerdUser::find($foreign->id))->not->toBeNull();
});
