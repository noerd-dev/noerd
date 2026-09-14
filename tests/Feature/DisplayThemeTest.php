<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Helpers\ThemeHelper;
use Noerd\Models\NoerdSettings;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Services\ThemeRegistry;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The display theme renders YAML fields as read-only text instead of controls.
 | Everything runs against synthetic layouts and runtime-written fixture YAMLs:
 | which theme a shipped config declares is per-installation configuration.
 */

/**
 * Renders a synthetic layout through the detail block with the given values.
 */
function zzRenderDisplayLayout(array $fields, array $detailData, string $theme = 'display'): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(new class extends Component {
        public array $detailData = [];

        public array $zzFields = [];

        public string $zzTheme = 'display';

        public function mount(array $detailData, array $zzFields, string $zzTheme): void
        {
            $this->detailData = $detailData;
            $this->zzFields = $zzFields;
            $this->zzTheme = $zzTheme;
        }

        public function render(): string
        {
            return <<<'BLADE'
                <div>
                    @include('noerd::components.detail.block', [
                        'theme' => $zzTheme,
                        'fields' => $zzFields,
                    ])
                </div>
                BLADE;
        }
    }, ['detailData' => $detailData, 'zzFields' => $fields, 'zzTheme' => $theme]);
}

beforeEach(function (): void {
    $this->admin = NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create();
    $this->actingAs($this->admin);
});

describe('registration', function (): void {
    it('ships display as a hidden text-only theme', function (): void {
        $definition = app(ThemeRegistry::class)->get('display');

        expect($definition->name)->toBe('display')
            ->and($definition->hidden)->toBeTrue()
            ->and($definition->textOnly)->toBeTrue()
            ->and(app(ThemeRegistry::class)->get('default')->textOnly)->toBeFalse()
            ->and(app(ThemeRegistry::class)->get('compact')->textOnly)->toBeFalse();
    });
});

describe('rendering', function (): void {
    it('renders the values as text instead of controls, formatted by the field type', function (): void {
        $component = zzRenderDisplayLayout([
            ['name' => 'detailData.phone', 'label' => 'Phone', 'type' => 'phone', 'colspan' => 12],
            ['name' => 'detailData.email', 'label' => 'Email', 'type' => 'email', 'colspan' => 12],
            ['name' => 'detailData.note', 'label' => 'Note', 'type' => 'textarea', 'colspan' => 12],
            ['name' => 'detailData.kind', 'label' => 'Kind', 'type' => 'select', 'colspan' => 12, 'options' => [
                ['value' => 'b2b', 'label' => 'Business'],
                ['value' => 'b2c', 'label' => 'Private'],
            ]],
            ['name' => 'detailData.active', 'label' => 'Active', 'type' => 'checkbox', 'colspan' => 12],
            ['name' => 'detailData.amount', 'label' => 'Amount', 'type' => 'currency', 'colspan' => 12],
            ['name' => 'detailData.since', 'label' => 'Since', 'type' => 'date', 'colspan' => 12],
            ['name' => 'detailData.plain', 'label' => 'Plain', 'colspan' => 12],
        ], [
            'phone' => '09281 12345',
            'email' => 'erika@example.com',
            'note' => "Ring twice\nthen wait",
            'kind' => 'b2b',
            'active' => true,
            'amount' => 1234.5,
            'since' => '2026-03-04',
            'plain' => 'just text',
        ])
            ->assertSuccessful()
            ->assertSeeHtml('data-theme="display"')
            ->assertSeeHtml('href="tel:09281 12345"')
            ->assertSeeHtml('href="mailto:erika@example.com"')
            ->assertSee('Ring twice')
            ->assertSee('Business')
            ->assertDontSee('Private')
            ->assertSee(__('Yes'))
            ->assertSee(zzNormalizeSpaces(\Noerd\Helpers\CurrencyHelper::format(1234.5)))
            ->assertSee(\Noerd\Helpers\FormatHelper::date('2026-03-04'))
            ->assertSee('just text')
            ->assertDontSeeHtml('<input')
            ->assertDontSeeHtml('<textarea')
            ->assertDontSeeHtml('<select');

        // The label column of the display row.
        assertElementHasClasses($component->html(), ['w-28', 'shrink-0', 'truncate']);
    });

    it('renders an unticked checkbox as No and an unknown select value verbatim', function (): void {
        zzRenderDisplayLayout([
            ['name' => 'detailData.active', 'label' => 'Active', 'type' => 'checkbox', 'colspan' => 12],
            ['name' => 'detailData.kind', 'label' => 'Kind', 'type' => 'select', 'colspan' => 12, 'options' => [
                ['value' => 'b2b', 'label' => 'Business'],
            ]],
        ], ['active' => false, 'kind' => 'legacy'])
            ->assertSee(__('No'))
            ->assertSee('legacy')
            ->assertDontSee('Business');
    });

    it('drops a blank value only when the field asks for it', function (): void {
        zzRenderDisplayLayout([
            ['name' => 'detailData.phone', 'label' => 'Phone', 'type' => 'phone', 'colspan' => 12, 'hideIfEmpty' => true],
            ['name' => 'detailData.fax', 'label' => 'Fax', 'type' => 'text', 'colspan' => 12],
            ['name' => 'detailData.note', 'label' => 'Note', 'type' => 'textarea', 'colspan' => 12, 'hideIfEmpty' => true],
        ], ['phone' => null, 'fax' => '', 'note' => 'VIP'])
            ->assertDontSee('Phone')
            ->assertSee('Fax')
            ->assertSee('Note')
            ->assertSee('VIP');
    });

    it('ignores hideIfEmpty in a theme that renders controls', function (): void {
        zzRenderDisplayLayout([
            ['name' => 'detailData.phone', 'label' => 'Phone', 'type' => 'text', 'colspan' => 12, 'hideIfEmpty' => true],
        ], ['phone' => null], theme: 'default')
            ->assertSee('Phone')
            ->assertSeeHtml('<input');
    });

    it('mixes a display row with editable inputs in one layout', function (): void {
        $component = zzRenderDisplayLayout([
            ['type' => 'block', 'theme' => 'display', 'colspan' => 12, 'fields' => [
                ['name' => 'detailData.customer', 'label' => 'Customer', 'type' => 'text', 'colspan' => 6],
                ['name' => 'detailData.total', 'label' => 'Total', 'type' => 'currency', 'colspan' => 6],
            ]],
            ['name' => 'detailData.created', 'label' => 'Created', 'type' => 'text', 'colspan' => 6, 'theme' => 'display'],
            ['name' => 'detailData.note', 'label' => 'Note', 'type' => 'textarea', 'colspan' => 12],
        ], ['customer' => 'Erika', 'total' => 10, 'created' => 'yesterday', 'note' => 'edit me'], theme: 'default');

        $component->assertSuccessful()
            ->assertSee('Erika')
            ->assertSee('yesterday')
            ->assertSeeHtml('data-theme="display"')
            ->assertSeeHtml('<textarea')
            ->assertDontSeeHtml('<input');

        // Exactly one control: the textarea. The display rows carry no controls.
        expect(mb_substr_count($component->html(), '<textarea'))->toBe(1)
            ->and(mb_substr_count($component->html(), 'data-theme="display"'))->toBe(1);
    });

    it('renders a relation field as its resolved title', function (): void {
        app(RelationFieldRegistry::class)->register('zzDisplayRelation', RelationFieldDefinition::model(
            listComponent: 'zz-displays-list',
            detailComponent: 'zz-display-detail',
            modelClass: null,
            titleResolver: 'name',
        ));

        Livewire::test('noerd-relation-field', [
            'relationType' => 'zzDisplayRelation',
            'fieldName' => 'detailData.zz_display_id',
            'label' => 'Linked',
            'theme' => 'display',
        ])
            ->assertSuccessful()
            ->assertSee('Linked')
            ->assertDontSeeHtml('<input')
            ->assertDontSeeHtml('<button');
    });
});

describe('enforced system theme', function (): void {
    beforeEach(function (): void {
        $tenant = Tenant::factory()->create();
        TenantHelper::setSelectedTenantId($tenant->id);
        TenantHelper::setSelectedApp('ZZDISPLAYAPP');
        ThemeHelper::clearCache();

        File::ensureDirectoryExists(base_path('app-configs/zzdisplayapp/details'));
        File::ensureDirectoryExists(base_path('app-configs/zzdisplayapp/pages'));
        File::put(base_path('app-configs/zzdisplayapp/details/zz-mixed-detail.yml'), implode("\n", [
            'title: Mixed',
            'theme: numbered',
            'fields:',
            '  - type: block',
            '    theme: display',
            '    fields:',
            '      - name: detailData.customer',
            '        label: Customer',
            '        type: text',
            '  - name: detailData.created',
            '    label: Created',
            '    type: text',
            '    theme: display',
            '  - name: detailData.note',
            '    label: Note',
            '    type: text',
            '    theme: compact',
        ]));
        File::put(base_path('app-configs/zzdisplayapp/pages/zz-facts-page.yml'), implode("\n", [
            'theme: display',
            'fields:',
            '  - name: detailData.customer',
            '    label: Customer',
            '    type: text',
        ]));

        NoerdSettings::updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['detail_theme' => 'compact', 'detail_theme_enforced' => true],
        );
        ThemeHelper::clearCache();
    });

    afterEach(function (): void {
        File::deleteDirectory(base_path('app-configs/zzdisplayapp'));
        ThemeHelper::clearCache();
    });

    it('keeps display overrides on fields and nested blocks while stripping the others', function (): void {
        $layout = StaticConfigHelper::getComponentFields('zz-mixed-detail');
        [$block, $created, $note] = $layout['fields'];

        expect($layout['theme'])->toBe('compact')
            ->and($block['theme'])->toBe('display')
            ->and($created['theme'])->toBe('display')
            ->and($note)->not->toHaveKey('theme');
    });

    it('keeps a top-level display theme of a page layout', function (): void {
        expect(StaticConfigHelper::getPageFields('zz-facts-page')['theme'])->toBe('display');
    });
});
