<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Noerd\Contracts\CustomAttributeDefinitionsContract;
use Noerd\Services\NullCustomAttributeDefinitions;
use Noerd\Support\CustomAttributeField;
use Noerd\Tests\TestCase;

uses(TestCase::class);

function zzCustomFieldPasses(CustomAttributeField $field, array $data, bool $update = false): bool
{
    return Validator::make($data, ['value' => $field->rules($update)])->passes();
}

it('validates a value by the field type', function (string $type, mixed $valid, mixed $invalid): void {
    $field = new CustomAttributeField('value', 'Value', $type);

    expect(zzCustomFieldPasses($field, ['value' => $valid]))->toBeTrue()
        ->and(zzCustomFieldPasses($field, ['value' => $invalid]))->toBeFalse()
        ->and(zzCustomFieldPasses($field, ['value' => null]))->toBeTrue();
})->with([
    'text' => ['text', 'Döner', ['no', 'list']],
    'number' => ['number', 52.520008, 'north'],
    'currency' => ['currency', '12.50', 'much'],
    'date' => ['date', '2026-11-24', '24.11.2026'],
    'checkbox' => ['checkbox', true, 'maybe'],
]);

it('accepts only the option values of a select', function (): void {
    $field = new CustomAttributeField('value', 'Value', 'select', options: [
        ['value' => 'delivery', 'label' => 'Delivery'],
        ['value' => 'pickup', 'label' => 'Pickup'],
    ]);

    expect(zzCustomFieldPasses($field, ['value' => 'pickup']))->toBeTrue()
        ->and(zzCustomFieldPasses($field, ['value' => 'both']))->toBeFalse()
        ->and($field->optionValues())->toBe(['delivery', 'pickup']);
});

it('requires a required field on create only', function (): void {
    $field = new CustomAttributeField('value', 'Value', 'text', required: true);

    expect(zzCustomFieldPasses($field, []))->toBeFalse()
        ->and(zzCustomFieldPasses($field, [], update: true))->toBeTrue();
});

it('describes the field with its options', function (): void {
    $field = new CustomAttributeField('fulfillment', 'Fulfillment', 'select', options: [['value' => 'pickup', 'label' => 'Pickup']]);

    expect($field->describe())->toBe([
        'key' => 'fulfillment',
        'label' => 'Fulfillment',
        'type' => 'select',
        'required' => false,
        'options' => [['value' => 'pickup', 'label' => 'Pickup']],
    ])->and((new CustomAttributeField('cuisine', 'Cuisine', 'text'))->describe())->not->toHaveKey('options');
});

it('binds an empty default for the definitions', function (): void {
    expect(app(CustomAttributeDefinitionsContract::class))->toBeInstanceOf(NullCustomAttributeDefinitions::class)
        ->and(app(CustomAttributeDefinitionsContract::class)->fieldsFor(stdClass::class, 1))->toBe([]);
});
