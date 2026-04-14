<?php

use Noerd\Services\FieldTypeRegistry;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;

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
    ], new class
    {
        public array $detailData = ['customer_id' => 12];
    }, null, 99))->toBe([
        'relationType' => 'customerRelation',
        'fieldName' => 'detailData.customer_id',
        'label' => 'Customer',
        'value' => 12,
        'required' => true,
        'readonly' => false,
        'modelId' => 99,
    ]);
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

    $component = new class
    {
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
    expect($definition->resolveTitle((object) []))->toBe('Seite');
});
