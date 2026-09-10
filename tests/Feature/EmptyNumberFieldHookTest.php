<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

/**
 * A detail with a CUSTOM store() that records what it would persist — proving
 * the hook normalises the payload before any store() body runs, with a
 * synthetic layout instead of a shipped YAML.
 */
class ZzEmptyNumberDetail extends Component
{
    use NoerdDetail;

    public $detailModel = NoerdUser::class;

    public ?string $detailPrimary = 'zzId';

    public array $stored = [];

    public function mount(): void
    {
        $this->pageLayout = [
            'fields' => [
                ['name' => 'detailData.rate', 'label' => 'Rate', 'type' => 'number'],
                ['name' => 'detailData.code', 'label' => 'Code', 'type' => 'text'],
                ['type' => 'block', 'title' => 'Nested', 'fields' => [
                    ['name' => 'detailData.nested_rate', 'label' => 'Nested', 'type' => 'number'],
                ]],
            ],
        ];
    }

    public function store(): void
    {
        $this->stored = $this->detailData;
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());
    Livewire::component('zz-empty-number-detail', ZzEmptyNumberDetail::class);
});

it('stores an emptied number field as null, also inside a nested block', function (): void {
    Livewire::test('zz-empty-number-detail')
        ->set('detailData.rate', '')
        ->set('detailData.nested_rate', '')
        ->set('detailData.code', '')
        ->call('store')
        ->assertSet('stored.rate', null)
        ->assertSet('stored.nested_rate', null)
        // Only number fields are touched: an empty text is still an empty text.
        ->assertSet('stored.code', '');
});

it('keeps an entered number and a typed zero', function (): void {
    Livewire::test('zz-empty-number-detail')
        ->set('detailData.rate', '5.5')
        ->set('detailData.nested_rate', '0')
        ->call('store')
        ->assertSet('stored.rate', '5.5')
        ->assertSet('stored.nested_rate', '0');
});
