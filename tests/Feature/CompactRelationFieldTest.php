<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $this->actingAs($this->admin);

    app(RelationFieldRegistry::class)->register('compactTestRelation', RelationFieldDefinition::model(
        listComponent: 'compact-tests-list',
        detailComponent: 'compact-test-detail',
        modelClass: null,
        titleResolver: 'name',
    ));
});

it('shrinks the relation select button to match the compact input height', function (): void {
    $component = Livewire::test('noerd-relation-field', [
        'relationType' => 'compactTestRelation',
        'fieldName' => 'detailData.compact_test_id',
        'label' => 'Compact Test',
        'theme' => 'compact',
    ])->assertOk();

    assertElementHasClasses($component->html(), ['h-7!', 'px-2!']);
});
