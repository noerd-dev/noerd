<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Customer\Models\Customer;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->withExampleTenant()->create());
});

/**
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

it('renders a relation badge with the related record title', function (): void {
    $customer = Customer::factory()->create([
        'tenant_id' => TenantHelper::getSelectedTenantId(),
        'name' => 'Erika Musterfrau',
    ]);

    $html = renderTableCell([
        'type' => 'relationBadge',
        'columnValue' => 'customer_id',
        'value' => $customer->id,
    ]);

    expect($html)->toContain('rounded-full')
        ->and($html)->toContain('Erika Musterfrau');
});

it('renders an empty cell for an empty foreign key', function (): void {
    $html = renderTableCell([
        'type' => 'relationBadge',
        'columnValue' => 'customer_id',
        'value' => null,
    ]);

    expect($html)->toContain('<td')
        ->and($html)->not->toContain('rounded-full');
});

it('renders a custom attribute cell by traversing the json column', function (): void {
    $customer = Customer::factory()->create([
        'tenant_id' => TenantHelper::getSelectedTenantId(),
        'custom_attributes' => ['plz_zone' => 'Zone Nord'],
    ]);

    $html = renderTableCell([
        'type' => 'customAttribute',
        'columnValue' => 'custom_attributes.plz_zone',
        'value' => '',
        'rowData' => $customer,
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
