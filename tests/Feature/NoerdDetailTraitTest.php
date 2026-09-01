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
 | Core NoerdDetail mechanics not covered by the store roundtrip suite
 | (NoerdDetailStoreRoundtripTest): layout hydration on mount, the recursion of
 | validateFromLayout() into `type: block` fields, the setFieldValue event
 | (relation title adoption and dotted writes — its detailData allowlist is
 | proven in SecurityHighFixesTest) and clearRelation(). The layout comes from a
 | runtime fixture YAML, never from a shipped config.
 */

class ZzDetailTraitComponent extends Component
{
    use NoerdDetail;

    public $detailModel = Tenant::class;

    public ?string $detailPrimary = 'tenantId';

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-detail-trait-detail';
    }
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::component('zz-detail-trait-detail', ZzDetailTraitComponent::class);

    File::put(base_path('app-configs/setup/details/zz-detail-trait-detail.yml'), implode("\n", [
        'title: Zz Detail Trait',
        'fields:',
        '  - name: detailData.name',
        '    label: Name',
        '    type: text',
        '    required: true',
        '  - type: block',
        '    fields:',
        '      - name: detailData.zz_nested',
        '        label: Nested',
        '        type: text',
        '        required: true',
    ]));
});

afterEach(function (): void {
    File::delete(base_path('app-configs/setup/details/zz-detail-trait-detail.yml'));
});

it('hydrates detailData from the mounted record and the layout from the YAML', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Hydrated Tenant']);

    $component = Livewire::test('zz-detail-trait-detail', ['modelId' => $tenant->id]);

    expect($component->get('detailData.name'))->toBe('Hydrated Tenant')
        ->and($component->get('pageLayout.title'))->toBe('Zz Detail Trait');
});

it('derives required rules from the layout, recursing into block fields', function (): void {
    $component = Livewire::test('zz-detail-trait-detail')
        ->set('detailData', [])
        ->call('store');

    $required = requiredLayoutFields($component);

    expect($required)->toContain('detailData.zz_nested');

    $component->assertHasErrors($required);
});

it('adopts a setFieldValue event into detailData with its relation title', function (): void {
    $component = Livewire::test('zz-detail-trait-detail')
        ->dispatch('setFieldValue', field: 'detailData.customer_id', value: 5, relationTitle: 'Acme');

    expect($component->get('detailData.customer_id'))->toBe(5)
        ->and($component->get('relationTitles.customer_id'))->toBe('Acme');

    // Dotted fields write into the nested payload.
    $component->dispatch('setFieldValue', field: 'detailData.address.city', value: 'Berlin');

    expect($component->get('detailData.address.city'))->toBe('Berlin');
});

it('clears a relation value and its title for plain and dotted fields', function (): void {
    $component = Livewire::test('zz-detail-trait-detail')
        ->dispatch('setFieldValue', field: 'detailData.customer_id', value: 5, relationTitle: 'Acme')
        ->dispatch('setFieldValue', field: 'detailData.invoice.contact_id', value: 7, relationTitle: 'Jane')
        ->call('clearRelation', 'detailData.customer_id')
        ->call('clearRelation', 'detailData.invoice.contact_id');

    expect($component->get('detailData.customer_id'))->toBeNull()
        ->and($component->get('relationTitles.customer_id'))->toBe('')
        ->and($component->get('detailData.invoice.contact_id'))->toBeNull()
        ->and($component->get('relationTitles.contact_id'))->toBe('');
});
