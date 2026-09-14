<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Noerd\Contracts\DefinesPositionColumns;
use Noerd\Models\NoerdUser;
use Noerd\Support\Positions\PositionColumn;
use Noerd\Support\Positions\PositionColumnResolver;
use Noerd\Support\SchemaColumnCache;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

class ZzPosition extends Model
{
    protected $table = 'zz_positions';

    protected $guarded = [];

    protected $casts = [
        'wishes' => 'array',
        'delivered_on' => 'date',
    ];
}

class ZzPositionColumns implements DefinesPositionColumns
{
    public function columns(string $modelClass): array
    {
        return [
            PositionColumn::make('quantity')->type('number')->width('w-20')->locked(),
            PositionColumn::make('name')->width('w-auto'),
            PositionColumn::make('note')->default(false),
            PositionColumn::make('price')->type('number')->step('0.01')->locked()->onChange('calcGross'),
            PositionColumn::make('total')->type('number')->locked()->readonly(),
        ];
    }

    public function forbidden(): array
    {
        return ['invoice_id', 'total_tax'];
    }
}

/**
 * @param  array<int, mixed>|null  $columns
 * @return array<int, string>
 */
function zzResolvedFields(?array $columns): array
{
    $layout = $columns === null ? [] : ['positions' => ['columns' => $columns]];

    return array_map(
        fn(PositionColumn $column): string => $column->field,
        app(PositionColumnResolver::class)->resolve(new ZzPositionColumns(), ZzPosition::class, $layout),
    );
}

/**
 * @param  array<int, mixed>  $columns
 * @return array<int, array<string, mixed>>
 */
function zzResolvedArrays(array $columns): array
{
    return array_map(
        fn(PositionColumn $column): array => $column->toArray(),
        app(PositionColumnResolver::class)->resolve(new ZzPositionColumns(), ZzPosition::class, ['positions' => ['columns' => $columns]]),
    );
}

beforeEach(function (): void {
    SchemaColumnCache::clear();

    Schema::create('zz_positions', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('tenant_id')->nullable();
        $table->unsignedBigInteger('invoice_id')->nullable();
        $table->decimal('quantity', 10, 2)->default(1);
        $table->string('name')->nullable();
        $table->string('note')->nullable();
        $table->decimal('price', 10, 2)->default(0);
        $table->decimal('total', 10, 2)->default(0);
        $table->decimal('total_tax', 10, 2)->default(0);
        $table->string('comment')->nullable();
        $table->json('wishes')->nullable();
        $table->date('delivered_on')->nullable();
        $table->boolean('express')->default(false);
        $table->string('unit')->nullable();
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('zz_positions');
    SchemaColumnCache::clear();
});

describe('PositionColumnResolver', function (): void {

    it('returns the default catalog columns without a positions block', function (): void {
        expect(zzResolvedFields(null))->toBe(['quantity', 'name', 'price', 'total']);
    });

    it('follows the YAML order and hides optional columns it leaves out', function (): void {
        expect(zzResolvedFields([
            ['field' => 'price'],
            ['field' => 'note'],
            ['field' => 'quantity'],
            ['field' => 'total'],
        ]))->toBe(['price', 'note', 'quantity', 'total']);
    });

    it('re-inserts an omitted locked column after its nearest present predecessor', function (): void {
        // quantity has no predecessor → start; price follows name; total follows price.
        expect(zzResolvedFields([
            ['field' => 'comment'],
            ['field' => 'name'],
        ]))->toBe(['quantity', 'comment', 'name', 'price', 'total']);
    });

    it('applies label and width overrides but ignores type, readonly and change', function (): void {
        $price = collect(zzResolvedArrays([
            [
                'field' => 'price',
                'label' => 'Unit Price',
                'width' => 'w-40',
                'type' => 'text',
                'readonly' => true,
                'change' => 'store',
                'step' => '1',
            ],
        ]))->firstWhere('field', 'price');

        expect($price)
            ->label->toBe('Unit Price')
            ->width->toBe('w-40')
            ->type->toBe('number')
            ->readonly->toBeFalse()
            ->change->toBe('calcGross')
            ->step->toBe('0.01')
            ->locked->toBeTrue();
    });

    it('adds a real table column with its declared type and readonly flag', function (): void {
        $columns = collect(zzResolvedArrays([
            ['field' => 'comment', 'label' => 'Comment'],
            ['field' => 'wishes', 'label' => 'Wishes', 'readonly' => true],
            ['field' => 'unit', 'type' => 'select', 'options' => [['value' => 'kg', 'label' => 'Kilogram']]],
            ['field' => 'express', 'type' => 'checkbox'],
        ]))->keyBy('field');

        expect($columns['comment'])->type->toBe('text')->width->toBe('w-32')->change->toBe('store')->locked->toBeFalse()
            ->and($columns['wishes'])->label->toBe('Wishes')->readonly->toBeTrue()
            ->and($columns['unit'])->type->toBe('select')->options->toBe([['value' => 'kg', 'label' => 'Kilogram']])
            ->and($columns['express']['type'])->toBe('checkbox')
            ->and($columns->keys()->all())->toContain('quantity', 'price', 'total');
    });

    it('defaults the label of an extra column to the headline of the field', function (): void {
        expect(collect(zzResolvedArrays([['field' => 'delivered_on', 'type' => 'date']]))->firstWhere('field', 'delivered_on')['label'])
            ->toBe('Delivered On');
    });

    it('drops invalid entries and logs a warning for each', function (): void {
        Log::spy();

        expect(zzResolvedFields([
            ['field' => 'name'],
            ['field' => 'does_not_exist'],
            ['field' => 'id'],
            ['field' => 'tenant_id'],
            ['field' => 'created_at'],
            ['field' => 'invoice_id'],
            ['field' => 'total_tax'],
            ['label' => 'No field'],
            ['field' => 'name', 'label' => 'Again'],
        ]))->toBe(['quantity', 'name', 'price', 'total']);

        Log::shouldHaveReceived('warning')->times(8);
    });
});

describe('PositionColumn', function (): void {

    it('survives an array round trip', function (): void {
        $column = PositionColumn::make('unit')
            ->label('Unit')
            ->width('w-24')
            ->options(['kg' => 'Kilogram', 'pc' => 'Piece'])
            ->locked()
            ->readonly()
            ->default(false)
            ->onChange('calcGross')
            ->step(2);

        expect(PositionColumn::fromArray($column->toArray())->toArray())->toBe($column->toArray())
            ->and($column->type)->toBe('select')
            ->and($column->options)->toBe([['value' => 'kg', 'label' => 'Kilogram'], ['value' => 'pc', 'label' => 'Piece']]);
    });

    it('renders array values as text', function (): void {
        $column = PositionColumn::make('wishes');

        expect($column->displayValue(['no onions', 'extra cheese']))->toBe('no onions, extra cheese')
            ->and($column->displayValue('[{"name":"Sauce","size":"large"},{"name":"Salt"}]'))->toBe('Sauce large, Salt')
            ->and($column->displayValue(null))->toBe('')
            ->and($column->displayValue(new stdClass()))->toBe('')
            ->and(PositionColumn::isArrayValue('["a"]'))->toBeTrue()
            ->and(PositionColumn::isArrayValue('plain'))->toBeFalse();
    });

    it('offers type shorthands', function (): void {
        expect(PositionColumn::make('a')->number(1)->toArray())->type->toBe('number')->step->toBe(1)
            ->and(PositionColumn::make('a')->date()->type)->toBe('date')
            ->and(PositionColumn::make('a')->checkbox()->type)->toBe('checkbox')
            ->and(PositionColumn::make('a')->select(['x' => 'X'])->toArray())->type->toBe('select')->options->toBe([['value' => 'x', 'label' => 'X']]);
    });

    it('is editable unless readonly', function (): void {
        expect(PositionColumn::make('price')->locked()->isEditable())->toBeTrue()
            ->and(PositionColumn::make('total')->readonly()->isEditable())->toBeFalse();
    });
});

describe('NoerdPositionRow + positions.cells', function (): void {

    beforeEach(function (): void {
        $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

        $this->position = ZzPosition::create([
            'quantity' => 2,
            'name' => 'Pizza',
            'price' => 9.5,
            'total' => 19,
            'comment' => 'ring twice',
            'wishes' => ['no onions', 'extra cheese'],
        ]);

        $this->columns = zzResolvedArrays([
            ['field' => 'quantity'],
            ['field' => 'name'],
            ['field' => 'comment', 'label' => 'Comment'],
            ['field' => 'wishes', 'label' => 'Wishes'],
            ['field' => 'delivered_on', 'type' => 'date'],
        ]);

        $this->mountRow = fn(string $theme = 'default') => Livewire::test('noerd-test::position-columns-row-test', [
            'modelClass' => ZzPosition::class,
            'positionId' => $this->position->id,
            'columns' => $this->columns,
            'theme' => $theme,
            'number' => 1,
        ]);
    });

    it('binds every column to row.{field} and renders readonly and array columns without input', function (): void {
        $html = ($this->mountRow)()
            ->assertSeeHtml('wire:model="row.comment"')
            ->assertSeeHtml('wire:model="row.quantity"')
            ->assertSeeHtml('wire:change="calcGross"')
            ->assertSeeHtml('no onions, extra cheese')
            ->assertDontSeeHtml('wire:model="row.wishes"')
            ->assertSet('row.delivered_on', null)
            ->html();

        // The locked, readonly total renders a disabled control without a change handler.
        expect(preg_match('/<input[^>]*wire:model="row\.total"[^>]*>/', $html, $total))->toBe(1)
            ->and($total[0])->toContain('disabled')
            ->and($total[0])->not->toContain('wire:change');
    });

    it('shows an array-cast column without a value as text, never as an input', function (): void {
        $this->position->update(['wishes' => null]);

        $row = ($this->mountRow)()
            ->assertDontSeeHtml('wire:model="row.wishes"')
            ->assertSet('row.wishes', []);

        expect(collect($row->get('columns'))->firstWhere('field', 'wishes')['readonly'])->toBeTrue();
    });

    it('renders the placeholder of a select column', function (): void {
        $this->columns[] = PositionColumn::make('unit')->select(['kg' => 'Kilogram'])->placeholder('-')->toArray();

        ($this->mountRow)()->assertSeeHtml('<option value="">-</option>');
    });

    it('rejects client updates to the resolved columns', function (): void {
        ($this->mountRow)()->set('columns', [['field' => 'invoice_id']]);
    })->throws(CannotUpdateLockedPropertyException::class);

    it('returns only editable, unlocked fields of the resolved columns', function (): void {
        $values = ($this->mountRow)()
            ->set('row.invoice_id', 99)
            ->instance()
            ->editablePositionValues();

        expect(array_keys($values))->toBe(['name', 'comment', 'delivered_on']);
    });

    it('saves a configured extra column through the row store()', function (): void {
        ($this->mountRow)()
            ->set('row.comment', 'leave at the door')
            ->set('row.delivered_on', '2026-09-20')
            ->set('row.total', 1000)
            ->call('store')
            ->assertHasNoErrors();

        $fresh = $this->position->fresh();

        expect($fresh->comment)->toBe('leave at the door')
            ->and($fresh->delivered_on->format('Y-m-d'))->toBe('2026-09-20')
            ->and((float) $fresh->total)->toBe(19.0);
    });

    it('keeps the theme chrome of the numbered theme', function (): void {
        $row = ($this->mountRow)('numbered')
            ->assertSeeHtml('tabular-nums')
            ->assertSeeHtml('bg-zinc-100');

        expect($row->instance()->positionColumnCount())->toBe(count($this->columns) + 1);
    });

    it('resolves the columns from the layout of a detail', function (): void {
        Livewire::component('zz-position-host-detail', new class extends Component {
            use NoerdDetail;

            public $detailModel = ZzPosition::class;

            public ?string $detailPrimary = 'zzPositionId';

            public function mount(): void
            {
                $this->pageLayout = ['positions' => ['columns' => [['field' => 'comment'], ['field' => 'name']]]];
            }

            public function render(): string
            {
                return '<div></div>';
            }
        });

        $columns = Livewire::test('zz-position-host-detail')->instance()->positionColumns(ZzPositionColumns::class, ZzPosition::class);

        expect(array_column($columns, 'field'))->toBe(['quantity', 'comment', 'name', 'price', 'total']);
    });
});

describe('positions.table', function (): void {

    it('keeps rendering the legacy header shape', function (): void {
        $html = Blade::render('<x-noerd::positions.table :columns="$columns" />', [
            'columns' => [['label' => 'Quantity', 'class' => 'w-32'], 'Name', ''],
        ]);

        expect(mb_substr_count($html, 'scope="col"'))->toBe(3)
            ->and($html)->toContain('w-32')
            ->and($html)->toContain('Quantity')
            ->and($html)->toContain('Name');
    });

    it('renders resolved columns with labels, widths and the action header', function (): void {
        $columns = zzResolvedArrays([['field' => 'name', 'label' => 'Article', 'width' => 'w-96']]);

        $withActions = Blade::render('<x-noerd::positions.table :columns="$columns" />', ['columns' => $columns]);
        $withoutActions = Blade::render('<x-noerd::positions.table :columns="$columns" :actions="false" />', ['columns' => $columns]);

        expect($withActions)->toContain('Article')->toContain('w-96')->toContain('w-20')
            ->and(mb_substr_count($withActions, 'scope="col"'))->toBe(count($columns) + 1)
            ->and(mb_substr_count($withoutActions, 'scope="col"'))->toBe(count($columns));
    });
});
