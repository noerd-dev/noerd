<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

/*
 | Layout-declared defaults: a detail form must never display a value it does
 | not hold. A select whose bound property is null shows its first option by
 | pure HTML fallback and loses it on save, so the layout owns the initial
 | value — an explicit `default:` on any field, or the first option of a select
 | whose options are written in the YAML. The layout comes from a runtime
 | fixture YAML, never from a shipped config.
 */

class ZzLayoutDefaultsComponent extends Component
{
    use NoerdDetail;

    public $detailModel = Tenant::class;

    public ?string $detailPrimary = 'tenantId';

    public function zzDynamicOptions(): array
    {
        return ['first' => 'First', 'second' => 'Second'];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-layout-defaults-detail';
    }
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::component('zz-layout-defaults-detail', ZzLayoutDefaultsComponent::class);

    File::put(base_path('app-configs/setup/details/zz-layout-defaults-detail.yml'), implode("\n", [
        'title: Zz Layout Defaults',
        'fields:',
        '  - name: detailData.name',
        '    label: Name',
        '    type: select',
        '    options:',
        '      - value: alpha',
        '        label: Alpha',
        '      - value: beta',
        '        label: Beta',
        '  - name: detailData.zz_explicit',
        '    label: Explicit',
        '    type: select',
        '    default: beta',
        '    options:',
        '      - value: alpha',
        '        label: Alpha',
        '      - value: beta',
        '        label: Beta',
        '  - name: detailData.zz_text',
        '    label: Text',
        '    type: text',
        '    default: seeded',
        '  - name: detailData.zz_placeholder',
        '    label: Placeholder',
        '    type: select',
        '    placeholder: Please choose',
        '    options:',
        '      - value: alpha',
        '        label: Alpha',
        '  - name: detailData.zz_dynamic',
        '    label: Dynamic',
        '    type: select',
        '    optionsMethod: zzDynamicOptions',
        '  - name: detailData.uuid',
        '    label: Uuid',
        '    type: text',
        '  - name: detailData.zz_plain',
        '    label: Plain',
        '    type: text',
        '  - type: block',
        '    fields:',
        '      - name: detailData.zz_nested',
        '        label: Nested',
        '        type: select',
        '        options:',
        '          - value: nested-first',
        '            label: Nested First',
        '          - value: nested-second',
        '            label: Nested Second',
    ]));
});

afterEach(function (): void {
    File::delete(base_path('app-configs/setup/details/zz-layout-defaults-detail.yml'));
});

it('seeds a new record with the first option of a select whose options are in the YAML', function (): void {
    Livewire::test('zz-layout-defaults-detail')
        ->assertSet('detailData.name', 'alpha');
});

it('persists the seeded default on the first save', function (): void {
    Livewire::test('zz-layout-defaults-detail')
        ->set('detailData.uuid', 'zz-layout-defaults')
        ->call('store');

    expect(Tenant::query()->where('name', 'alpha')->exists())->toBeTrue();
});

it('prefers an explicit default over the first option and seeds non-select fields too', function (): void {
    Livewire::test('zz-layout-defaults-detail')
        ->assertSet('detailData.zz_explicit', 'beta')
        ->assertSet('detailData.zz_text', 'seeded');
});

it('leaves a select with a placeholder empty, because empty is a valid answer there', function (): void {
    Livewire::test('zz-layout-defaults-detail')
        ->assertSet('detailData.zz_placeholder', null);
});

it('never seeds a select whose options are built from data at runtime', function (): void {
    Livewire::test('zz-layout-defaults-detail')
        ->assertSet('detailData.zz_dynamic', null);
});

it('seeds a select nested in a block field', function (): void {
    Livewire::test('zz-layout-defaults-detail')
        ->assertSet('detailData.zz_nested', 'nested-first');
});

it('leaves fields without a declared default untouched', function (): void {
    Livewire::test('zz-layout-defaults-detail')
        ->assertSet('detailData.zz_plain', null);
});

it('seeds an existing record too, not only a new one', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'beta']);

    // zz_explicit is not a column of the record, so it arrives null from the
    // database — exactly the state an existing row with a NULL picklist has.
    Livewire::test('zz-layout-defaults-detail', ['modelId' => $tenant->id])
        ->assertSet('detailData.zz_explicit', 'beta');
});

it('never overwrites a value the record already holds', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'beta']);

    Livewire::test('zz-layout-defaults-detail', ['modelId' => $tenant->id])
        ->assertSet('detailData.name', 'beta');
});

it('treats an empty string as an answer and does not re-seed it', function (): void {
    Livewire::test('zz-layout-defaults-detail')
        ->set('detailData.name', '')
        ->assertSet('detailData.name', '');
});
