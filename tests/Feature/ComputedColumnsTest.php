<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Contracts\ComputedColumnProvider;
use Noerd\Services\ComputedColumnRegistry;
use Noerd\Support\ComputedFields;
use Noerd\Support\ModelMethodColumns;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Computed columns: NoerdList/NoerdDetail only know WHERE a computed value is
 | needed and delegate the computation to the ComputedColumnRegistry providers.
 | These tests prove that seam with a synthetic provider (`zzcompute:` key);
 | the core's own `method:` provider is covered by ModelMethodColumnsTest.
 */

class ZzComputedHost extends Model
{
    protected $table = 'zz_computed_hosts';

    protected $guarded = [];

    public function zzItems(): HasMany
    {
        return $this->hasMany(ZzComputedItem::class, 'host_id');
    }
}

class ZzComputedItem extends Model
{
    protected $table = 'zz_computed_items';

    protected $guarded = [];
}

/**
 * Understands exactly two instructions: `ITEMS` (an aggregate, sortable and
 * filterable through a withCount alias) and `UPPER` (computed per row, display only).
 */
class ZzStubComputedColumns implements ComputedColumnProvider
{
    public static int $fillRowsCalls = 0;

    public function handles(array $item): bool
    {
        return isset($item['zzcompute']);
    }

    public function prepareQuery(Builder $query, array $columns): void
    {
        foreach ($columns as $column) {
            if ($column['zzcompute'] === 'ITEMS') {
                $query->withCount('zzItems as ' . $column['field']);
            }
        }
    }

    public function isSortable(string $modelClass, array $column): bool
    {
        return $column['zzcompute'] === 'ITEMS';
    }

    public function applyOrder(Builder $query, array $column, string $direction): void
    {
        $query->orderBy($column['field'], $direction);
    }

    public function isFilterable(string $modelClass, array $column): bool
    {
        return $column['zzcompute'] === 'ITEMS';
    }

    public function applyFilter(Builder $query, array $column, string $type, string $raw): void
    {
        $query->has('zzItems', '>=', (int) $raw);
    }

    public function fillRows(iterable $rows, array $columns): void
    {
        self::$fillRowsCalls++;

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                if ($column['zzcompute'] === 'UPPER') {
                    $row->setAttribute($column['field'], mb_strtoupper((string) $row->name));
                }
            }
        }
    }

    public function detailValues(Model $model, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[$field['name']] = 'computed:' . $model->name . ':' . $model->zzItems()->count();
        }

        return $values;
    }
}

class ZzComputedListComponent extends Component
{
    use NoerdList;

    public $listModel = ZzComputedHost::class;

    public function with(): array
    {
        return [
            'listConfig' => $this->buildList($this->listQuery(ZzComputedHost::class)->paginate($this->perPage)),
        ];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    public function rowValues(string $field): array
    {
        $rows = $this->buildList($this->listQuery(ZzComputedHost::class)->paginate($this->perPage))['rows'];

        return $rows->getCollection()->mapWithKeys(fn($row) => [$row->name => data_get($row, $field)])->all();
    }

    protected function componentName(): string
    {
        return 'zz-computed-list';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return [
            'title' => 'Computed hosts',
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
                ['field' => 'item_count', 'label' => 'Items', 'type' => 'number', 'zzcompute' => 'ITEMS'],
                ['field' => 'shout', 'label' => 'Shout', 'zzcompute' => 'UPPER'],
            ],
        ];
    }

    protected function prepareCsvExport(): array
    {
        return [ZzComputedHost::query()->orderBy('id'), $this->getListConfig()['columns'], 'zz-computed.csv'];
    }
}

beforeEach(function (): void {
    Schema::create('zz_computed_hosts', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    Schema::create('zz_computed_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('host_id');
        $table->timestamps();
    });

    Livewire::component('zz-computed-detail', new class extends Component {
        use NoerdDetail;

        public $detailModel = ZzComputedHost::class;

        public ?string $detailPrimary = 'zzComputedHostId';

        public function mount(): void
        {
            // Synthetic layout instead of a YAML file — mechanics only.
            $this->pageLayout = ['fields' => [
                ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'detailData.summary', 'label' => 'Summary', 'type' => 'text', 'zzcompute' => 'SUMMARY', 'required' => true],
            ]];

            if ($this->modelId) {
                $this->detailData = ZzComputedHost::findOrFail($this->modelId)->toArray();
            }
        }

        public function render(): string
        {
            return '<div>zz-computed-detail</div>';
        }
    });
});

afterEach(function (): void {
    Schema::dropIfExists('zz_computed_items');
    Schema::dropIfExists('zz_computed_hosts');
});

function zzComputedHosts(): array
{
    $anna = ZzComputedHost::create(['name' => 'anna']);
    $bert = ZzComputedHost::create(['name' => 'bert']);
    ZzComputedHost::create(['name' => 'carl']);

    foreach ([$anna, $anna, $anna, $bert] as $host) {
        ZzComputedItem::create(['host_id' => $host->id]);
    }

    return [$anna, $bert];
}

it('computes `method:` columns with the core provider by default', function (): void {
    expect(app(ComputedColumnRegistry::class)->providerFor(['method' => 'x']))->toBeInstanceOf(ModelMethodColumns::class)
        ->and(app(ComputedColumnRegistry::class)->providerFor(['field' => 'name']))->toBeNull();
});

describe('with a registered provider', function (): void {
    beforeEach(function (): void {
        ZzStubComputedColumns::$fillRowsCalls = 0;
        app(ComputedColumnRegistry::class)->register(ZzStubComputedColumns::class);
    });

    it('computes the computed columns of the current page', function (): void {
        zzComputedHosts();

        $list = Livewire::test(ZzComputedListComponent::class)->instance();

        expect($list->rowValues('item_count'))->toEqual(['anna' => 3, 'bert' => 1, 'carl' => 0])
            ->and($list->rowValues('shout'))->toEqual(['anna' => 'ANNA', 'bert' => 'BERT', 'carl' => 'CARL']);
    });

    it('lets the provider decide which computed columns are sortable and filterable', function (): void {
        $list = Livewire::test(ZzComputedListComponent::class)->instance();

        expect($list->isSortableColumn('item_count', $list->builtListConfig()['notSortableColumns']))->toBeTrue()
            ->and($list->isSortableColumn('shout', $list->builtListConfig()['notSortableColumns']))->toBeFalse()
            ->and($list->builtListConfig()['filterableColumns'])->toBe(['name', 'item_count']);
    });

    it('orders by a sortable computed column and refuses a display-only one', function (): void {
        zzComputedHosts();

        $component = Livewire::test(ZzComputedListComponent::class)->call('sortBy', 'item_count');
        expect(array_keys($component->instance()->rowValues('item_count')))->toBe(['carl', 'bert', 'anna']);

        $component->call('sortBy', 'item_count');
        expect(array_keys($component->instance()->rowValues('item_count')))->toBe(['anna', 'bert', 'carl']);

        $component->call('sortBy', 'shout')->assertSet('sortField', 'item_count');
    });

    it('filters through the provider', function (): void {
        zzComputedHosts();

        $component = Livewire::test(ZzComputedListComponent::class)->call('setColumnFilter', 'item_count', '1');

        expect(array_keys($component->instance()->rowValues('item_count')))->toEqualCanonicalizing(['anna', 'bert']);
    });

    it('computes computed columns once per chunk in the CSV export', function (): void {
        config(['noerd.format.csv_delimiter' => ';']);
        zzComputedHosts();

        $response = Livewire::test(ZzComputedListComponent::class)->instance()->exportCsv();
        ZzStubComputedColumns::$fillRowsCalls = 0;

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        expect($csv)->toContain("anna;3.00;ANNA\n")
            ->and($csv)->toContain("carl;0.00;CARL\n")
            ->and(ZzStubComputedColumns::$fillRowsCalls)->toBe(1);
    });

    it('shows detail computed values as text outside of detailData', function (): void {
        [$anna] = zzComputedHosts();

        $component = Livewire::test('zz-computed-detail', ['modelId' => $anna->id]);

        $component->assertSet('computedValues', ['summary' => 'computed:anna:3'])
            ->assertSet('pageLayout.fields.1.name', 'computedValues.summary')
            ->assertSet('pageLayout.fields.1.theme', 'display');

        expect($component->get('detailData'))->not->toHaveKey('summary')
            ->and($component->get('pageLayout.fields.1'))->not->toHaveKey('required');
    });

    it('never persists a computed field and refreshes it after saving', function (): void {
        [$anna] = zzComputedHosts();

        Livewire::test('zz-computed-detail', ['modelId' => $anna->id])
            ->set('detailData.name', 'anne')
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('computedValues', ['summary' => 'computed:anne:3']);

        expect($anna->refresh()->name)->toBe('anne');
    });

    it('leaves computed fields empty for a new record', function (): void {
        Livewire::test('zz-computed-detail')->assertSet('computedValues', ['summary' => null]);
    });
});

it('excludes computed fields from the writable keys of a layout', function (): void {
    $detail = new class {
        use NoerdDetail;

        public function keys(array $fields): array
        {
            return $this->writableKeysFromFields($fields);
        }
    };

    expect($detail->keys([
        ['name' => 'detailData.name'],
        ['name' => 'detailData.summary', 'method' => 'anything'],
        ['type' => 'block', 'fields' => [['name' => 'detailData.total', 'method' => 'anything']]],
    ]))->toBe(['name']);
});

it('rewrites computed fields idempotently', function (): void {
    $layout = ['fields' => [
        ['type' => 'block', 'fields' => [['name' => 'detailData.total', 'method' => 'x', 'required' => true]]],
    ]];

    $once = ComputedFields::prepareLayout($layout);

    expect(ComputedFields::prepareLayout($once))->toBe($once)
        ->and($once['fields'][0]['fields'][0])->toBe(['name' => 'computedValues.total', 'method' => 'x', 'theme' => 'display']);
});
