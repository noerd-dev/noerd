<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Support\ListFilter;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Header filters are configuration (`filters:` in the list YAML) or
 | ListFilter objects returned by get*ListFilter() methods — and listQuery()
 | applies them. Proven against a runtime table with a synthetic list config,
 | never against a shipped YAML.
 */

function zzFilterRowIds(mixed $component): array
{
    return \Livewire\invade($component->instance())->visibleRowIds();
}

function zzCreateFilterItemsTable(): void
{
    if (Schema::hasTable('zz_filter_items')) {
        return;
    }

    Schema::create('zz_filter_items', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('status')->default('open');
        $table->date('booked_on')->nullable();
    });
}

beforeEach(function (): void {
    zzCreateFilterItemsTable();
    session()->forget('listFilters');
});

it('applies a picklist filter declared in the list YAML through listQuery()', function (): void {
    $open = ZzFilterItem::create(['name' => 'Alpha', 'status' => 'open']);
    ZzFilterItem::create(['name' => 'Beta', 'status' => 'done']);

    $component = Livewire::test(ZzYamlFilterListComponent::class);
    expect(zzFilterRowIds($component))->toHaveCount(2);

    $component->set('listFilters.status', 'open');

    expect(zzFilterRowIds($component))->toBe([$open->id]);
});

it('offers the YAML filters in the filter row', function (): void {
    $this->actingAs(NoerdUser::factory()->create());

    Livewire::test(ZzYamlFilterPageComponent::class)
        ->assertSeeHtml('listFilters.status')
        ->assertSee('Zz Status')
        ->assertSee('Zz Open');
});

it('starts on the declared default and clears it to the stored empty selection', function (): void {
    $open = ZzFilterItem::create(['name' => 'Alpha', 'status' => 'open']);
    $done = ZzFilterItem::create(['name' => 'Beta', 'status' => 'done']);

    $component = Livewire::test(ZzDefaultFilterListComponent::class);
    expect($component->get('listFilters.status'))->toBe('open')
        ->and(zzFilterRowIds($component))->toBe([$open->id]);

    $component->call('clearAllListFilters');

    expect(zzFilterRowIds($component))->toEqualCanonicalizing([$open->id, $done->id])
        ->and(session('listFilters'))->toBe(['status' => '']);

    // A later mount honours the cleared selection instead of re-applying the default.
    expect(zzFilterRowIds(Livewire::test(ZzDefaultFilterListComponent::class)))->toHaveCount(2);
});

it('offers the distinct years of a date column newest first and narrows to the chosen year', function (): void {
    ZzFilterItem::create(['name' => 'Old', 'booked_on' => '2024-05-01']);
    $spring = ZzFilterItem::create(['name' => 'Spring', 'booked_on' => '2026-02-02']);
    $summer = ZzFilterItem::create(['name' => 'Summer', 'booked_on' => '2026-07-07']);
    ZzFilterItem::create(['name' => 'Undated', 'booked_on' => null]);

    $component = Livewire::test(ZzYamlFilterListComponent::class);
    $headerFilters = collect(\Livewire\invade($component->instance())->headerFilters())->keyBy('column');

    expect($headerFilters['booked_on']['options'])->toBe(['2026' => '2026', '2024' => '2024']);

    $component->set('listFilters.booked_on', '2026');

    expect(zzFilterRowIds($component))->toEqualCanonicalizing([$spring->id, $summer->id]);
});

it('compares a dateFrom filter against its column and ignores an unparseable value', function (): void {
    ZzFilterItem::create(['name' => 'Old', 'booked_on' => '2025-12-31']);
    $new = ZzFilterItem::create(['name' => 'New', 'booked_on' => '2026-01-01']);

    $component = Livewire::test(ZzDateFilterListComponent::class)->set('listFilters.booked_on', '2026-01-01');
    expect(zzFilterRowIds($component))->toBe([$new->id]);

    $component->set('listFilters.booked_on', 'not a date');
    expect(zzFilterRowIds($component))->toHaveCount(2);
});

it('applies a virtual column through its closure and keeps one without a closure out of the SQL', function (): void {
    ZzFilterItem::create(['name' => 'Ab']);
    $long = ZzFilterItem::create(['name' => 'Abcdef']);

    $component = Livewire::test(ZzVirtualFilterListComponent::class)
        ->set('listFilters.long_name', 'yes')
        ->set('listFilters.virtual_thing', 'x');

    expect(zzFilterRowIds($component))->toBe([$long->id]);
});

it('applies the header filters once per builder, also when the list calls applyListFilters() again', function (): void {
    ZzFilterItem::create(['name' => 'Alpha', 'status' => 'open']);

    $component = Livewire::test(ZzDoubleApplyFilterListComponent::class)->set('listFilters.status', 'open');

    expect(zzFilterRowIds($component))->toHaveCount(1)
        ->and($component->instance()->statusWheres)->toBe(1);
});

it('ignores a filter key no dropdown declares unless the component whitelists it', function (): void {
    $alpha = ZzFilterItem::create(['name' => 'Alpha', 'status' => 'open']);
    ZzFilterItem::create(['name' => 'Beta', 'status' => 'open']);

    $undeclared = Livewire::test(ZzYamlFilterListComponent::class)->set('listFilters.name', 'Alpha');
    expect(zzFilterRowIds($undeclared))->toHaveCount(2);

    $whitelisted = Livewire::test(ZzWhitelistFilterListComponent::class)->set('listFilters.name', 'Alpha');
    expect(zzFilterRowIds($whitelisted))->toBe([$alpha->id]);
});

class ZzFilterItem extends Model
{
    public $timestamps = false;

    protected $table = 'zz_filter_items';

    protected $guarded = [];
}

/** The list over the runtime table; the YAML is the inline config below. */
class ZzYamlFilterListComponent extends Component
{
    use NoerdList;

    public $listModel = ZzFilterItem::class;

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-filter-items-list';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Zz Items',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
                ['field' => 'status', 'label' => 'Status'],
            ],
            'filters' => [
                [
                    'label' => 'Zz Status',
                    'column' => 'status',
                    'options' => [
                        ['value' => 'open', 'label' => 'Zz Open'],
                        ['value' => 'done', 'label' => 'Zz Done'],
                    ],
                ],
                ['label' => 'Zz Year', 'column' => 'booked_on', 'type' => 'years'],
            ],
        ];
    }
}

/** The date column carries the dateFrom dropdown instead of the years. */
class ZzDateFilterListComponent extends ZzYamlFilterListComponent
{
    protected function componentName(): string
    {
        return 'zz-date-filter-items-list';
    }

    protected function getListConfig(?string $customName = null): array
    {
        $config = parent::getListConfig($customName);
        $config['filters'] = [
            ['label' => 'Zz From', 'column' => 'booked_on', 'type' => 'dateFrom'],
        ];

        return $config;
    }
}

/** The same list rendered through the real list chrome. */
class ZzYamlFilterPageComponent extends ZzYamlFilterListComponent
{
    public function render(): string
    {
        return '<x-noerd::page><x-noerd::list /></x-noerd::page>';
    }
}

class ZzDefaultFilterListComponent extends ZzYamlFilterListComponent
{
    protected function componentName(): string
    {
        return 'zz-default-filter-items-list';
    }

    protected function getListConfig(?string $customName = null): array
    {
        $config = parent::getListConfig($customName);
        $config['filters'] = [
            [
                'label' => 'Zz Status',
                'column' => 'status',
                'default' => 'open',
                'options' => ['open' => 'Zz Open', 'done' => 'Zz Done'],
            ],
        ];

        return $config;
    }
}

class ZzVirtualFilterListComponent extends ZzYamlFilterListComponent
{
    protected function getZzLongNameListFilter(): ListFilter
    {
        return ListFilter::options('Zz Long', 'long_name', ['yes' => 'Long names'])
            ->apply(fn(Builder $query, string $value) => $query->whereRaw('LENGTH(name) > 4'));
    }

    protected function getZzVirtualListFilter(): ListFilter
    {
        // No closure, no such column: rendered, never written into the SQL.
        return ListFilter::options('Zz Virtual', 'virtual_thing', ['x' => 'X']);
    }
}

class ZzDoubleApplyFilterListComponent extends ZzYamlFilterListComponent
{
    public int $statusWheres = 0;

    public function listData(): array
    {
        $query = $this->listQuery($this->listModel);
        // The pattern lists used before 0.17 — a second application must be a no-op.
        $this->applyListFilters($query);

        $this->statusWheres = collect($query->getQuery()->wheres)
            ->filter(fn(array $where): bool => str_ends_with((string) ($where['column'] ?? ''), 'status'))
            ->count();

        return $this->buildList($query->paginate($this->perPage));
    }
}

class ZzWhitelistFilterListComponent extends ZzYamlFilterListComponent
{
    protected function getAllowedListFilterColumns(): array
    {
        return [...parent::getAllowedListFilterColumns(), 'name'];
    }
}

it('reduces a stored array value to its first scalar instead of failing on the cast', function (): void {
    ZzFilterItem::create(['name' => 'Alpha', 'status' => 'open']);
    $done = ZzFilterItem::create(['name' => 'Beta', 'status' => 'done']);

    // An older multi-select left an array in the shared session bucket.
    session(['listFilters' => ['status' => ['done', 'open'], 'zz_other_list' => ['kept']]]);

    $component = Livewire::test(ZzYamlFilterListComponent::class);

    expect(zzFilterRowIds($component))->toBe([$done->id])
        ->and($component->get('listFilters.status'))->toBe('done')
        // another list's key is not this list's business
        ->and($component->get('listFilters.zz_other_list'))->toBe(['kept']);
});

it('skips an array value that holds nothing, also for a filter without a closure', function (): void {
    ZzFilterItem::create(['name' => 'Ab']);
    ZzFilterItem::create(['name' => 'Abcdef']);

    session(['listFilters' => ['virtual_thing' => ['x'], 'long_name' => [null, '']]]);

    $component = Livewire::test(ZzVirtualFilterListComponent::class);

    expect(zzFilterRowIds($component))->toHaveCount(2)
        ->and($component->get('listFilters.virtual_thing'))->toBe('x')
        ->and($component->get('listFilters.long_name'))->toBe('');
});
