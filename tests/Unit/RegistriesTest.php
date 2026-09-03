<?php

declare(strict_types=1);

use Noerd\Contracts\DynamicNavigationProviderContract;
use Noerd\Services\DetailSlotsRegistry;
use Noerd\Services\DynamicNavigationRegistry;
use Noerd\Services\FieldTypeRegistry;
use Noerd\Services\HeaderActionsRegistry;
use Noerd\Services\PicklistRegistry;
use Noerd\Services\RelationBoxRegistry;
use Noerd\Services\TopBarRegistry;
use Noerd\Support\FieldTypeDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | The extension registries a module contributes to. Each is a fresh instance
 | per case, so nothing here depends on what the container happens to hold.
 */

class ZzRegistryBaseModel {}
class ZzRegistryChildModel extends ZzRegistryBaseModel {}

describe('DetailSlotsRegistry', function (): void {
    it('orders slot components by sort ascending regardless of registration order', function (): void {
        $registry = new DetailSlotsRegistry();

        $registry->register('user-below-form', 'some-module::second', sort: 10);
        $registry->register('user-below-form', 'other-module::first', sort: 5);

        expect($registry->for('user-below-form'))->toBe([
            'other-module::first',
            'some-module::second',
        ]);
    });

    it('keeps registration order for equal slot sort values', function (): void {
        $registry = new DetailSlotsRegistry();

        $registry->register('user-below-form', 'some-module::first');
        $registry->register('user-below-form', 'other-module::second');

        expect($registry->for('user-below-form'))->toBe([
            'some-module::first',
            'other-module::second',
        ]);
    });

    it('keeps slots independent of each other', function (): void {
        $registry = new DetailSlotsRegistry();

        $registry->register('user-below-form', 'some-module::user-extension');
        $registry->register('customer-below-form', 'some-module::customer-extension');

        expect($registry->for('user-below-form'))->toBe(['some-module::user-extension'])
            ->and($registry->for('customer-below-form'))->toBe(['some-module::customer-extension']);
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

        expect($registry->resolve('test-type'))->toBe($provider);
        expect($registry->resolve('test-type')->items())->toBe([['title' => 'Test', 'link' => '/test']]);
    });

    it('returns null for unregistered type', function (): void {
        $registry = new DynamicNavigationRegistry();

        expect($registry->resolve('nonexistent'))->toBeNull();
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
        expect($registry->resolve('custom'))->toBe($definition);
        expect($registry->resolve('custom')?->resolveProps(['name' => 'detailData.custom'], null, null, 42))
            ->toBe([
                'field' => ['name' => 'detailData.custom'],
                'modelId' => 42,
            ]);
    });

    it('overwrites an existing field type definition', function (): void {
        $registry = new FieldTypeRegistry();

        $registry->register('custom', FieldTypeDefinition::include('test::first'));
        $registry->register('custom', FieldTypeDefinition::include('test::second'));

        expect($registry->resolve('custom')?->target)->toBe('test::second');
    });

    it('returns all registered field type definitions', function (): void {
        $registry = new FieldTypeRegistry();

        $registry->register('first', FieldTypeDefinition::include('test::first'));
        $registry->register('second', FieldTypeDefinition::livewire('test-second'));

        expect($registry->all())->toHaveCount(2)
            ->toHaveKeys(['first', 'second']);
    });
});

describe('HeaderActionsRegistry', function (): void {
    it('starts empty so the core renders no module actions of its own', function (): void {
        $registry = new HeaderActionsRegistry();

        expect($registry->listActions())->toBe([])
            ->and($registry->detailActions())->toBe([]);
    });

    it('keeps list and detail registrations in separate slots', function (): void {
        $registry = new HeaderActionsRegistry();

        $registry->registerListAction('some-module::list-action');
        $registry->registerDetailAction('other-module::detail-action');

        expect($registry->listActions())->toBe(['some-module::list-action'])
            ->and($registry->detailActions())->toBe(['other-module::detail-action']);
    });

    it('returns registered actions in registration order', function (): void {
        $registry = new HeaderActionsRegistry();

        $registry->registerListAction('some-module::first');
        $registry->registerListAction('other-module::second');

        expect($registry->listActions())->toBe([
            'some-module::first',
            'other-module::second',
        ]);
    });
});

describe('PicklistRegistry', function (): void {
    it('starts empty so the core resolves no picklist of its own', function (): void {
        $registry = new PicklistRegistry();

        expect($registry->has('zz-status'))->toBeFalse()
            ->and($registry->resolve('zz-status'))->toBeNull();
    });

    it('registers and resolves a picklist provider', function (): void {
        $registry = new PicklistRegistry();
        $provider = fn(): array => [['value' => 'open', 'label' => 'Open']];

        $registry->register('zz-status', $provider);

        expect($registry->has('zz-status'))->toBeTrue()
            ->and($registry->resolve('zz-status'))->toBe($provider)
            ->and(($registry->resolve('zz-status'))())->toBe([['value' => 'open', 'label' => 'Open']]);
    });

    it('overwrites an existing picklist provider', function (): void {
        $registry = new PicklistRegistry();

        $registry->register('zz-status', fn(): array => ['first']);
        $registry->register('zz-status', fn(): array => ['second']);

        expect(($registry->resolve('zz-status'))())->toBe(['second']);
    });
});

describe('RelationBoxRegistry', function (): void {
    it('starts empty so a model gets no contributed tiles by default', function (): void {
        $registry = new RelationBoxRegistry();

        expect($registry->for(ZzRegistryBaseModel::class))->toBe([]);
    });

    it('orders tiles by sort ascending regardless of registration order', function (): void {
        $registry = new RelationBoxRegistry();

        $registry->register(ZzRegistryBaseModel::class, ['label' => 'Second'], sort: 10);
        $registry->register(ZzRegistryBaseModel::class, ['label' => 'First'], sort: 5);

        expect($registry->for(ZzRegistryBaseModel::class))->toBe([
            ['label' => 'First'],
            ['label' => 'Second'],
        ]);
    });

    it('keeps registration order for equal relation-box sort values', function (): void {
        $registry = new RelationBoxRegistry();

        $registry->register(ZzRegistryBaseModel::class, ['label' => 'First']);
        $registry->register(ZzRegistryBaseModel::class, ['label' => 'Second']);

        expect($registry->for(ZzRegistryBaseModel::class))->toBe([
            ['label' => 'First'],
            ['label' => 'Second'],
        ]);
    });

    it('keeps unrelated model classes independent of each other', function (): void {
        $registry = new RelationBoxRegistry();

        $registry->register(ZzRegistryBaseModel::class, ['label' => 'Base Tile']);
        $registry->register(ZzRegistryChildModel::class, ['label' => 'Child Tile']);

        expect($registry->for(ZzRegistryBaseModel::class))->toBe([['label' => 'Base Tile']]);
    });

    it('lets a subclass inherit tiles registered for its parent class', function (): void {
        $registry = new RelationBoxRegistry();

        $registry->register(ZzRegistryBaseModel::class, ['label' => 'Base Tile']);

        expect($registry->for(ZzRegistryChildModel::class))->toBe([['label' => 'Base Tile']])
            ->and($registry->for(ZzRegistryBaseModel::class))->toBe([['label' => 'Base Tile']]);
    });
});

describe('TopBarRegistry', function (): void {
    it('starts empty so the core renders no module components of its own', function (): void {
        expect((new TopBarRegistry())->all())->toBe([]);
    });

    it('returns registered components in registration order', function (): void {
        $registry = new TopBarRegistry();

        $registry->register('some-module::top-bar.first');
        $registry->register('other-module::top-bar.second');

        expect($registry->all())->toBe([
            'some-module::top-bar.first',
            'other-module::top-bar.second',
        ]);
    });
});
