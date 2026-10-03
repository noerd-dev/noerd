<?php

declare(strict_types=1);

namespace Noerd\Support;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Stringable;
use Throwable;

/**
 * Turns a computed value into what a list cell or detail field of the given
 * display `type` shows: dates as ISO strings (`Y-m-d`, `Y-m-d H:i:s`), numbers
 * as numbers, booleans as booleans. A value that does not fit the type, or
 * that is no scalar, date, enum or Stringable, presents as blank — it never
 * reaches a formatter that would throw.
 */
final class ComputedValuePresenter
{
    public static function present(mixed $value, ?string $type): mixed
    {
        $value = match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => CarbonImmutable::instance($value),
            $value instanceof Stringable => (string) $value,
            default => $value,
        };

        if ($value === null || $value === '' || (! is_scalar($value) && ! $value instanceof CarbonImmutable)) {
            return null;
        }

        return match ($type) {
            'number', 'currency' => self::number($value),
            'bool', 'boolean', 'checkbox', 'inversebool' => self::bool($value),
            'date' => self::date($value)?->format('Y-m-d'),
            'datetime', 'datetime-local' => self::date($value)?->format('Y-m-d H:i:s'),
            default => self::text($value),
        };
    }

    private static function number(mixed $value): float|int|null
    {
        return match (true) {
            is_bool($value) => $value ? 1 : 0,
            is_int($value), is_float($value) => $value,
            is_string($value) && is_numeric($value) => $value + 0,
            default => null,
        };
    }

    private static function bool(mixed $value): bool
    {
        return match (true) {
            is_bool($value) => $value,
            is_int($value), is_float($value) => (float) $value !== 0.0,
            is_string($value) => in_array(mb_strtolower($value), ['1', 'true', 'yes', 'on'], true),
            default => true,
        };
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}/', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private static function text(mixed $value): mixed
    {
        return match (true) {
            $value instanceof CarbonImmutable => $value->format($value->format('H:i:s') === '00:00:00' ? 'Y-m-d' : 'Y-m-d H:i:s'),
            $value === true => 'true',
            $value === false => 'false',
            is_float($value) => mb_rtrim(mb_rtrim(number_format($value, 10, '.', ''), '0'), '.'),
            default => $value,
        };
    }
}
