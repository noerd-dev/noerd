<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

/*
 | What the browser may CALL and SET on a list or detail beyond the form data:
 | helper methods stay server-side, embedding switches stay with the opener,
 | a row click runs only a declared row action, and the sort follows the
 | list's own columns. Synthetic fixtures only.
 */

class ZzActionSurfaceList extends Component
{
    use NoerdList;

    /** @var list<int|string> */
    public array $sideEffects = [];

    public function with(): array
    {
        return [
            'listConfig' => $this->buildList(
                $this->listQuery(Tenant::class)->paginate($this->perPage),
            ),
        ];
    }

    /** Public, but no row action: a mount argument must not route a row click here. */
    public function zzSideEffect(int|string $modelId): void
    {
        $this->sideEffects[] = $modelId;
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-action-surface-list';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Zz Action Surface',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
            ],
        ];
    }
}

class ZzActionSurfaceRowActionList extends ZzActionSurfaceList
{
    protected function rowActionMethods(): array
    {
        return [...parent::rowActionMethods(), 'zzSideEffect'];
    }
}

class ZzActionSurfaceDetail extends Component
{
    use NoerdDetail;

    public $detailModel = Tenant::class;

    public ?string $detailPrimary = 'tenantId';

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-action-surface-detail';
    }
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::component('zz-action-surface-list', ZzActionSurfaceList::class);
    Livewire::component('zz-action-surface-row-action-list', ZzActionSurfaceRowActionList::class);
    Livewire::component('zz-action-surface-detail', ZzActionSurfaceDetail::class);
});

it('refuses browser calls to server-only helper methods', function (string $component, string $method): void {
    expect(fn() => Livewire::test($component)->call($method))
        ->toThrow(MethodNotFoundException::class);
})->with([
    'listData' => ['zz-action-surface-list', 'listData'],
    'builtListConfig' => ['zz-action-surface-list', 'builtListConfig'],
    'renderingNoerdList' => ['zz-action-surface-list', 'renderingNoerdList'],
    'queryStringNoerdPage' => ['zz-action-surface-detail', 'queryStringNoerdPage'],
    'validateFromLayout' => ['zz-action-surface-detail', 'validateFromLayout'],
    'resolvePicklistOptions' => ['zz-action-surface-detail', 'resolvePicklistOptions'],
]);

it('keeps the server-only helpers callable from PHP', function (): void {
    $listConfig = Livewire::test('zz-action-surface-list')->instance()->builtListConfig();

    expect($listConfig)->toHaveKey('rows');
});

it('rejects client updates of the embedding switches', function (string $component, string $property, mixed $value): void {
    expect(fn() => Livewire::test($component)->set($property, $value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    'compact' => ['zz-action-surface-list', 'compact', true],
    'returnsSelection' => ['zz-action-surface-list', 'returnsSelection', true],
    'multiSelect' => ['zz-action-surface-list', 'multiSelect', true],
    'context' => ['zz-action-surface-list', 'context', 'detailData.owner_id'],
    'embedded' => ['zz-action-surface-detail', 'embedded', true],
    'quickCreate' => ['zz-action-surface-detail', 'quickCreate', true],
    'detailPrimary' => ['zz-action-surface-detail', 'detailPrimary', 'otherId'],
]);

it('runs only a declared row action on a row click', function (): void {
    $tenant = Tenant::factory()->create();

    Livewire::test('zz-action-surface-list', ['listActionMethod' => 'zzSideEffect'])
        ->call('openListRow', $tenant->id)
        ->assertSet('sideEffects', []);

    Livewire::test('zz-action-surface-row-action-list', ['listActionMethod' => 'zzSideEffect'])
        ->call('openListRow', $tenant->id)
        ->assertSet('sideEffects', [$tenant->id]);
});

it('sorts only by a column of the list', function (): void {
    // uuid order is the reverse of the id order.
    $first = Tenant::factory()->create(['name' => 'B', 'uuid' => 'zzz']);
    $second = Tenant::factory()->create(['name' => 'A', 'uuid' => 'aaa']);

    $byUuid = Livewire::test('zz-action-surface-list')
        ->set('sortField', 'uuid')
        ->set('sortAsc', true)
        ->instance()->visibleRowIds();

    $byName = Livewire::test('zz-action-surface-list')
        ->set('sortField', 'name')
        ->set('sortAsc', true)
        ->instance()->visibleRowIds();

    expect(array_values(array_intersect($byUuid, [$first->id, $second->id])))->toBe([$first->id, $second->id])
        ->and(array_values(array_intersect($byName, [$first->id, $second->id])))->toBe([$second->id, $first->id]);
});
