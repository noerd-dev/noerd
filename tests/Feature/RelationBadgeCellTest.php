<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class BadgeCellFixtureGadget extends Model
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
    $gadget = BadgeCellFixtureGadget::create(['name' => 'Erika Musterfrau']);

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
    $gadget = BadgeCellFixtureGadget::create([
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
