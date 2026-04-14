<?php

use Noerd\Services\FieldTypeRegistry;
use Noerd\Support\FieldTypeDefinition;

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
