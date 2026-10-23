<?php

declare(strict_types=1);

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Services\ListCellRendererRegistry;
use Noerd\Support\ListCellFormatter;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdList;

uses(TestCase::class);

beforeEach(function (): void {
    $this->cellViews = sys_get_temp_dir() . '/zz-list-cells-' . uniqid();
    File::ensureDirectoryExists($this->cellViews);
    File::put(
        $this->cellViews . '/signed.blade.php',
        '<span class="zz-cell" data-value="{{ $value }}" data-row="{{ data_get($row, \'name\') }}" data-column="{{ $column[\'field\'] }}">{{ $text }}</span>',
    );
    View::addNamespace('zz-cells', $this->cellViews);
    app(ListCellRendererRegistry::class)->register('zzSigned', 'zz-cells::signed');
});

afterEach(function (): void {
    File::deleteDirectory($this->cellViews);
});

/**
 * @param  array<string, mixed>  $column
 */
function renderRendererCell(array $column, mixed $value = 12.5): string
{
    return view('noerd::components.table.table-cell', [
        'row' => 0,
        'column' => 0,
        'label' => '',
        'value' => $value,
        'readOnly' => true,
        'id' => 7,
        'columnValue' => $column['field'],
        'type' => $column['type'] ?? 'text',
        'action' => 'openListRow',
        'actions' => null,
        'columnConfig' => $column,
        'rowData' => ['id' => 7, 'name' => 'Gadget'],
    ])->render();
}

it('renders a column through its registered cell renderer with value, formatted text, row and column', function (): void {
    $html = renderRendererCell(['field' => 'amount', 'type' => 'number', 'cell' => 'zzSigned', 'align' => 'right']);

    expect($html)->toContain('class="zz-cell"')
        ->toContain('data-value="12.5"')
        ->toContain('data-row="Gadget"')
        ->toContain('data-column="amount"')
        ->toContain(e(ListCellFormatter::format(12.5, ['type' => 'number'])))
        ->toContain("openListRow('7')")
        ->toContain('justify-end')
        ->not->toContain('<input');
});

it('falls back to the type rendering and warns once for an unknown renderer', function (): void {
    Log::spy();

    $first = renderRendererCell(['field' => 'amount', 'type' => 'number', 'cell' => 'zzUnknownRenderer']);
    renderRendererCell(['field' => 'amount', 'type' => 'number', 'cell' => 'zzUnknownRenderer']);

    expect($first)->toContain('<input')
        ->not->toContain('zz-cell');
    Log::shouldHaveReceived('warning')->once();
});

it('falls back to the type rendering when the registered view does not exist', function (): void {
    app(ListCellRendererRegistry::class)->register('zzMissingView', 'zz-cells::does-not-exist');

    expect(renderRendererCell(['field' => 'amount', 'type' => 'number', 'cell' => 'zzMissingView']))
        ->toContain('<input')
        ->not->toContain('zz-cell');
});

it('renders the cell renderer in a minimal list too', function (): void {
    $html = Livewire::test(ZzCellRendererMinimalList::class)->html();

    expect($html)->toContain('class="zz-cell"')
        ->toContain('data-row="Gadget"');
});

class ZzCellRendererMinimalList extends Component
{
    use NoerdList;

    public function mount(): void
    {
        $this->minimal = true;
    }

    public function with(): array
    {
        return [
            'listConfig' => [
                'listId' => 'zz-cell-renderer',
                'rows' => new LengthAwarePaginator([['id' => 1, 'name' => 'Gadget', 'amount' => 3]], 1, 10),
                'listSettings' => ['columns' => [['field' => 'amount', 'label' => 'Amount', 'type' => 'number', 'cell' => 'zzSigned']]],
            ],
        ];
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                @include('noerd::components.list.minimal')
            </div>
        BLADE;
    }

    protected function componentName(): string
    {
        return 'zz-cell-renderer-test-list';
    }
}
