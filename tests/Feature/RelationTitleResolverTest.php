<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Customer\Models\Customer;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Product\Models\Product;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Services\RelationTitleResolver;
use Noerd\Support\RelationFieldDefinition;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(NoerdUser::factory()->withExampleTenant()->create());
    $this->resolver = app(RelationTitleResolver::class);
});

it('resolves the title through a registered relation type', function (): void {
    $customer = Customer::factory()->create([
        'tenant_id' => TenantHelper::getSelectedTenantId(),
        'name' => 'Erika Musterfrau',
    ]);

    expect($this->resolver->title('customer_id', $customer->id))->toBe('Erika Musterfrau');
});

it('resolves the title by naming convention when no relation type is registered', function (): void {
    $product = Product::factory()->create([
        'tenant_id' => TenantHelper::getSelectedTenantId(),
        'name' => 'Pizza Salami',
    ]);

    expect($this->resolver->title('product_id', $product->id))->toBe('Pizza Salami');
});

it('falls back to the id when the name is empty or the row is missing', function (): void {
    $product = Product::factory()->create([
        'tenant_id' => TenantHelper::getSelectedTenantId(),
        'name' => '',
    ]);

    expect($this->resolver->title('product_id', $product->id))->toBe((string) $product->id)
        ->and($this->resolver->title('product_id', 999999))->toBe('999999');
});

it('falls back to the id when no matching table exists', function (): void {
    expect($this->resolver->title('gizmo_id', 42))->toBe('42');
});

it('returns null for empty values and non-foreign-key columns', function (): void {
    expect($this->resolver->title('product_id', null))->toBeNull()
        ->and($this->resolver->title('product_id', ''))->toBeNull()
        ->and($this->resolver->title('name', 5))->toBeNull();
});

it('prefers a registered relation type over the table convention', function (): void {
    $customer = Customer::factory()->create([
        'tenant_id' => TenantHelper::getSelectedTenantId(),
        'name' => 'Erika Musterfrau',
    ]);

    app(RelationFieldRegistry::class)->register('widgetRelation', RelationFieldDefinition::model(
        'widgets-list',
        null,
        Customer::class,
        titleResolver: fn (mixed $model): string => 'WIDGET:'.$model->name,
    ));

    expect(app(RelationTitleResolver::class)->title('widget_id', $customer->id))->toBe('WIDGET:Erika Musterfrau');
});
