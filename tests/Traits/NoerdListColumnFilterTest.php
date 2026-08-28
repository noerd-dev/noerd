<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

function filterListIds(mixed $component): array
{
    return $component->instance()->visibleRowIds();
}

function createColumnFilterJsonItemsTable(): void
{
    if (Schema::hasTable('column_filter_json_items')) {
        return;
    }

    Schema::create('column_filter_json_items', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->json('custom_attributes')->nullable();
    });
}

function createColumnFilterRelationTables(): void
{
    if (Schema::hasTable('column_filter_records')) {
        return;
    }

    Schema::create('column_filter_owners', function (Blueprint $table): void {
        $table->id();
        $table->string('city');
        $table->integer('rating')->default(0);
    });

    Schema::create('column_filter_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('owner_id')->nullable();
    });
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

it('ignores filters on dotted fields without a json base column', function (): void {
    NoerdUser::factory()->count(2)->create();

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->call('setColumnFilter', 'custom_attributes.color', 'rot');

    expect(filterListIds($component))->toHaveCount(2);
});

it('ignores filters on dotted fields whose base column is not json cast', function (): void {
    NoerdUser::factory()->count(2)->create();

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->call('setColumnFilter', 'email.domain', 'example');

    expect(filterListIds($component))->toHaveCount(2);
});

it('filters a custom attribute path with a like match by default', function (): void {
    createColumnFilterJsonItemsTable();
    $red = ColumnFilterJsonItem::create(['name' => 'A', 'custom_attributes' => ['color' => 'dunkelrot']]);
    ColumnFilterJsonItem::create(['name' => 'B', 'custom_attributes' => ['color' => 'blau']]);
    ColumnFilterJsonItem::create(['name' => 'C', 'custom_attributes' => null]);

    $component = Livewire::test(TestableJsonColumnFilterListComponent::class)
        ->call('setColumnFilter', 'custom_attributes.color', 'rot');

    expect(filterListIds($component))->toBe([$red->id]);
});

it('filters a custom attribute path with an exact match on =', function (): void {
    createColumnFilterJsonItemsTable();
    ColumnFilterJsonItem::create(['name' => 'A', 'custom_attributes' => ['color' => 'dunkelrot']]);
    $exact = ColumnFilterJsonItem::create(['name' => 'B', 'custom_attributes' => ['color' => 'rot']]);

    $component = Livewire::test(TestableJsonColumnFilterListComponent::class)
        ->call('setColumnFilter', 'custom_attributes.color', '=rot');

    expect(filterListIds($component))->toBe([$exact->id]);
});

it('filters a numeric custom attribute path with comparison operators', function (): void {
    createColumnFilterJsonItemsTable();
    $cheap = ColumnFilterJsonItem::create(['name' => 'A', 'custom_attributes' => ['price' => 5]]);
    $expensive = ColumnFilterJsonItem::create(['name' => 'B', 'custom_attributes' => ['price' => 20]]);

    $component = Livewire::test(TestableJsonColumnFilterListComponent::class)
        ->call('setColumnFilter', 'custom_attributes.price', '>10');

    expect(filterListIds($component))->toBe([$expensive->id]);

    $component->call('setColumnFilter', 'custom_attributes.price', '<=5');
    expect(filterListIds($component))->toBe([$cheap->id]);
});

it('filters a badge custom attribute path by option value', function (): void {
    createColumnFilterJsonItemsTable();
    $weekly = ColumnFilterJsonItem::create(['name' => 'A', 'custom_attributes' => ['cycle' => 'weekly']]);
    ColumnFilterJsonItem::create(['name' => 'B', 'custom_attributes' => ['cycle' => 'monthly']]);

    $component = Livewire::test(TestableJsonColumnFilterListComponent::class)
        ->call('setColumnFilter', 'custom_attributes.cycle', 'weekly');

    expect(filterListIds($component))->toBe([$weekly->id]);
});

it('exposes json custom attribute paths as filterable', function (): void {
    createColumnFilterJsonItemsTable();
    ColumnFilterJsonItem::create(['name' => 'A', 'custom_attributes' => []]);

    $component = Livewire::test(TestableJsonColumnFilterListComponent::class);
    $listConfig = $component->instance()->with()['listConfig'];

    expect($listConfig['filterableColumns'])->toBe([
        'name',
        'custom_attributes.color',
        'custom_attributes.price',
        'custom_attributes.cycle',
    ]);
});

it('filters a relation column path with a like match by default', function (): void {
    createColumnFilterRelationTables();
    $berlin = ColumnFilterOwner::create(['city' => 'Berlin', 'rating' => 5]);
    $hamburg = ColumnFilterOwner::create(['city' => 'Hamburg', 'rating' => 9]);
    $match = ColumnFilterRecord::create(['name' => 'A', 'owner_id' => $berlin->id]);
    ColumnFilterRecord::create(['name' => 'B', 'owner_id' => $hamburg->id]);
    ColumnFilterRecord::create(['name' => 'C', 'owner_id' => null]);

    $component = Livewire::test(TestableRelationColumnFilterListComponent::class)
        ->call('setColumnFilter', 'owner.city', 'erli');

    expect(filterListIds($component))->toBe([$match->id]);
});

it('filters a relation column path with an exact match on =', function (): void {
    createColumnFilterRelationTables();
    $berlin = ColumnFilterOwner::create(['city' => 'Berlin', 'rating' => 5]);
    $berlinSuburb = ColumnFilterOwner::create(['city' => 'Berlin-Spandau', 'rating' => 3]);
    $exact = ColumnFilterRecord::create(['name' => 'A', 'owner_id' => $berlin->id]);
    ColumnFilterRecord::create(['name' => 'B', 'owner_id' => $berlinSuburb->id]);

    $component = Livewire::test(TestableRelationColumnFilterListComponent::class)
        ->call('setColumnFilter', 'owner.city', '=Berlin');

    expect(filterListIds($component))->toBe([$exact->id]);
});

it('types a relation column from the schema of the related table', function (): void {
    createColumnFilterRelationTables();
    $low = ColumnFilterOwner::create(['city' => 'Berlin', 'rating' => 3]);
    $high = ColumnFilterOwner::create(['city' => 'Hamburg', 'rating' => 9]);
    ColumnFilterRecord::create(['name' => 'A', 'owner_id' => $low->id]);
    $match = ColumnFilterRecord::create(['name' => 'B', 'owner_id' => $high->id]);

    $component = Livewire::test(TestableRelationColumnFilterListComponent::class)
        ->call('setColumnFilter', 'owner.rating', '>5');

    expect(filterListIds($component))->toBe([$match->id]);
});

it('exposes relation column paths as filterable but ignores unknown ones', function (): void {
    createColumnFilterRelationTables();
    ColumnFilterRecord::create(['name' => 'A', 'owner_id' => null]);

    $component = Livewire::test(TestableRelationColumnFilterListComponent::class);
    $listConfig = $component->instance()->with()['listConfig'];

    expect($listConfig['filterableColumns'])->toBe([
        'name',
        'owner.city',
        'owner.rating',
    ]);
});

it('ignores filters on unresolvable relation paths', function (): void {
    createColumnFilterRelationTables();
    $berlin = ColumnFilterOwner::create(['city' => 'Berlin', 'rating' => 5]);
    ColumnFilterRecord::create(['name' => 'A', 'owner_id' => $berlin->id]);
    ColumnFilterRecord::create(['name' => 'B', 'owner_id' => null]);

    $component = Livewire::test(TestableRelationColumnFilterListComponent::class)
        ->call('setColumnFilter', 'nope.city', 'Berlin');

    expect(filterListIds($component))->toHaveCount(2);
});

it('hydrates a dotted relation filter from the url', function (): void {
    createColumnFilterRelationTables();
    $berlin = ColumnFilterOwner::create(['city' => 'Berlin', 'rating' => 5]);
    $hamburg = ColumnFilterOwner::create(['city' => 'Hamburg', 'rating' => 9]);
    $match = ColumnFilterRecord::create(['name' => 'A', 'owner_id' => $berlin->id]);
    ColumnFilterRecord::create(['name' => 'B', 'owner_id' => $hamburg->id]);

    $component = Livewire::withUrlParams(['cf' => ['owner.city' => 'Berlin']])
        ->test(TestableRelationColumnFilterListComponent::class);

    expect($component->get('listColumnFilters'))->toBe(['owner.city' => 'Berlin'])
        ->and(filterListIds($component))->toBe([$match->id]);
});

it('keeps relation column paths unsortable', function (): void {
    createColumnFilterRelationTables();
    ColumnFilterRecord::create(['name' => 'A', 'owner_id' => null]);

    $component = Livewire::test(TestableRelationColumnFilterListComponent::class)
        ->call('sortBy', 'owner.city');

    expect($component->get('sortField'))->toBe('id');
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
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create();
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('SETUP');
    test()->actingAs($user);

    $html = Livewire::test(TestableColumnFilterRenderComponent::class)->html();

    expect($html)->toContain('column-filter-name-')
        ->toContain('setColumnFilter')
        ->not->toContain('column-filter-custom_attributes.color');
});

it('renders funnel buttons for json custom attribute columns', function (): void {
    createColumnFilterJsonItemsTable();
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create();
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('SETUP');
    test()->actingAs($user);

    $html = Livewire::test(TestableJsonColumnFilterRenderComponent::class)->html();

    expect($html)->toContain('column-filter-custom_attributes.color-');
});

it('renders no funnel buttons in compact mode', function (): void {
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create();
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('SETUP');
    test()->actingAs($user);

    $html = Livewire::test(TestableColumnFilterRenderComponent::class, ['compact' => true])->html();

    expect($html)->not->toContain('column-filter-name-');
});

it('resolves active column filters into labeled header chips', function (): void {
    NoerdUser::factory()->create(['name' => 'Rotkohl', 'super_admin' => true]);

    $component = Livewire::test(TestableColumnFilterChipListComponent::class)
        ->call('setColumnFilter', 'name', 'rot')
        ->call('setColumnFilter', 'super_admin', '1')
        ->call('setColumnFilter', 'email', 'open@example.com');

    filterListIds($component);

    expect($component->instance()->activeColumnFilterChips())->toBe([
        ['field' => 'name', 'label' => 'Name', 'value' => 'rot'],
        ['field' => 'super_admin', 'label' => 'Admin', 'value' => __('Yes')],
        ['field' => 'email', 'label' => 'Status', 'value' => __('Open')],
    ]);
});

it('resolves a bool zero filter chip to No', function (): void {
    NoerdUser::factory()->create(['super_admin' => false]);

    $component = Livewire::test(TestableColumnFilterChipListComponent::class)
        ->call('setColumnFilter', 'super_admin', '0');

    filterListIds($component);

    expect($component->instance()->activeColumnFilterChips())->toBe([
        ['field' => 'super_admin', 'label' => 'Admin', 'value' => __('No')],
    ]);
});

it('returns no chips without active column filters', function (): void {
    NoerdUser::factory()->create();

    $component = Livewire::test(TestableColumnFilterChipListComponent::class);

    expect($component->instance()->activeColumnFilterChips())->toBe([]);
});

it('renders active column filter chips in the list header', function (): void {
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create();
    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('SETUP');
    test()->actingAs($user);

    $html = Livewire::test(TestableColumnFilterPageRenderComponent::class)
        ->call('setColumnFilter', 'name', 'rot')
        ->html();

    expect($html)->toContain('column-filter-chip-name')
        ->toContain('clearColumnFilter');
});

/**
 * List component with an inline YAML config over the noerd_users table.
 */
class TestableColumnFilterListComponent extends Component
{
    use NoerdList;

    public const COMPONENT = 'testable-column-filter-list';

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
                ['field' => 'email.domain', 'label' => 'Domain'],
            ],
        ];
    }
}

/**
 * Model over a runtime-created table with a JSON custom_attributes column, so
 * the JSON-path column filters can be exercised without touching real tables.
 */
class ColumnFilterJsonItem extends Model
{
    public $timestamps = false;

    protected $table = 'column_filter_json_items';

    protected $guarded = [];

    protected $casts = [
        'custom_attributes' => 'array',
    ];
}

/**
 * List component over the JSON model: one plain column plus custom-attribute
 * paths in every filterable flavour (text, number, badge with options).
 */
class TestableJsonColumnFilterListComponent extends Component
{
    use NoerdList;

    public const COMPONENT = 'testable-json-column-filter-list';

    public function with(): array
    {
        return [
            'listConfig' => $this->buildList(
                $this->listQuery(ColumnFilterJsonItem::class)->paginate($this->perPage),
            ),
        ];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Testable Json Items',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
                ['field' => 'custom_attributes.color', 'label' => 'Color'],
                ['field' => 'custom_attributes.price', 'label' => 'Price', 'type' => 'number'],
                [
                    'field' => 'custom_attributes.cycle',
                    'label' => 'Cycle',
                    'type' => 'badge',
                    'options' => [
                        ['value' => 'weekly', 'label' => 'Weekly'],
                        ['value' => 'monthly', 'label' => 'Monthly'],
                    ],
                ],
            ],
        ];
    }
}

/**
 * Related model over a runtime-created table, so relation-path column filters can
 * be exercised without touching real tables.
 */
class ColumnFilterOwner extends Model
{
    public $timestamps = false;

    protected $table = 'column_filter_owners';

    protected $guarded = [];
}

/**
 * Owning model with a belongsTo relation, plus a method that returns no relation
 * at all — its dotted path must stay out of the filterable columns.
 */
class ColumnFilterRecord extends Model
{
    public $timestamps = false;

    protected $table = 'column_filter_records';

    protected $guarded = [];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(ColumnFilterOwner::class, 'owner_id');
    }

    public function nope(): string
    {
        return 'not a relation';
    }
}

/**
 * List component over the relation model: one plain column, two relation paths
 * (text and a number typed from the related table's schema) and one path whose
 * base method is no relation at all.
 */
class TestableRelationColumnFilterListComponent extends Component
{
    use NoerdList;

    public const COMPONENT = 'testable-relation-column-filter-list';

    public function with(): array
    {
        return [
            'listConfig' => $this->buildList(
                $this->listQuery(ColumnFilterRecord::class)->paginate($this->perPage),
            ),
        ];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Testable Records',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
                ['field' => 'owner.city', 'label' => 'City'],
                ['field' => 'owner.rating', 'label' => 'Rating'],
                ['field' => 'nope.city', 'label' => 'Nope'],
            ],
        ];
    }
}

/**
 * Same JSON list, but rendering the real list Blade so the header funnels appear.
 */
class TestableJsonColumnFilterRenderComponent extends TestableJsonColumnFilterListComponent
{
    public function render(): string
    {
        return '<div><x-noerd::list /></div>';
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

/**
 * Same list wrapped in the page component, so the header slot (and with it the
 * filter chips) actually renders.
 */
class TestableColumnFilterPageRenderComponent extends TestableColumnFilterListComponent
{
    public function render(): string
    {
        return '<div><x-noerd::page><x-noerd::list /></x-noerd::page></div>';
    }
}

/**
 * Synthetic config exercising every chip value resolution: plain text, an
 * auto-typed bool column and a badge column with inline options.
 */
class TestableColumnFilterChipListComponent extends TestableColumnFilterListComponent
{
    public const COMPONENT = 'testable-column-filter-chip-list';

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Testable Users',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
                ['field' => 'super_admin', 'label' => 'Admin'],
                [
                    'field' => 'email',
                    'label' => 'Status',
                    'type' => 'badge',
                    'options' => [
                        ['value' => 'open@example.com', 'label' => 'Open'],
                    ],
                ],
            ],
        ];
    }
}

it('resets pagination when the search changes', function (): void {
    NoerdUser::factory()->count(3)->create();

    $component = Livewire::test(TestableColumnFilterListComponent::class)
        ->set('perPage', 1)
        ->call('setPage', 2)
        ->set('search', 'a');

    expect($component->instance()->paginators['page'] ?? 1)->toBe(1);
});
