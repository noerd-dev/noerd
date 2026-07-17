<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Noerd\Models\Tenant;
use Noerd\Services\ColumnFilterParser;

uses(Tests\TestCase::class);

function filterQuery(): Builder
{
    return Tenant::query();
}

/**
 * The where clauses added by the parser (ignores the model's global scopes,
 * which are only merged into the query when it executes).
 *
 * @return array<int, array<string, mixed>>
 */
function addedWheres(Builder $query): array
{
    return $query->getQuery()->wheres;
}

it('parses operator prefixes', function (string $raw, ?string $op, string $value): void {
    expect(ColumnFilterParser::parse($raw))->toBe(['op' => $op, 'value' => $value]);
})->with([
    'greater than' => ['>0', '>', '0'],
    'greater or equal' => ['>=10', '>=', '10'],
    'less than' => ['<5', '<', '5'],
    'less or equal' => ['<= 10', '<=', '10'],
    'equals' => ['=rot', '=', 'rot'],
    'not equals' => ['!=rot', '!=', 'rot'],
    'angle brackets normalised' => ['<>rot', '!=', 'rot'],
    'surrounding whitespace' => ['  >=  7 ', '>=', '7'],
    'no operator' => ['rot', null, 'rot'],
    'operator only' => ['>=', '>=', ''],
]);

it('applies a plain text value as a like filter with escaped wildcards', function (): void {
    $query = filterQuery();
    ColumnFilterParser::apply($query, 'name', 'text', '50%_a\\b');

    $wheres = addedWheres($query);
    expect($wheres)->toHaveCount(1)
        ->and($wheres[0]['operator'])->toBe('like')
        ->and($wheres[0]['value'])->toBe('%50\%\_a\\\\b%');
});

it('applies text operators as direct comparisons', function (string $raw, string $operator, string $value): void {
    $query = filterQuery();
    ColumnFilterParser::apply($query, 'name', 'text', $raw);

    $wheres = addedWheres($query);
    expect($wheres)->toHaveCount(1)
        ->and($wheres[0]['operator'])->toBe($operator)
        ->and($wheres[0]['value'])->toBe($value);
})->with([
    'exact match' => ['=rot', '=', 'rot'],
    'not equal' => ['!=rot', '!=', 'rot'],
    'string greater than' => ['>m', '>', 'm'],
]);

it('applies number filters with the parsed operator', function (string $raw, string $operator, float $value): void {
    $query = filterQuery();
    ColumnFilterParser::apply($query, 'amount', 'number', $raw);

    $wheres = addedWheres($query);
    expect($wheres)->toHaveCount(1)
        ->and($wheres[0]['operator'])->toBe($operator)
        ->and($wheres[0]['value'])->toBe($value);
})->with([
    'greater than zero' => ['>0', '>', 0.0],
    'less or equal ten' => ['<=10', '<=', 10.0],
    'plain number is exact' => ['5', '=', 5.0],
    'comma decimal' => ['>=2,5', '>=', 2.5],
]);

it('ignores non-numeric input on number columns', function (): void {
    $query = filterQuery();
    ColumnFilterParser::apply($query, 'amount', 'number', '>abc');

    expect(addedWheres($query))->toBeEmpty();
});

it('applies bool filters only for 1 or 0', function (): void {
    $query = filterQuery();
    ColumnFilterParser::apply($query, 'is_active', 'bool', '1');
    ColumnFilterParser::apply($query, 'is_active', 'bool', '0');
    ColumnFilterParser::apply($query, 'is_active', 'bool', 'maybe');

    $wheres = addedWheres($query);
    expect($wheres)->toHaveCount(2)
        ->and($wheres[0]['value'])->toBeTrue()
        ->and($wheres[1]['value'])->toBeFalse();
});

it('applies date filters via whereDate', function (string $raw, string $operator, string $date): void {
    $query = filterQuery();
    ColumnFilterParser::apply($query, 'created_at', 'date', $raw);

    $wheres = addedWheres($query);
    expect($wheres)->toHaveCount(1)
        ->and($wheres[0]['type'])->toBe('Date')
        ->and($wheres[0]['operator'])->toBe($operator)
        ->and($wheres[0]['value'])->toBe($date);
})->with([
    'iso date with operator' => ['>=2026-01-01', '>=', '2026-01-01'],
    'plain iso date is that day' => ['2026-01-01', '=', '2026-01-01'],
    'german date format' => ['<=17.07.2026', '<=', '2026-07-17'],
]);

it('ignores unparseable dates', function (): void {
    $query = filterQuery();
    ColumnFilterParser::apply($query, 'created_at', 'date', '>=not-a-date');

    expect(addedWheres($query))->toBeEmpty();
});

it('applies badge and select values as exact matches', function (): void {
    $query = filterQuery();
    ColumnFilterParser::apply($query, 'status', 'badge', 'draft');

    $wheres = addedWheres($query);
    expect($wheres)->toHaveCount(1)
        ->and($wheres[0]['operator'])->toBe('=')
        ->and($wheres[0]['value'])->toBe('draft');
});

it('ignores empty and operator-only input', function (string $raw): void {
    $query = filterQuery();
    ColumnFilterParser::apply($query, 'name', 'text', $raw);

    expect(addedWheres($query))->toBeEmpty();
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'operator only' => ['>='],
]);
