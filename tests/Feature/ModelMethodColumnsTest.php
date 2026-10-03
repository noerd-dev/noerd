<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Attributes\ComputedValue;
use Noerd\Support\ModelMethodColumns;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\NoerdList;

uses(TestCase::class, RefreshDatabase::class);

/*
 | `method:` columns and fields: a marked model method computes the value.
 | Only methods carrying #[ComputedValue] are ever called.
 */

enum ZzMethodTier: string
{
    case Gold = 'gold';
}

class ZzMethodCustomer extends Model
{
    public static int $sideEffects = 0;

    protected $table = 'zz_mc_customers';

    protected $guarded = [];

    #[ComputedValue]
    public static function staticValue(): string
    {
        self::$sideEffects++;

        return 'static';
    }

    public function zzOrders(): HasMany
    {
        return $this->hasMany(ZzMethodOrder::class, 'customer_id');
    }

    #[ComputedValue(with: ['zzOrders'])]
    public function lastOrder(): ?CarbonInterface
    {
        return $this->zzOrders->max('ordered_at');
    }

    #[ComputedValue(with: ['zzOrders'])]
    public function orderCount(): int
    {
        return $this->zzOrders->count();
    }

    #[ComputedValue]
    public function tier(): ZzMethodTier
    {
        return ZzMethodTier::Gold;
    }

    #[ComputedValue]
    public function broken(): string
    {
        throw new RuntimeException('boom');
    }

    #[ComputedValue]
    public function withArgument(string $required): string
    {
        self::$sideEffects++;

        return $required;
    }

    /** Not marked — must never be called from a YAML entry. */
    public function unmarked(): string
    {
        self::$sideEffects++;

        return 'called';
    }
}

class ZzMethodOrder extends Model
{
    protected $table = 'zz_mc_orders';

    protected $guarded = [];

    protected $casts = ['ordered_at' => 'datetime'];
}

class ZzMethodCustomersList extends Component
{
    use NoerdList;

    public $listModel = ZzMethodCustomer::class;

    public static array $columns = [];

    public function render(): string
    {
        return '<div></div>';
    }

    public function values(string $field): array
    {
        return $this->listData()['rows']->getCollection()->mapWithKeys(fn($row) => [$row->name => data_get($row, $field)])->all();
    }

    protected function componentName(): string
    {
        return 'zz-method-customers-list';
    }

    protected function getListConfig(?string $customName = null): array
    {
        return ['title' => 'Customers', 'columns' => array_merge([['field' => 'name', 'label' => 'Name']], self::$columns)];
    }
}

beforeEach(function (): void {
    ZzMethodCustomer::$sideEffects = 0;

    Schema::create('zz_mc_customers', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    Schema::create('zz_mc_orders', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('customer_id');
        $table->dateTime('ordered_at');
        $table->timestamps();
    });

    $this->anna = ZzMethodCustomer::create(['name' => 'anna']);
    $this->bert = ZzMethodCustomer::create(['name' => 'bert']);
    foreach (['2026-09-01 10:00:00', '2026-10-01 08:00:00'] as $orderedAt) {
        ZzMethodOrder::create(['customer_id' => $this->anna->id, 'ordered_at' => $orderedAt]);
    }
});

afterEach(function (): void {
    Schema::dropIfExists('zz_mc_orders');
    Schema::dropIfExists('zz_mc_customers');
});

function zzMethodList(array $columns): ZzMethodCustomersList
{
    ZzMethodCustomersList::$columns = $columns;

    return Livewire::test(ZzMethodCustomersList::class)->instance();
}

it('shows the value of a marked method, presented by the column type', function (): void {
    $list = zzMethodList([
        ['field' => 'last_order', 'type' => 'date', 'method' => 'lastOrder'],
        ['field' => 'order_count', 'type' => 'number', 'method' => 'orderCount'],
        ['field' => 'tier', 'method' => 'tier'],
    ]);

    expect($list->values('last_order'))->toEqual(['anna' => '2026-10-01', 'bert' => null])
        ->and($list->values('order_count'))->toEqual(['anna' => 2, 'bert' => 0])
        ->and($list->values('tier'))->toEqual(['anna' => 'gold', 'bert' => 'gold']);
});

it('eager-loads the relations the method declares once per page', function (): void {
    $list = zzMethodList([['field' => 'order_count', 'type' => 'number', 'method' => 'orderCount']]);
    $list->values('order_count');

    DB::enableQueryLog();
    $list->values('order_count');
    $orderQueries = collect(DB::getQueryLog())->filter(fn(array $entry): bool => str_contains($entry['query'], 'zz_mc_orders'))->count();
    DB::disableQueryLog();

    expect($orderQueries)->toBe(1);
});

it('never calls a method that is not marked, static or needs arguments', function (string $method): void {
    Log::spy();

    $list = zzMethodList([['field' => 'x', 'method' => $method]]);

    expect($list->values('x'))->toEqual(['anna' => null, 'bert' => null])
        ->and(ZzMethodCustomer::$sideEffects)->toBe(0);

    Log::shouldHaveReceived('warning')->withArgs(fn(string $message, array $context): bool => $message === 'Computed column method not allowed'
        && $context['method'] === $method);
})->with(['unmarked', 'staticValue', 'withArgument', 'delete', 'nope']);

it('renders a failing method empty and logs it', function (): void {
    Log::spy();

    expect(zzMethodList([['field' => 'x', 'method' => 'broken']])->values('x'))->toEqual(['anna' => null, 'bert' => null]);

    Log::shouldHaveReceived('warning')->once()->withArgs(fn(string $message, array $context): bool => $message === 'Computed column method failed'
        && $context['reason'] === 'boom');
});

it('neither sorts nor filters a method column', function (): void {
    ZzMethodCustomersList::$columns = [['field' => 'order_count', 'type' => 'number', 'method' => 'orderCount']];
    $component = Livewire::test(ZzMethodCustomersList::class)->call('sortBy', 'order_count');

    $built = $component->instance()->builtListConfig();

    expect($component->get('sortField'))->not->toBe('order_count')
        ->and($built['notSortableColumns'])->toBe(['order_count'])
        ->and($built['filterableColumns'])->toBe(['name']);
});

it('shows a marked method in a detail without saving it', function (): void {
    Livewire::component('zz-method-customer-detail', new class extends Component {
        use NoerdDetail;

        public $detailModel = ZzMethodCustomer::class;

        public ?string $detailPrimary = 'zzMethodCustomerId';

        public function mount(): void
        {
            $this->pageLayout = ['fields' => [
                ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'detailData.last_order', 'label' => 'Last order', 'type' => 'date', 'method' => 'lastOrder'],
            ]];

            if ($this->modelId) {
                $this->detailData = ZzMethodCustomer::findOrFail($this->modelId)->toArray();
            }
        }

        public function render(): string
        {
            return '<div></div>';
        }
    });

    Livewire::test('zz-method-customer-detail', ['modelId' => $this->anna->id])
        ->assertSet('computedValues', ['last_order' => '2026-10-01'])
        ->assertSet('pageLayout.fields.1.name', 'computedValues.last_order')
        ->set('detailData.name', 'anne')
        ->call('store')
        ->assertHasNoErrors();

    expect($this->anna->refresh()->name)->toBe('anne');
});

it('exposes the marker for authoring tools', function (): void {
    $provider = app(ModelMethodColumns::class);

    expect($provider->marker(ZzMethodCustomer::class, 'lastOrder')?->with)->toBe(['zzOrders'])
        ->and($provider->marker(ZzMethodCustomer::class, 'unmarked'))->toBeNull();
});
