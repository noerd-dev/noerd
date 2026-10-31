<?php

declare(strict_types=1);

use Carbon\Carbon;
use Noerd\Support\LayoutDefaults;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | A date default is relative to the day the form opens: the YAML says `today`
 | or `+14 days`, the resolver writes the concrete date. Anything that is not
 | such a token is the literal default.
 */

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-02 14:30:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('resolves the relative date tokens against today', function (string $token, string $expected): void {
    expect(LayoutDefaults::resolveToken($token))->toBe($expected);
})->with([
    'today' => ['today', '2026-09-02'],
    'now' => ['now', '2026-09-02 14:30:00'],
    'plus days' => ['+14 days', '2026-09-16'],
    'minus day' => ['-1 day', '2026-09-01'],
    'plus weeks' => ['+2 weeks', '2026-09-16'],
    'minus month' => ['-1 month', '2026-08-02'],
    'plus year' => ['+1 year', '2027-09-02'],
    'spaces and case' => [' + 3 Days ', '2026-09-05'],
]);

it('returns anything else as written', function (mixed $default): void {
    expect(LayoutDefaults::resolveToken($default))->toBe($default);
})->with([
    'literal text' => ['created'],
    'a date' => ['2026-01-01'],
    'a number' => [14],
    'a bool' => [false],
    'null' => [null],
    'tomorrow is no token' => ['tomorrow'],
]);

it('applies the tokens through the layout defaults', function (): void {
    $defaults = LayoutDefaults::resolve([
        ['name' => 'detailData.invoice_date', 'type' => 'date', 'default' => 'today'],
        ['name' => 'detailData.due_date', 'type' => 'date', 'default' => '+14 days'],
        ['name' => 'detailData.status', 'type' => 'text', 'default' => 'open'],
    ]);

    expect($defaults)->toBe([
        'invoice_date' => '2026-09-02',
        'due_date' => '2026-09-16',
        'status' => 'open',
    ]);
});
