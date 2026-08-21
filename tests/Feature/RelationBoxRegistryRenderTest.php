<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Services\RelationBoxRegistry;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->withExampleTenant()->create());

    // Installed modules register themselves at boot, so start from a clean registry:
    // these tests are about the core's merge mechanics, not about whoever is installed.
    app()->instance(RelationBoxRegistry::class, new RelationBoxRegistry());
});

function mountRegistryRelationBox(array $relations = []): array
{
    $tenant = Tenant::first() ?? Tenant::factory()->create();

    $component = Livewire::test('noerd::relation-box', [
        'modelClass' => Tenant::class,
        'modelId' => $tenant->id,
        'relations' => $relations,
    ]);

    return [$component, $tenant];
}

it('renders contributed tiles after the yaml tiles with closure count and resolved arguments', function (): void {
    app(RelationBoxRegistry::class)->register(Tenant::class, [
        'label' => 'Contributed Tile',
        'heroicon' => 'document-text',
        'component' => 'zz::contributed-list',
        'arguments' => ['tenantId' => '$modelId'],
        'count' => fn(Tenant $tenant): int => 7,
    ]);

    [$component, $tenant] = mountRegistryRelationBox([
        [
            'label' => 'Yaml Tile',
            'heroicon' => 'users',
            'relation' => 'tenantApps',
            'component' => 'zz::tenant-apps-list',
        ],
    ]);

    $html = html_entity_decode($component->html());

    expect($html)->toContain('Contributed Tile')
        ->toContain('(7)')
        ->toContain('zz::contributed-list')
        ->and(mb_strpos($html, 'Yaml Tile'))->toBeLessThan(mb_strpos($html, 'Contributed Tile'))
        ->and($component->get('resolvedRelations')[1]['arguments'])->toBe(['tenantId' => $tenant->id]);
});

it('hides a contributed tile whose visible closure returns false', function (): void {
    app(RelationBoxRegistry::class)->register(Tenant::class, [
        'label' => 'Hidden Tile',
        'component' => 'zz::hidden-list',
        'visible' => fn(): bool => false,
    ]);
    app(RelationBoxRegistry::class)->register(Tenant::class, [
        'label' => 'Shown Tile',
        'component' => 'zz::shown-list',
        'visible' => fn(): bool => true,
    ]);

    [$component] = mountRegistryRelationBox();

    $component->assertSee('Shown Tile')
        ->assertDontSee('Hidden Tile');
});

it('re-resolves contributed counts when a modal closes', function (): void {
    $counter = new stdClass();
    $counter->value = 3;

    app(RelationBoxRegistry::class)->register(Tenant::class, [
        'label' => 'Counting Tile',
        'component' => 'zz::counting-list',
        'count' => fn(): int => $counter->value,
    ]);

    [$component] = mountRegistryRelationBox();

    $component->assertSee('Counting Tile (3)');

    $counter->value = 5;

    $component->dispatch('closeTopModal')
        ->assertSee('Counting Tile (5)');
});

it('keeps yaml-only behavior unchanged without registrations', function (): void {
    [$component] = mountRegistryRelationBox([
        [
            'label' => 'Yaml Tile',
            'heroicon' => 'users',
            'relation' => 'tenantApps',
            'component' => 'zz::tenant-apps-list',
        ],
    ]);

    $html = html_entity_decode($component->html());

    expect($html)->toContain('Yaml Tile')
        ->toContain('$modal(')
        ->toContain('zz::tenant-apps-list');
});
