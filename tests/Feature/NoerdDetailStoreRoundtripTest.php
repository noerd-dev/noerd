<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Noerd\Traits\NoerdDetail;

uses(TestCase::class, RefreshDatabase::class);

/*
 | The generic NoerdDetail store roundtrip: persisting through the declared
 | $detailModel, the finishStore() tail (success indicator, adopted id, the
 | detailStored-{name} event a hosting page listens for) and the identity-
 | column stripping of writableDetailData().
 */

class ZzStoreRoundtripComponent extends Component
{
    use NoerdDetail;

    public $detailModel = Tenant::class;

    public function render(): string
    {
        return '<div></div>';
    }

    protected function componentName(): string
    {
        return 'zz-store-roundtrip-page';
    }
}

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->adminUser()->withSelectedApp('setup')->create());

    Livewire::component('zz-store-roundtrip-page', ZzStoreRoundtripComponent::class);
});

it('creates the record, adopts its id and reports it to a hosting page', function (): void {
    $component = Livewire::test('zz-store-roundtrip-page')
        ->set('detailData', validDetailPayload(Tenant::class))
        ->set('detailData.name', 'Roundtrip Tenant')
        ->call('store')
        ->assertSet('showSuccessIndicator', true);

    $tenant = Tenant::query()->where('name', 'Roundtrip Tenant')->first();

    expect($tenant)->not->toBeNull();

    $component->assertSet('modelId', $tenant->id)
        ->assertDispatched('detailStored-zz-store-roundtrip-page', modelId: $tenant->id);
});

it('updates the mounted record instead of creating a new one', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Before']);

    $countBefore = Tenant::query()->count();

    Livewire::test('zz-store-roundtrip-page', ['modelId' => $tenant->id])
        ->set('detailData.name', 'After')
        ->call('store');

    expect(Tenant::query()->count())->toBe($countBefore)
        ->and($tenant->refresh()->name)->toBe('After');
});

it('never mass-assigns identity or timestamp columns from the client payload', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Original']);
    $other = Tenant::factory()->create(['name' => 'Other']);

    Livewire::test('zz-store-roundtrip-page', ['modelId' => $tenant->id])
        ->set('detailData.id', $other->id)
        ->set('detailData.name', 'Injected')
        ->call('store');

    // The id key was stripped: the mounted record was updated, the other left alone.
    expect($tenant->refresh()->name)->toBe('Injected')
        ->and($other->refresh()->name)->toBe('Other');
});

it('reduces a detail payload to declared layout keys and always drops identity/tenant columns', function (): void {
    $component = new class {
        use NoerdDetail;

        /** @param array<int, array<string, mixed>> $fields */
        public function collectKeys(array $fields): array
        {
            return $this->writableKeysFromFields($fields);
        }

        /**
         * @param  array<string, mixed>  $data
         * @param  array<int, string>  $allowed
         */
        public function reduce(array $data, array $allowed): array
        {
            return $this->reduceToWritableKeys($data, $allowed);
        }
    };

    $fields = [
        ['name' => 'detailData.name', 'type' => 'text'],
        ['type' => 'block', 'fields' => [
            ['name' => 'detailData.custom_attributes.sap', 'type' => 'text'],
            ['name' => 'detailData.price', 'type' => 'number'],
        ]],
        ['name' => 'relationTitles.customer_id', 'type' => 'text'], // not detailData → ignored
    ];

    expect($component->collectKeys($fields))
        ->toEqualCanonicalizing(['name', 'custom_attributes', 'price']);

    $reduced = $component->reduce(
        [
            'id' => 5,
            'tenant_id' => 99,
            'name' => 'ok',
            'price' => 10,
            'is_admin' => true,      // injected, not in layout
            'custom_attributes' => ['sap' => 'A1'],
            'created_at' => 'now',
            'updated_at' => 'now',
        ],
        ['name', 'custom_attributes', 'price'],
    );

    expect($reduced)->toBe([
        'name' => 'ok',
        'price' => 10,
        'custom_attributes' => ['sap' => 'A1'],
    ]);
});
