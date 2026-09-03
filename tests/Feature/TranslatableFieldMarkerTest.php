<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Enums\Profile;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Translatable fields must be recognisable at a glance: a light blue frame in the
 * detail form (in every theme) and on the list cell. The tests below drive the
 * MECHANICS with synthetic layouts — never the content of a shipped YAML config.
 */
describe('Translatable field marker in detail forms', function (): void {

    beforeEach(function (): void {
        $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
        $this->actingAs($this->admin);

        $this->translatableFields = [
            ['name' => 'model.title', 'label' => 'Title', 'type' => 'translatableText', 'colspan' => 6],
            ['name' => 'model.body', 'label' => 'Body', 'type' => 'translatableTextarea', 'colspan' => 12],
        ];
    });

    it('frames a translatable field in blue in the default theme', function (): void {
        $html = Livewire::test('noerd-test::theme-test', [
            'initialModel' => [],
            'theme' => 'default',
            'fields' => $this->translatableFields,
        ])->assertSuccessful()->html();

        assertElementHasClasses($html, ['border-sky-300', 'rounded-lg']);
        expect(mb_substr_count($html, 'border-sky-300'))->toBeGreaterThanOrEqual(2);
    });

    it('frames a translatable field in blue in the compact theme and keeps the compact layout', function (): void {
        $html = Livewire::test('noerd-test::theme-test', [
            'initialModel' => [],
            'theme' => 'compact',
            'fields' => $this->translatableFields,
        ])->assertSuccessful()->html();

        // Blue frame + the compact control metrics, not the default theme's.
        assertElementHasClasses($html, ['border-sky-300', 'rounded-sm', 'h-7']);
        // Label to the LEFT of the input is the compact signature.
        assertElementHasClasses($html, ['w-36', 'shrink-0', 'truncate']);
        assertNoElementHasClasses($html, ['border-sky-300', 'h-10']);
    });

    it('frames a translatable field in blue in the numbered theme and keeps the numbered row chrome', function (): void {
        $html = Livewire::test('noerd-test::theme-test', [
            'initialModel' => [],
            'theme' => 'numbered',
            'fields' => $this->translatableFields,
        ])->assertSuccessful()->html();

        assertElementHasClasses($html, ['border-sky-400', 'rounded-none', 'h-9']);
        // Numbered row chrome: gray full-width row with the leading number cell.
        expect($html)->toContain('bg-zinc-100')
            ->and($html)->toContain('tabular-nums')
            ->and($html)->toContain('col-span-full');
    });

    it('does not frame a non-translatable field', function (): void {
        $html = Livewire::test('noerd-test::theme-test', [
            'initialModel' => [],
            'theme' => 'compact',
            'fields' => [
                ['name' => 'model.code', 'label' => 'Code', 'type' => 'text', 'colspan' => 6],
            ],
        ])->assertSuccessful()->html();

        expect($html)->not->toContain('border-sky-300')
            ->and($html)->not->toContain('border-sky-400');
    });

    it('marks the label with a hoverable affordance explaining the field is translatable', function (string $theme): void {
        $html = Livewire::test('noerd-test::theme-test', [
            'initialModel' => [],
            'theme' => $theme,
            'fields' => $this->translatableFields,
        ])->assertSuccessful()->html();

        // One language affordance per translatable field, carrying the explanation.
        expect(mb_substr_count($html, 'This field is translatable.'))->toBe(4)
            ->and($html)->toContain('text-sky-500');
    })->with(['default', 'compact', 'numbered']);

    it('does not mark the label of a non-translatable field', function (): void {
        $html = Livewire::test('noerd-test::theme-test', [
            'initialModel' => [],
            'theme' => 'default',
            'fields' => [
                ['name' => 'model.code', 'label' => 'Code', 'type' => 'text', 'colspan' => 6],
            ],
        ])->assertSuccessful()->html();

        expect($html)->not->toContain('This field is translatable.');
    });

    it('binds a translatable field to the selected language in every theme', function (string $theme): void {
        session([SetupLanguage::SESSION_KEY => 'en']);

        $html = Livewire::test('noerd-test::theme-test', [
            'initialModel' => [],
            'theme' => $theme,
            'fields' => $this->translatableFields,
        ])->assertSuccessful()->html();

        expect($html)->toContain('model.title.en')
            ->and($html)->toContain('model.body.en');
    })->with(['default', 'compact', 'numbered']);
});

describe('Translatable field marker in lists', function (): void {

    beforeEach(function (): void {
        config(['noerd.collections.mode' => 'yaml']);
        config(['noerd.collections.show_definitions_ui' => false]);
        app()->forgetInstance(SetupCollectionDefinitionRepositoryContract::class);
        app()->forgetInstance(SetupCollectionHelper::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = NoerdUser::factory()->create();
        $this->user->tenants()->attach($this->tenant->id, ['profile_key' => Profile::Admin->value]);
        TenantHelper::setSelectedTenantId($this->tenant->id);
        TenantHelper::setSelectedApp('SETUP');
        $this->actingAs($this->user);

        // Synthetic collection: one translatable and one plain field.
        $this->yamlPath = base_path('app-configs/setup/collections/marker-example.yml');
        if (! is_dir(dirname($this->yamlPath))) {
            mkdir(dirname($this->yamlPath), 0755, true);
        }
        file_put_contents($this->yamlPath, <<<'YAML'
title: Marker
titleList: Markers
key: MARKER-EXAMPLE
buttonList: New Marker
fields:
  - name: detailData.name
    label: Name
    type: translatableText
    colspan: 6
  - name: detailData.code
    label: Code
    type: text
    colspan: 6
YAML);

        $collection = SetupCollection::create([
            'tenant_id' => $this->tenant->id,
            'collection_key' => 'MARKER-EXAMPLE',
            'name' => 'Marker',
        ]);

        SetupCollectionEntry::create([
            'tenant_id' => $this->tenant->id,
            'setup_collection_id' => $collection->id,
            'data' => [
                'name' => ['de' => 'Deutschland', 'en' => 'Germany'],
                'code' => 'DE',
            ],
            'sort' => 0,
        ]);
    });

    afterEach(function (): void {
        if (isset($this->yamlPath) && file_exists($this->yamlPath)) {
            unlink($this->yamlPath);
        }
    });

    it('flags translatable columns when deriving the table config', function (): void {
        $table = SetupCollectionHelper::getCollectionTable('marker-example');

        $byField = collect($table)->keyBy('field');

        expect($byField['name']['translatable'])->toBeTrue()
            ->and($byField['code']['translatable'])->toBeFalse();
    });

    it('renders the blue frame only on the translatable column', function (): void {
        $html = Livewire::test('noerd::setup-collections-list', ['collectionKey' => 'marker-example'])
            ->assertSuccessful()
            ->html();

        // The translated value of the active language plus the plain value are both shown …
        expect($html)->toContain('Germany')->toContain('DE');
        // … but only the translatable cell carries the tinted background, and it is a
        // background — never a frame, which would fight the table's own grid lines.
        expect(mb_substr_count($html, 'bg-sky-50 group-hover:bg-transparent'))->toBe(1)
            ->and($html)->not->toContain('border-sky-300!');
    });

    it('leaves a list without translatable columns unmarked', function (): void {
        file_put_contents($this->yamlPath, <<<'YAML'
title: Marker
titleList: Markers
key: MARKER-EXAMPLE
buttonList: New Marker
fields:
  - name: detailData.code
    label: Code
    type: text
    colspan: 6
YAML);
        app()->forgetInstance(SetupCollectionDefinitionRepositoryContract::class);
        app()->forgetInstance(SetupCollectionHelper::class);

        $html = Livewire::test('noerd::setup-collections-list', ['collectionKey' => 'marker-example'])
            ->assertSuccessful()
            ->html();

        expect($html)->not->toContain('bg-sky-50');
    });
});
