<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * @param  array<int, array<string, mixed>>  $relations
 */
function renderRelationBox(array $relations): string
{
    $tenant = Tenant::first() ?? Tenant::factory()->create();

    return html_entity_decode(
        Livewire::test('noerd::relation-box', [
            'modelClass' => Tenant::class,
            'modelId' => $tenant->id,
            'relations' => $relations,
        ])->html(),
    );
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->withExampleTenant()->create());
});

it('opens a relation tile via $modalRoute without rewriting the url', function (): void {
    registerTestLivewireRoute('zz-relation-tiles', 'noerd::theme-test', 'zz.relation.tiles');

    $html = renderRelationBox([
        [
            'label' => 'Tenant Apps',
            'heroicon' => 'users',
            'relation' => 'tenantApps',
            'route' => 'zz.relation.tiles',
            'arguments' => ['tenantId' => '$modelId'],
        ],
    ]);

    expect($html)->toContain('$modalRoute(')
        ->toContain('zz.relation.tiles')
        ->toContain('rewriteUrl: false')
        ->not->toContain('$modal(');
});

it('falls back to the component tile when the relation route is not registered', function (): void {
    $html = renderRelationBox([
        [
            'label' => 'Tenant Apps',
            'heroicon' => 'users',
            'relation' => 'tenantApps',
            'route' => 'zz.relation.route.that.does.not.exist',
            'component' => 'zz::tenant-apps-list',
        ],
    ]);

    expect($html)->toContain('$modal(')
        ->toContain('zz::tenant-apps-list')
        ->not->toContain('$modalRoute(');
});

it('keeps rendering component-only relation tiles unchanged', function (): void {
    $html = renderRelationBox([
        [
            'label' => 'Tenant Apps',
            'heroicon' => 'users',
            'relation' => 'tenantApps',
            'component' => 'zz::tenant-apps-list',
            'arguments' => ['tenantId' => '$modelId'],
        ],
    ]);

    expect($html)->toContain('$modal(')
        ->toContain('zz::tenant-apps-list')
        ->not->toContain('$modalRoute(');
});
