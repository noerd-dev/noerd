<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Relation field theme templates', function (): void {

    beforeEach(function (): void {
        $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);

        app(RelationFieldRegistry::class)->register('themeTestRelation', RelationFieldDefinition::model(
            listComponent: 'theme-tests-list',
            detailComponent: 'theme-test-detail',
            modelClass: null,
            titleResolver: 'name',
        ));
    });

    $props = [
        'relationType' => 'themeTestRelation',
        'fieldName' => 'detailData.theme_test_id',
        'label' => 'Theme Test',
    ];

    it('renders the default template with the label above the control', function () use ($props): void {
        $component = Livewire::test('noerd-relation-field', $props)
            ->assertSuccessful()
            ->assertDontSeeHtml('tabular-nums');

        assertNoElementHasClasses($component->html(), ['w-36', 'shrink-0', 'truncate']);
    });

    it('renders the compact template with the label to the left', function () use ($props): void {
        $component = Livewire::test('noerd-relation-field', $props + ['theme' => 'compact'])
            ->assertSuccessful();

        assertElementHasClasses($component->html(), ['w-36', 'shrink-0', 'truncate']);

        // The select button shrinks to the compact input height on the same element.
        assertElementHasClasses($component->html(), ['h-7!', 'px-2!']);
    });

    it('renders the numbered template inside the numbered row chrome', function () use ($props): void {
        $component = Livewire::test('noerd-relation-field', $props + ['theme' => 'numbered', 'number' => 7])
            ->assertSuccessful()
            ->assertSeeHtml('bg-zinc-100')
            ->assertSeeHtml('tabular-nums')
            ->assertSeeHtml('rounded-none');

        assertElementHasClasses($component->html(), ['text-right', 'truncate']);

        expect(preg_match('/tabular-nums[^"]*">\s*7\s*<\/div>/', $component->html()))->toBe(1);
    });

    it('falls back to the default template for a theme without a relation template', function () use ($props): void {
        $component = Livewire::test('noerd-relation-field', $props + ['theme' => 'does-not-exist'])
            ->assertSuccessful();

        assertNoElementHasClasses($component->html(), ['w-36', 'shrink-0', 'truncate']);
    });

    it('keeps the inherited selection listener working on every theme', function () use ($props): void {
        foreach (['default', 'compact', 'numbered'] as $theme) {
            Livewire::test('noerd-relation-field', $props + ['theme' => $theme])
                ->dispatch('noerdRelationSelected', value: 42, context: 'detailData.theme_test_id')
                ->assertSet('value', 42)
                ->assertDispatched('setFieldValue');
        }
    });
});

describe('Polymorphic relation field theme templates', function (): void {

    beforeEach(function (): void {
        $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);

        app(RelationFieldRegistry::class)->register('themeTestRelation', RelationFieldDefinition::model(
            listComponent: 'theme-tests-list',
            detailComponent: 'theme-test-detail',
            modelClass: null,
            titleResolver: 'name',
        ));
    });

    $props = [
        'fieldName' => 'detailData.related_id',
        'typeField' => 'detailData.related_type',
        'label' => 'Related',
        'allowedTypes' => ['themeTestRelation'],
    ];

    it('renders the compact template', function () use ($props): void {
        $component = Livewire::test('noerd-polymorphic-relation-field', $props + ['theme' => 'compact'])
            ->assertSuccessful()
            ->assertSeeHtml('h-7');

        assertElementHasClasses($component->html(), ['w-36', 'shrink-0', 'truncate']);
    });

    it('renders the numbered template inside the numbered row chrome', function () use ($props): void {
        $component = Livewire::test('noerd-polymorphic-relation-field', $props + ['theme' => 'numbered', 'number' => 3])
            ->assertSuccessful()
            ->assertSeeHtml('bg-zinc-100')
            ->assertSeeHtml('tabular-nums')
            ->assertSeeHtml('rounded-none');

        expect(preg_match('/tabular-nums[^"]*">\s*3\s*<\/div>/', $component->html()))->toBe(1);
    });
});
