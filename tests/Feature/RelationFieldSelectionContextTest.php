<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Relation field selection context', function (): void {

    beforeEach(function (): void {
        $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

        app(RelationFieldRegistry::class)->register('contextGuardRelation', RelationFieldDefinition::model(
            listComponent: 'context-guards-list',
            detailComponent: 'context-guard-detail',
            modelClass: null,
            titleResolver: 'name',
        ));
    });

    $props = [
        'relationType' => 'contextGuardRelation',
        'fieldName' => 'detailData.guard_id',
        'label' => 'Guard',
    ];

    it('adopts a selection dispatched with its own field name as context', function () use ($props): void {
        Livewire::test('noerd-relation-field', $props + ['value' => 7])
            ->dispatch('noerdRelationSelected', value: 42, context: 'detailData.guard_id')
            ->assertSet('value', 42);
    });

    it('ignores a selection dispatched with an empty context', function () use ($props): void {
        // A picker opened without a context dispatches NoerdList::$context's
        // default (''). That selection belongs to a custom listActionMethod
        // flow — it must never be adopted by every relation field on the page.
        Livewire::test('noerd-relation-field', $props + ['value' => 7])
            ->dispatch('noerdRelationSelected', value: 42, context: '')
            ->assertSet('value', 7);
    });

    it('ignores a selection dispatched with no context at all', function () use ($props): void {
        Livewire::test('noerd-relation-field', $props + ['value' => 7])
            ->dispatch('noerdRelationSelected', value: 42)
            ->assertSet('value', 7);
    });

    it('ignores a selection dispatched for another field', function () use ($props): void {
        Livewire::test('noerd-relation-field', $props + ['value' => 7])
            ->dispatch('noerdRelationSelected', value: 42, context: 'detailData.other_id')
            ->assertSet('value', 7);
    });

    it('scopes the picker context and the parent sync to the owning detail', function () use ($props): void {
        $component = Livewire::test('noerd-relation-field', $props + ['value' => 7, 'owner' => 'owner-a']);

        expect($component->instance()->selectionContext())->toBe('detailData.guard_id@owner-a');

        // A selection made for the same field on ANOTHER detail is not adopted.
        $component->dispatch('noerdRelationSelected', value: 42, context: 'detailData.guard_id@owner-b')
            ->assertSet('value', 7);

        $component->dispatch('noerdRelationSelected', value: 42, context: 'detailData.guard_id@owner-a')
            ->assertSet('value', 42)
            ->assertDispatched('setFieldValue', owner: 'owner-a');
    });
});
