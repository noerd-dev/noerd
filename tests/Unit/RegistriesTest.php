<?php

declare(strict_types=1);

use Noerd\Contracts\DynamicNavigationProviderContract;
use Noerd\Services\ComponentSlotsRegistry;
use Noerd\Services\DynamicNavigationRegistry;
use Noerd\Services\FieldTypeRegistry;
use Noerd\Services\PicklistRegistry;
use Noerd\Services\RelationBoxRegistry;
use Noerd\Support\FieldTypeDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | The extension registries a module contributes to. Each is a fresh instance
 | per case, so nothing here depends on what the container happens to hold.
 */

class ZzRegistryBaseModel {}
class ZzRegistryChildModel extends ZzRegistryBaseModel {}

describe('ComponentSlotsRegistry', function (): void {
    it('starts empty so the core renders no module components of its own', function (): void {
        $registry = new ComponentSlotsRegistry();

        expect($registry->get(ComponentSlotsRegistry::TOP_BAR))->toBe([])
            ->and($registry->get(ComponentSlotsRegistry::LIST_HEADER_ACTIONS))->toBe([])
            ->and($registry->get(ComponentSlotsRegistry::DETAIL_HEADER_ACTIONS))->toBe([])
            ->and($registry->has(ComponentSlotsRegistry::TOP_BAR))->toBeFalse()
            ->and($registry->all())->toBe([]);
    });

    it('orders slot components by sort ascending regardless of registration order', function (): void {
        $registry = new ComponentSlotsRegistry();

        $registry->register('user-below-form', 'some-module::second', sort: 10);
        $registry->register('user-below-form', 'other-module::first', sort: 5);

        expect($registry->get('user-below-form'))->toBe([
            'other-module::first',
            'some-module::second',
        ]);
    });

    it('keeps registration order for equal slot sort values', function (): void {
        $registry = new ComponentSlotsRegistry();

        $registry->register(ComponentSlotsRegistry::TOP_BAR, 'some-module::top-bar.first');
        $registry->register(ComponentSlotsRegistry::TOP_BAR, 'other-module::top-bar.second');

        expect($registry->get(ComponentSlotsRegistry::TOP_BAR))->toBe([
            'some-module::top-bar.first',
            'other-module::top-bar.second',
        ]);
    });

    it('keeps slots independent of each other', function (): void {
        $registry = new ComponentSlotsRegistry();

        $registry->register(ComponentSlotsRegistry::LIST_HEADER_ACTIONS, 'some-module::list-action');
        $registry->register(ComponentSlotsRegistry::DETAIL_HEADER_ACTIONS, 'other-module::detail-action');
        $registry->register('customer-below-form', 'some-module::customer-extension');

        expect($registry->get(ComponentSlotsRegistry::LIST_HEADER_ACTIONS))->toBe(['some-module::list-action'])
            ->and($registry->get(ComponentSlotsRegistry::DETAIL_HEADER_ACTIONS))->toBe(['other-module::detail-action'])
            ->and($registry->get('customer-below-form'))->toBe(['some-module::customer-extension'])
            ->and($registry->has('customer-below-form'))->toBeTrue()
            ->and(array_keys($registry->all()))->toBe([
                ComponentSlotsRegistry::LIST_HEADER_ACTIONS,
                ComponentSlotsRegistry::DETAIL_HEADER_ACTIONS,
                'customer-below-form',
            ]);
    });
});

describe('DynamicNavigationRegistry', function (): void {
    it('registers and resolves a provider', function (): void {
        $registry = new DynamicNavigationRegistry();

        $provider = new class implements DynamicNavigationProviderContract {
            public function type(): string
            {
                return 'test-type';
            }

            public function items(): array
            {
                return [['title' => 'Test', 'link' => '/test']];
            }
        };

        $registry->register($provider);

        expect($registry->get('test-type'))->toBe($provider);
        expect($registry->get('test-type')->items())->toBe([['title' => 'Test', 'link' => '/test']]);
    });

    it('returns null for unregistered type', function (): void {
        $registry = new DynamicNavigationRegistry();

        expect($registry->get('nonexistent'))->toBeNull();
    });

    it('returns all registered providers', function (): void {
        $registry = new DynamicNavigationRegistry();

        $provider1 = new class implements DynamicNavigationProviderContract {
            public function type(): string
            {
                return 'type-a';
            }

            public function items(): array
            {
                return [];
            }
        };

        $provider2 = new class implements DynamicNavigationProviderContract {
            public function type(): string
            {
                return 'type-b';
            }

            public function items(): array
            {
                return [];
            }
        };

        $registry->register($provider1);
        $registry->register($provider2);

        expect($registry->all())->toHaveCount(2)
            ->toHaveKeys(['type-a', 'type-b']);
    });
});

describe('FieldTypeRegistry', function (): void {
    it('registers and resolves a field type definition', function (): void {
        $registry = new FieldTypeRegistry();
        $definition = FieldTypeDefinition::include(
            'test::components.forms.custom-field',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => [
                'field' => $field,
                'modelId' => $modelId,
            ],
        );

        $registry->register('custom', $definition);

        expect($registry->has('custom'))->toBeTrue();
        expect($registry->get('custom'))->toBe($definition);
        expect($registry->get('custom')?->resolveProps(['name' => 'detailData.custom'], null, null, 42))
            ->toBe([
                'field' => ['name' => 'detailData.custom'],
                'modelId' => 42,
            ]);
    });

    it('overwrites an existing field type definition', function (): void {
        $registry = new FieldTypeRegistry();

        $registry->register('custom', FieldTypeDefinition::include('test::first'));
        $registry->register('custom', FieldTypeDefinition::include('test::second'));

        expect($registry->get('custom')?->target)->toBe('test::second');
    });

    it('returns all registered field type definitions', function (): void {
        $registry = new FieldTypeRegistry();

        $registry->register('first', FieldTypeDefinition::include('test::first'));
        $registry->register('second', FieldTypeDefinition::livewire('test-second'));

        expect($registry->all())->toHaveCount(2)
            ->toHaveKeys(['first', 'second']);
    });
});

describe('PicklistRegistry', function (): void {
    it('starts empty so the core resolves no picklist of its own', function (): void {
        $registry = new PicklistRegistry();

        expect($registry->has('zz-status'))->toBeFalse()
            ->and($registry->get('zz-status'))->toBeNull();
    });

    it('registers and resolves a picklist provider', function (): void {
        $registry = new PicklistRegistry();
        $provider = fn(): array => [['value' => 'open', 'label' => 'Open']];

        $registry->register('zz-status', $provider);

        expect($registry->has('zz-status'))->toBeTrue()
            ->and($registry->get('zz-status'))->toBe($provider)
            ->and(($registry->get('zz-status'))())->toBe([['value' => 'open', 'label' => 'Open']]);
    });

    it('overwrites an existing picklist provider', function (): void {
        $registry = new PicklistRegistry();

        $registry->register('zz-status', fn(): array => ['first']);
        $registry->register('zz-status', fn(): array => ['second']);

        expect(($registry->get('zz-status'))())->toBe(['second']);
    });
});

describe('RelationBoxRegistry', function (): void {
    it('starts empty so a model gets no contributed tiles by default', function (): void {
        $registry = new RelationBoxRegistry();

        expect($registry->get(ZzRegistryBaseModel::class))->toBe([]);
    });

    it('orders tiles by sort ascending regardless of registration order', function (): void {
        $registry = new RelationBoxRegistry();

        $registry->register(ZzRegistryBaseModel::class, ['label' => 'Second'], sort: 10);
        $registry->register(ZzRegistryBaseModel::class, ['label' => 'First'], sort: 5);

        expect($registry->get(ZzRegistryBaseModel::class))->toBe([
            ['label' => 'First'],
            ['label' => 'Second'],
        ]);
    });

    it('keeps registration order for equal relation-box sort values', function (): void {
        $registry = new RelationBoxRegistry();

        $registry->register(ZzRegistryBaseModel::class, ['label' => 'First']);
        $registry->register(ZzRegistryBaseModel::class, ['label' => 'Second']);

        expect($registry->get(ZzRegistryBaseModel::class))->toBe([
            ['label' => 'First'],
            ['label' => 'Second'],
        ]);
    });

    it('keeps unrelated model classes independent of each other', function (): void {
        $registry = new RelationBoxRegistry();

        $registry->register(ZzRegistryBaseModel::class, ['label' => 'Base Tile']);
        $registry->register(ZzRegistryChildModel::class, ['label' => 'Child Tile']);

        expect($registry->get(ZzRegistryBaseModel::class))->toBe([['label' => 'Base Tile']]);
    });

    it('lets a subclass inherit tiles registered for its parent class', function (): void {
        $registry = new RelationBoxRegistry();

        $registry->register(ZzRegistryBaseModel::class, ['label' => 'Base Tile']);

        expect($registry->get(ZzRegistryChildModel::class))->toBe([['label' => 'Base Tile']])
            ->and($registry->get(ZzRegistryBaseModel::class))->toBe([['label' => 'Base Tile']]);
    });
});
