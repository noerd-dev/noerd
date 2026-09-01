<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Models\NoerdUser;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * The YAML field key `helpText` renders a question-mark tooltip next to the field
 * label. These tests use synthetic layouts only — never a shipped app-config.
 */
describe('Field helpText tooltip', function (): void {

    beforeEach(function (): void {
        $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);
    });

    $fieldsWithHelpText = fn(string $helpText = 'What this field is for'): array => [
        ['name' => 'model.title', 'label' => 'Title', 'type' => 'text', 'colspan' => 6, 'helpText' => $helpText],
        ['name' => 'model.status', 'label' => 'Status', 'type' => 'select', 'colspan' => 6, 'helpText' => $helpText,
            'options' => [['value' => 'a', 'label' => 'A']]],
        ['name' => 'model.notes', 'label' => 'Notes', 'type' => 'textarea', 'colspan' => 12, 'helpText' => $helpText],
        ['name' => 'model.flag', 'label' => 'Flag', 'type' => 'checkbox', 'colspan' => 6, 'helpText' => $helpText],
    ];

    it('renders the tooltip for every field type in the [{0}] theme', function (string $theme) use ($fieldsWithHelpText): void {
        $component = Livewire::test('noerd-test::theme-test', [
            'initialModel' => [],
            'theme' => $theme,
            'fields' => $fieldsWithHelpText(),
        ])
            ->assertSuccessful()
            ->assertSeeHtml('x-teleport="body"')
            ->assertSeeHtml('role="tooltip"')
            ->assertSee('What this field is for');

        // text, select, textarea and checkbox — the checkbox proves the raw-label path
        // and, under compact/numbered, the fallback to the default theme's template.
        expect(mb_substr_count($component->html(), 'role="tooltip"'))->toBe(4);
    })->with(['default', 'compact', 'numbered']);

    it('renders no tooltip when helpText is absent in the [{0}] theme', function (string $theme): void {
        Livewire::test('noerd-test::theme-test', [
            'initialModel' => [],
            'theme' => $theme,
            'fields' => [
                ['name' => 'model.title', 'label' => 'Title', 'type' => 'text', 'colspan' => 6],
                ['name' => 'model.flag', 'label' => 'Flag', 'type' => 'checkbox', 'colspan' => 6],
            ],
        ])
            ->assertSuccessful()
            ->assertDontSeeHtml('role="tooltip"');
    })->with(['default', 'compact', 'numbered']);

    it('treats an empty helpText as absent', function () use ($fieldsWithHelpText): void {
        Livewire::test('noerd-test::theme-test', [
            'initialModel' => [],
            'theme' => 'default',
            'fields' => $fieldsWithHelpText('   '),
        ])
            ->assertSuccessful()
            ->assertDontSeeHtml('role="tooltip"');
    });

    it('does not leak the help text to the next field', function (string $theme): void {
        $component = Livewire::test('noerd-test::theme-test', [
            'initialModel' => [],
            'theme' => $theme,
            'fields' => [
                ['name' => 'model.title', 'label' => 'Title', 'type' => 'text', 'colspan' => 6, 'helpText' => 'Only here'],
                ['name' => 'model.notes', 'label' => 'Notes', 'type' => 'textarea', 'colspan' => 6],
                ['type' => 'block', 'title' => 'Nested', 'colspan' => 12, 'fields' => [
                    ['name' => 'model.nested', 'label' => 'Nested', 'type' => 'text', 'colspan' => 6],
                ]],
            ],
        ])->assertSuccessful();

        // Exactly one tooltip: the field that declares helpText. The static FieldContext
        // must not bleed into the following field or into the nested block.
        expect(mb_substr_count($component->html(), 'role="tooltip"'))->toBe(1);
    })->with(['default', 'compact', 'numbered']);

    it('translates the help text', function (): void {
        app('translator')->addLines(['*.Explain this field' => 'Erkläre dieses Feld'], 'de');
        app()->setLocale('de');

        Livewire::test('noerd-test::theme-test', [
            'initialModel' => [],
            'theme' => 'default',
            'fields' => [
                ['name' => 'model.title', 'label' => 'Title', 'type' => 'text', 'colspan' => 6, 'helpText' => 'Explain this field'],
            ],
        ])
            ->assertSuccessful()
            ->assertSee('Erkläre dieses Feld')
            ->assertDontSee('Explain this field');
    });

    it('renders the tooltip on a relation field in the [{0}] theme', function (string $theme): void {
        app(RelationFieldRegistry::class)->register('helpTextRelation', RelationFieldDefinition::model(
            listComponent: 'help-text-tests-list',
            detailComponent: 'help-text-test-detail',
            modelClass: null,
            titleResolver: 'name',
        ));

        Livewire::test('noerd-relation-field', [
            'relationType' => 'helpTextRelation',
            'fieldName' => 'detailData.help_text_test_id',
            'label' => 'Help Text Test',
            'theme' => $theme,
            'helpText' => 'Pick the related record',
        ])
            ->assertSuccessful()
            ->assertSeeHtml('role="tooltip"')
            ->assertSee('Pick the related record');
    })->with(['default', 'compact', 'numbered']);
});
