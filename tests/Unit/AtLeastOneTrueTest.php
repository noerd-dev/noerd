<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Noerd\Rules\AtLeastOneTrue;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/**
 * Validate a single value against the rule and return the failure message
 * (null when the value passes).
 */
function zzAtLeastOneTrueFailure(mixed $value): ?string
{
    $validator = Validator::make(['tenantAccess' => $value], ['tenantAccess' => [new AtLeastOneTrue()]]);

    return $validator->fails() ? $validator->errors()->first('tenantAccess') : null;
}

it('passes for an array holding at least one true', function (mixed $value): void {
    expect(zzAtLeastOneTrueFailure($value))->toBeNull();
})->with([
    'single true' => [[true]],
    'mixed booleans' => [[false, true, false]],
    'string keys' => [[7 => false, 9 => true]],
]);

it('fails for an array without a strict true', function (mixed $value): void {
    expect(zzAtLeastOneTrueFailure($value))->not->toBeNull();
})->with([
    'empty array' => [[]],
    'all false' => [[false, false]],
    'truthy but not true' => [[1, '1', 'yes']],
    'nulls' => [[null]],
]);

it('fails for a value that is not an array', function (mixed $value): void {
    expect(zzAtLeastOneTrueFailure($value))->not->toBeNull();
})->with([
    'null' => [null],
    'bare true' => [true],
    'string' => ['true'],
]);

it('reports the translated message with the humanized attribute name', function (): void {
    expect(zzAtLeastOneTrueFailure([false]))
        ->toBe(__('The :attribute must have at least one true value.', ['attribute' => 'tenant access']));
});
