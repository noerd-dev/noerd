<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class ZzTableCellGadget extends Model
{
    protected $table = 'gadgets';

    protected $guarded = [];

    protected $casts = [
        'custom_attributes' => 'array',
    ];
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->withExampleTenant()->create());

    Schema::create('gadgets', function (Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->string('name')->nullable();
        $blueprint->json('custom_attributes')->nullable();
        $blueprint->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('gadgets');
});

/**
 * Render one table cell of a list row. Every caller overrides only the keys its
 * case is about — the rest keeps the neutral read-only text-cell defaults.
 *
 * @param  array<string, mixed>  $overrides
 */
function renderTableCell(array $overrides = []): string
{
    return view('noerd::components.table.table-cell', array_merge([
        'row' => 0,
        'column' => 0,
        'label' => '',
        'value' => '',
        'readOnly' => true,
        'id' => 1,
        'columnValue' => 'name',
        'type' => 'text',
        'action' => 'tableAction',
        'actions' => null,
        'columnConfig' => [],
        'rowData' => [],
    ], $overrides))->render();
}

/**
 * @param  array<string, mixed>  $columnConfig
 * @param  array<string, mixed>  $rowData
 */
function renderRelationLinkCell(array $columnConfig, array $rowData = ['vehicle_id' => 42]): string
{
    return renderTableCell([
        'value' => 'Vehicle A',
        'columnValue' => 'vehicle',
        'type' => 'relation_link',
        'columnConfig' => $columnConfig,
        'rowData' => $rowData,
    ]);
}

describe('value cells', function (): void {

    it('renders a relation badge with the related record title', function (): void {
        $gadget = ZzTableCellGadget::create(['name' => 'Erika Musterfrau']);

        $html = renderTableCell([
            'type' => 'relationBadge',
            'columnValue' => 'gadget_id',
            'value' => $gadget->id,
        ]);

        expect($html)->toContain('rounded-full')
            ->and($html)->toContain('Erika Musterfrau');
    });

    it('renders an empty cell for an empty foreign key', function (): void {
        $html = renderTableCell([
            'type' => 'relationBadge',
            'columnValue' => 'gadget_id',
            'value' => null,
        ]);

        expect($html)->toContain('<td')
            ->and($html)->not->toContain('rounded-full');
    });

    it('renders a custom attribute cell by traversing the json column', function (): void {
        $gadget = ZzTableCellGadget::create([
            'custom_attributes' => ['plz_zone' => 'Zone Nord'],
        ]);

        $html = renderTableCell([
            'type' => 'customAttribute',
            'columnValue' => 'custom_attributes.plz_zone',
            'value' => '',
            'rowData' => $gadget,
        ]);

        expect($html)->toContain('Zone Nord');
    });

    it('renders a plain text cell', function (): void {
        $html = renderTableCell([
            'type' => 'text',
            'columnValue' => 'name',
            'value' => 'Plain Value',
        ]);

        expect($html)->toContain('<input')
            ->and($html)->toContain('Plain Value');
    });
});

describe('relation_link route mode', function (): void {

    it('renders a relation_link route cell as $modalRoute with the id from idField', function (): void {
        registerTestLivewireRoute('zz-cell-vehicle/{modelId}', 'noerd-test::theme-test', 'zz.cell.vehicle');

        $html = renderRelationLinkCell([
            'route' => 'zz.cell.vehicle',
            'idField' => 'vehicle_id',
        ]);

        expect($html)->toContain('$modalRoute(')
            ->toContain('zz.cell.vehicle')
            ->toContain('42')
            ->not->toContain('$modal(');
    });

    it('defaults idParam to modelId in both route and component mode', function (): void {
        registerTestLivewireRoute('zz-cell-default/{modelId}', 'noerd-test::theme-test', 'zz.cell.default');

        $routeHtml = renderRelationLinkCell([
            'route' => 'zz.cell.default',
            'idField' => 'vehicle_id',
        ]);

        $componentHtml = renderRelationLinkCell([
            'modalComponent' => 'zz::vehicle-detail',
            'idField' => 'vehicle_id',
        ]);

        expect(html_entity_decode($routeHtml))->toContain('modelId');
        expect(html_entity_decode($componentHtml))->toContain('modelId\\u0022:42');
    });

    it('honors an explicit idParam override in component mode', function (): void {
        $componentHtml = renderRelationLinkCell([
            'modalComponent' => 'zz::vehicle-detail',
            'idField' => 'vehicle_id',
            'idParam' => 'vehicleId',
        ]);

        expect(html_entity_decode($componentHtml))->toContain('vehicleId\\u0022:42');
    });

    it('falls back to the modalComponent when the relation_link route is unregistered', function (): void {
        $html = renderRelationLinkCell([
            'route' => 'zz.cell.route.that.does.not.exist',
            'modalComponent' => 'zz::vehicle-detail',
            'idField' => 'vehicle_id',
        ]);

        expect($html)->toContain('$modal(')
            ->toContain('zz::vehicle-detail')
            ->not->toContain('$modalRoute(');
    });

    it('renders a plain span when neither a route nor a modalComponent is configured', function (): void {
        $html = renderRelationLinkCell(['idField' => 'vehicle_id']);

        expect($html)->toContain('Vehicle A')
            ->not->toContain('$modal');
    });
});
