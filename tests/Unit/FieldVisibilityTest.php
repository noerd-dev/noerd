<?php

declare(strict_types=1);

use Noerd\Support\FieldVisibility;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | The one x-show expression of showIf / showIfNot — shared by fields, tabs and
 | detail actions. The server-side mirror (visibleOn) is proven in
 | FieldTypeValidationTest.
 */

it('writes the x-show expression of the string and object forms, both keys combined with AND', function (): void {
    expect(FieldVisibility::showExpression(['showIf' => 'hasAccount']))->toBe('$wire.hasAccount')
        ->and(FieldVisibility::showExpression(['showIfNot' => 'detailData.is_business']))->toBe('!$wire.detailData.is_business')
        ->and(FieldVisibility::showExpression(['showIf' => ['field' => 'detailData.status', 'value' => 'open']]))
        ->toBe("(String(\$wire.detailData.status ?? '') === 'open')")
        ->and(FieldVisibility::showExpression(['showIf' => 'hasEmail', 'showIfNot' => ['field' => 'detailData.status', 'value' => 1]]))
        ->toBe("\$wire.hasEmail && !(String(\$wire.detailData.status ?? '') === '1')")
        ->and(FieldVisibility::showExpression(['label' => 'Plain']))->toBeNull()
        ->and(FieldVisibility::showExpression(['showIf' => '']))->toBeNull();
});

it('keeps a YAML path or value from breaking out of the attribute', function (): void {
    $expression = FieldVisibility::showExpression([
        'showIf' => ['field' => 'detailData.x" onclick="evil()', 'value' => '" onclick="evil()<'],
    ]);

    expect($expression)->toStartWith("(String(\$wire.detailData.xonclickevil ?? '') === ")
        ->not->toContain('"')
        ->not->toContain('<');
});
