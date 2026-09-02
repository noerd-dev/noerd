<?php

declare(strict_types=1);

use Noerd\Services\FieldTypeRegistry;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class);

it('registers a relation type and exposes it via field type registry', function (): void {
    $fieldTypeRegistry = new FieldTypeRegistry();
    $relationFieldRegistry = new RelationFieldRegistry($fieldTypeRegistry);

    $relationFieldRegistry->register('customerRelation', RelationFieldDefinition::model(
        listComponent: 'customers-list',
        detailComponent: 'customer-detail',
        modelClass: null,
        titleResolver: 'name',
    ));

    expect($relationFieldRegistry->has('customerRelation'))->toBeTrue();
    expect($relationFieldRegistry->resolve('customerRelation')?->listComponent)->toBe('customers-list');

    $fieldTypeDefinition = $fieldTypeRegistry->resolve('customerRelation');

    expect($fieldTypeDefinition?->kind)->toBe('livewire');
    expect($fieldTypeDefinition?->target)->toBe('noerd-relation-field');
    expect($fieldTypeDefinition?->resolveProps([
        'name' => 'detailData.customer_id',
        'label' => 'Customer',
        'required' => true,
    ], new class {
        public array $detailData = ['customer_id' => 12];
    }, null, 99))->toBe([
        'relationType' => 'customerRelation',
        'fieldName' => 'detailData.customer_id',
        'label' => 'Customer',
        'value' => 12,
        'required' => true,
        'readonly' => false,
        'helpText' => '',
        'modelId' => 99,
        'owner' => null,
        'errorMessages' => [],
        'theme' => 'default',
    ]);
});

it('renders a relation type through its custom field component when one is registered', function (): void {
    $fieldTypeRegistry = new FieldTypeRegistry();
    $relationFieldRegistry = new RelationFieldRegistry($fieldTypeRegistry);

    $relationFieldRegistry->register('customerAddressCardRelation', RelationFieldDefinition::model(
        listComponent: 'customer-addresses-list',
        detailComponent: 'customer-address-detail',
        modelClass: null,
        titleResolver: 'label',
        fieldComponent: 'customer::customer-address-card-field',
    ));

    $fieldTypeDefinition = $fieldTypeRegistry->resolve('customerAddressCardRelation');

    expect($fieldTypeDefinition?->kind)->toBe('livewire');
    expect($fieldTypeDefinition?->target)->toBe('customer::customer-address-card-field');
    // The custom component receives the identical props as the generic renderer.
    expect($fieldTypeDefinition?->resolveProps([
        'name' => 'detailData.default_invoice_address_id',
        'label' => 'Default Invoice Address',
    ], new class {
        public array $detailData = ['default_invoice_address_id' => 7];
    }, null, 3)['value'])->toBe(7);
});

it('passes the row number on only when the theme numbered the field', function (): void {
    $fieldTypeRegistry = new FieldTypeRegistry();
    $relationFieldRegistry = new RelationFieldRegistry($fieldTypeRegistry);

    $relationFieldRegistry->register('customerRelation', RelationFieldDefinition::model(
        listComponent: 'customers-list',
        detailComponent: 'customer-detail',
        modelClass: null,
        titleResolver: 'name',
    ));

    $definition = $fieldTypeRegistry->resolve('customerRelation');
    $field = ['name' => 'detailData.customer_id', 'label' => 'Customer'];

    expect($definition?->resolveProps($field, null, null, null))->not->toHaveKey('number')
        ->and($definition?->resolveProps($field + ['number' => 3], null, null, null)['number'])->toBe(3);
});

it('resolves top-level relation values from the parent component', function (): void {
    $fieldTypeRegistry = new FieldTypeRegistry();
    $relationFieldRegistry = new RelationFieldRegistry($fieldTypeRegistry);

    $relationFieldRegistry->register('projectRelation', RelationFieldDefinition::model(
        listComponent: 'projects-list',
        detailComponent: 'project-detail',
        modelClass: null,
        titleResolver: 'name',
    ));

    $definition = $fieldTypeRegistry->resolve('projectRelation');

    $component = new class {
        public int $projectId = 44;
    };

    expect($definition?->resolveProps([
        'name' => 'projectId',
        'label' => 'Project',
    ], $component, null, null)['value'])->toBe(44);
});

it('normalizes translated relation titles and derives default select event names', function (): void {
    $definition = RelationFieldDefinition::model(
        listComponent: 'pages-list',
        detailComponent: null,
        modelClass: null,
        titleResolver: fn(mixed $model): mixed => ['de' => 'Seite', 'en' => 'Page'],
    );

    expect($definition->getDetailComponent())->toBe('page-detail');
    expect($definition->getSelectEvent())->toBe('pageSelected');
    // Without a booted application the language fallback is 'en'; with one it
    // follows the session language / app locale.
    expect($definition->resolveTitle((object) []))->toBe('Page');
});
