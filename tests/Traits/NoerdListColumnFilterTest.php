<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Traits\NoerdList;

uses(Tests\TestCase::class, RefreshDatabase::class);

function filterListIds(mixed $component): array
{
    return $component->instance()->visibleRowIds();
}

it('filters a text column with a like match by default', function (): void {
    $red = NoerdUser::factory()->create(['name' => 'Rotkohl']);
    NoerdUser::factory()->create(['name' => 'Blaukraut']);

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->call('setColumnFilter', 'name', 'rot');

    expect(filterListIds($component))->toBe([$red->id]);
});

it('filters a text column with an exact match on =', function (): void {
    NoerdUser::factory()->create(['name' => 'Rotkohl']);
    $exact = NoerdUser::factory()->create(['name' => 'Rot']);

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->call('setColumnFilter', 'name', '=Rot');

    expect(filterListIds($component))->toBe([$exact->id]);
});

it('filters a number column with comparison operators', function (): void {
    $first = NoerdUser::factory()->create();
    $second = NoerdUser::factory()->create();
    $third = NoerdUser::factory()->create();

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->call('setColumnFilter', 'id', '>' . $first->id);

    expect(filterListIds($component))->toEqualCanonicalizing([$second->id, $third->id]);

    $component->call('setColumnFilter', 'id', '<=' . $second->id);
    expect(filterListIds($component))->toEqualCanonicalizing([$first->id, $second->id]);
});

it('filters a bool column', function (): void {
    $admin = NoerdUser::factory()->create(['super_admin' => true]);
    NoerdUser::factory()->create(['super_admin' => false]);

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->call('setColumnFilter', 'super_admin', '1');

    expect(filterListIds($component))->toBe([$admin->id]);
});

it('filters a datetime column by day', function (): void {
    $old = NoerdUser::factory()->create(['created_at' => '2025-01-15 10:00:00']);
    $recent = NoerdUser::factory()->create(['created_at' => '2026-06-01 10:00:00']);

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->call('setColumnFilter', 'created_at', '>=2026-01-01');

    expect(filterListIds($component))->toBe([$recent->id]);

    $component->call('setColumnFilter', 'created_at', '2025-01-15');
    expect(filterListIds($component))->toBe([$old->id]);
});

it('combines multiple column filters with and', function (): void {
    $match = NoerdUser::factory()->create(['name' => 'Rotkohl', 'super_admin' => true]);
    NoerdUser::factory()->create(['name' => 'Rotwein', 'super_admin' => false]);
    NoerdUser::factory()->create(['name' => 'Blaukraut', 'super_admin' => true]);

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->call('setColumnFilter', 'name', 'rot')
        ->call('setColumnFilter', 'super_admin', '1');

    expect(filterListIds($component))->toBe([$match->id]);
});

it('combines column filters with the list search', function (): void {
    $match = NoerdUser::factory()->create(['name' => 'Rotkohl', 'email' => 'kohl@example.com']);
    NoerdUser::factory()->create(['name' => 'Rotwein', 'email' => 'wein@example.com']);

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->set('search', 'kohl')
        ->call('setColumnFilter', 'name', 'rot');

    expect(filterListIds($component))->toBe([$match->id]);
});

it('ignores filters on columns not present in the list yaml', function (): void {
    NoerdUser::factory()->count(2)->create();

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->call('setColumnFilter', 'password', '=secret');

    expect(filterListIds($component))->toHaveCount(2);
});

it('ignores filters on dotted fields', function (): void {
    NoerdUser::factory()->count(2)->create();

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->call('setColumnFilter', 'custom_attributes.color', 'rot');

    expect(filterListIds($component))->toHaveCount(2);
});

it('persists column filters per component in the session and restores them', function (): void {
    NoerdUser::factory()->create(['name' => 'Rotkohl']);
    NoerdUser::factory()->create(['name' => 'Blaukraut']);

    Livewire::test(TestableColumnFilterListComponent::class)
        ->call('setColumnFilter', 'name', 'rot');

    expect(session('listColumnFilters.testable-column-filter-list'))->toBe(['name' => 'rot']);

    $component = Livewire::test(TestableColumnFilterListComponent::class);
    expect($component->get('listColumnFilters'))->toBe(['name' => 'rot'])
        ->and(filterListIds($component))->toHaveCount(1);
});

it('clears a single column filter with an empty value', function (): void {
    NoerdUser::factory()->count(2)->create();

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->call('setColumnFilter', 'name', 'rot')
        ->call('clearColumnFilter', 'name');

    expect($component->get('listColumnFilters'))->toBe([])
        ->and(session('listColumnFilters.testable-column-filter-list'))->toBe([])
        ->and(filterListIds($component))->toHaveCount(2);
});

it('resets pagination when a column filter is set', function (): void {
    NoerdUser::factory()->count(3)->create();

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->set('perPage', 1)
        ->call('setPage', 2)
        ->call('setColumnFilter', 'name', 'a');

    expect($component->instance()->paginators['page'] ?? 1)->toBe(1);
});

it('clears column filters via clearAllListFilters', function (): void {
    NoerdUser::factory()->count(2)->create();

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->call('setColumnFilter', 'name', 'rot')
        ->call('clearAllListFilters');

    expect($component->get('listColumnFilters'))->toBe([])
        ->and(session('listColumnFilters.testable-column-filter-list'))->toBeNull()
        ->and(filterListIds($component))->toHaveCount(2);
});

it('does not apply column filters in compact embedded lists', function (): void {
    NoerdUser::factory()->create(['name' => 'Rotkohl']);
    NoerdUser::factory()->create(['name' => 'Blaukraut']);

    session(['listColumnFilters.testable-column-filter-list' => ['name' => 'rot']]);

    $component = Livewire::test(TestableColumnFilterListComponent::class, ['compact' => true]);

    expect(filterListIds($component))->toHaveCount(2);
});

it('exposes only real non-dotted yaml columns as filterable', function (): void {
    NoerdUser::factory()->create();

    $component = Livewire::test(TestableColumnFilterListComponent::class);
    $listConfig = $component->instance()->with()['listConfig'];

    expect($listConfig['filterableColumns'])->toBe([
        'name',
        'email',
        'super_admin',
        'id',
        'created_at',
    ]);
});

it('hydrates column filters from the url and persists them to the session', function (): void {
    $red = NoerdUser::factory()->create(['name' => 'Rotkohl']);
    NoerdUser::factory()->create(['name' => 'Blaukraut']);

    $component = Livewire::withUrlParams(['cf' => ['name' => 'rot']])
        ->test(TestableColumnFilterListComponent::class);

    expect($component->get('listColumnFilters'))->toBe(['name' => 'rot'])
        ->and(session('listColumnFilters.testable-column-filter-list'))->toBe(['name' => 'rot'])
        ->and(filterListIds($component))->toBe([$red->id]);
});

it('lets url column filters win over the session state', function (): void {
    NoerdUser::factory()->create(['name' => 'Rotkohl']);
    $blue = NoerdUser::factory()->create(['name' => 'Blaukraut']);

    session(['listColumnFilters.testable-column-filter-list' => ['name' => 'rot']]);

    $component = Livewire::withUrlParams(['cf' => ['name' => 'blau']])
        ->test(TestableColumnFilterListComponent::class);

    expect($component->get('listColumnFilters'))->toBe(['name' => 'blau'])
        ->and(filterListIds($component))->toBe([$blue->id]);
});

it('ignores url column filters on compact embedded lists', function (): void {
    NoerdUser::factory()->create(['name' => 'Rotkohl']);
    NoerdUser::factory()->create(['name' => 'Blaukraut']);

    $component = Livewire::withUrlParams(['cf' => ['name' => 'rot']])
        ->test(TestableColumnFilterListComponent::class, ['compact' => true]);

    expect(filterListIds($component))->toHaveCount(2);
});

it('renders funnel buttons only for filterable columns', function (): void {
    $tenant = Noerd\Models\Tenant::factory()->create();
    $user = NoerdUser::factory()->create();
    Noerd\Helpers\TenantHelper::setSelectedTenantId($tenant->id);
    Noerd\Helpers\TenantHelper::setSelectedApp('SETUP');
    test()->actingAs($user);

    $html = Livewire::test(TestableColumnFilterRenderComponent::class)->html();

    expect($html)->toContain('column-filter-name-')
        ->toContain('setColumnFilter')
        ->not->toContain('column-filter-custom_attributes.color');
});

it('renders no funnel buttons in compact mode', function (): void {
    $tenant = Noerd\Models\Tenant::factory()->create();
    $user = NoerdUser::factory()->create();
    Noerd\Helpers\TenantHelper::setSelectedTenantId($tenant->id);
    Noerd\Helpers\TenantHelper::setSelectedApp('SETUP');
    test()->actingAs($user);

    $html = Livewire::test(TestableColumnFilterRenderComponent::class, ['compact' => true])->html();

    expect($html)->not->toContain('column-filter-name-');
});

/**
 * List component with an inline YAML config over the noerd_users table.
 */
class TestableColumnFilterListComponent extends Component
{
    use NoerdList;

    public const COMPONENT = 'testable-column-filter-list';

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Testable Users',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
                ['field' => 'email', 'label' => 'Email'],
                ['field' => 'super_admin', 'label' => 'Admin'],
                ['field' => 'id', 'label' => 'Id'],
                ['field' => 'created_at', 'label' => 'Created'],
                ['field' => 'custom_attributes.color', 'label' => 'Color'],
            ],
        ];
    }

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
}

/**
 * Same list, but rendering the real list Blade so the header funnels appear.
 */
class TestableColumnFilterRenderComponent extends TestableColumnFilterListComponent
{
    public function render(): string
    {
        return '<div><x-noerd::list /></div>';
    }
}
