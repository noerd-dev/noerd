<?php

declare(strict_types=1);

use Noerd\Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<string, mixed>  $columnConfig
 * @param  array<string, mixed>  $rowData
 */
function renderRelationLinkCell(array $columnConfig, array $rowData = ['vehicle_id' => 42]): string
{
    return view('noerd::components.table.table-cell', [
        'row' => 0,
        'column' => 0,
        'label' => '',
        'value' => 'Vehicle A',
        'readOnly' => true,
        'id' => 1,
        'columnValue' => 'vehicle',
        'type' => 'relation_link',
        'action' => 'tableAction',
        'actions' => null,
        'columnConfig' => $columnConfig,
        'rowData' => $rowData,
    ])->render();
}

it('renders a relation_link route cell as $modalRoute with the id from idField', function (): void {
    registerTestLivewireRoute('zz-cell-vehicle/{modelId}', 'noerd::theme-test', 'zz.cell.vehicle');

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
    registerTestLivewireRoute('zz-cell-default/{modelId}', 'noerd::theme-test', 'zz.cell.default');

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
