<?php

declare(strict_types=1);

namespace Noerd\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Parses Excel-style column filter expressions ('>0', '<=10', '=rot', 'rot')
 * and applies them to a list query. The operator set is fixed and the value is
 * only ever passed as a bound parameter — user input never reaches SQL text.
 * Unparseable input is a silent no-op, never an error.
 */
final class ColumnFilterParser
{
    /**
     * Split an optional comparison-operator prefix off the raw input.
     * '<>' is normalised to '!='; op is null when no prefix is present.
     *
     * @return array{op: ?string, value: string}
     */
    public static function parse(string $raw): array
    {
        if (preg_match('/^\s*(>=|<=|!=|<>|=|>|<)\s*(.*)$/u', $raw, $matches)) {
            return [
                'op' => $matches[1] === '<>' ? '!=' : $matches[1],
                'value' => trim($matches[2]),
            ];
        }

        return ['op' => null, 'value' => trim($raw)];
    }

    /**
     * Apply one column filter to the query based on the column's resolved type.
     */
    public static function apply(Builder $query, string $field, string $type, string $raw): void
    {
        if (trim($raw) === '') {
            return;
        }

        match (true) {
            in_array($type, ['bool', 'boolean', 'inversebool'], true) => self::applyBool($query, $field, $raw),
            in_array($type, ['number', 'currency'], true) => self::applyNumber($query, $field, $raw),
            in_array($type, ['date', 'datetime'], true) => self::applyDate($query, $field, $raw),
            in_array($type, ['badge', 'select'], true) => $query->where($field, '=', trim($raw)),
            default => self::applyText($query, $field, $raw),
        };
    }

    private static function applyBool(Builder $query, string $field, string $raw): void
    {
        $value = trim($raw);
        if ($value !== '1' && $value !== '0') {
            return;
        }

        $query->where($field, $value === '1');
    }

    private static function applyNumber(Builder $query, string $field, string $raw): void
    {
        ['op' => $op, 'value' => $value] = self::parse($raw);

        $value = str_replace(',', '.', $value);
        if (! is_numeric($value)) {
            return;
        }

        $query->where($field, $op ?? '=', (float) $value);
    }

    private static function applyDate(Builder $query, string $field, string $raw): void
    {
        ['op' => $op, 'value' => $value] = self::parse($raw);

        $date = self::parseDate($value);
        if ($date === null) {
            return;
        }

        $query->whereDate($field, $op ?? '=', $date->toDateString());
    }

    private static function applyText(Builder $query, string $field, string $raw): void
    {
        ['op' => $op, 'value' => $value] = self::parse($raw);

        if ($value === '') {
            return;
        }

        if ($op === null) {
            $query->where($field, 'like', '%' . addcslashes($value, '\\%_') . '%');

            return;
        }

        $query->where($field, $op, $value);
    }

    private static function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{1,2}\.\d{1,2}\.\d{4}$/', $value)) {
                return Carbon::createFromFormat('d.m.Y', $value);
            }

            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
