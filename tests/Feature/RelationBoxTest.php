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
    // these tests are about the core's own tile mechanics, not about whoever is installed.
    app()->instance(RelationBoxRegistry::class, new RelationBoxRegistry());
});

/**
 * Mount the relation box on a tenant record with the given YAML tiles.
 *
 * @param  array<int, array<string, mixed>>  $relations
 * @return array{0: \Livewire\Features\SupportTesting\Testable, 1: Tenant}
 */
function mountRelationBox(array $relations = []): array
{
    $tenant = Tenant::first() ?? Tenant::factory()->create();

    $component = Livewire::test('noerd::relation-box', [
        'modelClass' => Tenant::class,
        'modelId' => $tenant->id,
        'relations' => $relations,
    ]);

    return [$component, $tenant];
}

/**
 * @param  array<int, array<string, mixed>>  $relations
 */
function renderRelationBox(array $relations): string
{
    [$component] = mountRelationBox($relations);

    return html_entity_decode($component->html());
}

describe('route tiles', function (): void {

    it('opens a relation tile via $modalRoute without rewriting the url', function (): void {
        registerTestLivewireRoute('zz-relation-tiles', 'noerd-test::theme-test', 'zz.relation.tiles');

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
});

describe('registry contributions', function (): void {

    it('renders contributed tiles after the yaml tiles with closure count and resolved arguments', function (): void {
        app(RelationBoxRegistry::class)->register(Tenant::class, [
            'label' => 'Contributed Tile',
            'heroicon' => 'document-text',
            'component' => 'zz::contributed-list',
            'arguments' => ['tenantId' => '$modelId'],
            'count' => fn(Tenant $tenant): int => 7,
        ]);

        [$component, $tenant] = mountRelationBox([
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

        [$component] = mountRelationBox();

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

        [$component] = mountRelationBox();

        $component->assertSee('Counting Tile (3)');

        $counter->value = 5;

        $component->dispatch('closeTopModal')
            ->assertSee('Counting Tile (5)');
    });

    it('keeps yaml-only behavior unchanged without registrations', function (): void {
        $html = renderRelationBox([
            [
                'label' => 'Yaml Tile',
                'heroicon' => 'users',
                'relation' => 'tenantApps',
                'component' => 'zz::tenant-apps-list',
                'arguments' => ['tenantId' => '$modelId'],
            ],
        ]);

        expect($html)->toContain('Yaml Tile')
            ->toContain('$modal(')
            ->toContain('zz::tenant-apps-list')
            ->not->toContain('$modalRoute(');
    });
});

describe('count fallback', function (): void {

    it('computes a zero count for an unknown relation method instead of failing', function (): void {
        [$component] = mountRelationBox([
            [
                'label' => 'Unknown',
                'heroicon' => 'users',
                'relation' => 'doesNotExist',
                'component' => 'zz::tenant-apps-list',
                'arguments' => ['tenantId' => '$modelId'],
            ],
        ]);

        $component
            ->assertSet('resolvedRelations.0.label', 'Unknown')
            ->assertSet('resolvedRelations.0.count', 0);
    });
});
