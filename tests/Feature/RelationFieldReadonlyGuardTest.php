<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Relation field readonly server guards', function (): void {

    beforeEach(function (): void {
        $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

        app(RelationFieldRegistry::class)->register('readonlyGuardRelation', RelationFieldDefinition::model(
            listComponent: 'readonly-guards-list',
            detailComponent: 'readonly-guard-detail',
            modelClass: null,
            titleResolver: 'name',
        ));
    });

    $props = [
        'relationType' => 'readonlyGuardRelation',
        'fieldName' => 'detailData.guard_id',
        'label' => 'Guard',
        'readonly' => true,
    ];

    it('ignores a selection event on a readonly relation field', function () use ($props): void {
        Livewire::test('noerd-relation-field', $props + ['value' => 7])
            ->dispatch('noerdRelationSelected', value: 42, context: 'detailData.guard_id')
            ->assertSet('value', 7)
            ->assertNotDispatched('setFieldValue');
    });

    it('ignores clear() on a readonly relation field', function () use ($props): void {
        Livewire::test('noerd-relation-field', $props + ['value' => 7])
            ->call('clear')
            ->assertSet('value', 7)
            ->assertNotDispatched('setFieldValue');
    });

    it('still selects and clears when not readonly', function () use ($props): void {
        Livewire::test('noerd-relation-field', ['readonly' => false] + $props)
            ->dispatch('noerdRelationSelected', value: 42, context: 'detailData.guard_id')
            ->assertSet('value', 42)
            ->call('clear')
            ->assertSet('value', null);
    });

    it('ignores selection, type switch and clear() on a readonly polymorphic field', function (): void {
        $props = [
            'fieldName' => 'detailData.record_id',
            'typeField' => 'detailData.record_type',
            'label' => 'Record',
            'readonly' => true,
            'value' => 7,
        ];

        Livewire::test('noerd-polymorphic-relation-field', $props)
            ->dispatch('noerdRelationSelected', value: 42, context: 'detailData.record_id')
            ->assertSet('value', 7)
            ->assertNotDispatched('setFieldValue')
            ->call('clear')
            ->assertSet('value', 7)
            ->assertNotDispatched('setFieldValue');
    });
});
