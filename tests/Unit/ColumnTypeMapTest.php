<?php

declare(strict_types=1);

use Noerd\Commands\Concerns\GeneratesResourceFiles;
use Noerd\Support\ListColumnIntrospection;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | The `type_name` Schema::getColumns() reports differs per database: MySQL says
 | `int`/`mediumint`, PostgreSQL `int4`/`numeric`, SQLite `integer`/`real`. Every
 | spelling of a numeric, boolean, date or time column must get the same list
 | column type — and the generator's detail field type follows the list map.
 */

function zzDetailTypeOf(string $dbType): string
{
    $generator = new class {
        use GeneratesResourceFiles;

        public function detailType(string $dbType): string
        {
            return $this->getDetailType($dbType);
        }
    };

    return $generator->detailType($dbType);
}

it('maps every database spelling of a column type to its list column type', function (string $typeName, ?string $listType): void {
    expect(ListColumnIntrospection::columnType($typeName))->toBe($listType);
})->with([
    'mysql int' => ['int', 'number'],
    'mysql int(11)' => ['int(11)', 'number'],
    'mysql INT unsigned spelling' => ['INT', 'number'],
    'mysql mediumint' => ['mediumint', 'number'],
    'mysql bigint' => ['bigint', 'number'],
    'mysql tinyint(1)' => ['tinyint(1)', 'bool'],
    'mysql decimal(10,2)' => ['decimal(10,2)', 'number'],
    'mysql time' => ['time', 'time'],
    'mysql year' => ['year', null],
    'pgsql int2' => ['int2', 'number'],
    'pgsql int4' => ['int4', 'number'],
    'pgsql int8' => ['int8', 'number'],
    'pgsql numeric' => ['numeric', 'number'],
    'pgsql float8' => ['float8', 'number'],
    'pgsql bool' => ['bool', 'bool'],
    'pgsql timestamptz' => ['timestamptz', 'datetime'],
    'pgsql timetz' => ['timetz', 'time'],
    'sqlite integer' => ['integer', 'number'],
    'sqlite real' => ['real', 'number'],
    'text' => ['varchar(255)', null],
]);

it('derives the generated detail field type from the list map', function (string $typeName, string $detailType): void {
    expect(zzDetailTypeOf($typeName))->toBe($detailType);
})->with([
    'mysql int' => ['int', 'number'],
    'mysql mediumint' => ['mediumint', 'number'],
    'pgsql int4' => ['int4', 'number'],
    'pgsql numeric' => ['numeric', 'number'],
    'sqlite real' => ['real', 'number'],
    'boolean' => ['tinyint(1)', 'checkbox'],
    'date' => ['date', 'date'],
    'time' => ['time', 'time'],
    'datetime' => ['datetime', 'datetime-local'],
    'pgsql timestamptz' => ['timestamptz', 'datetime-local'],
    'text column' => ['text', 'textarea'],
    'json column' => ['json', 'textarea'],
    'varchar' => ['varchar(255)', 'text'],
    'year' => ['year', 'text'],
]);
