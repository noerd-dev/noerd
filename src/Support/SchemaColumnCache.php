<?php

namespace Noerd\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Process-wide cache of a table's schema columns, keyed by column name. Backs
 * every column-existence/type check in the list render path —
 * Schema::hasColumn() is NOT cached by Laravel and issues one
 * information-schema query per call.
 *
 * A single shared class instead of a trait static on purpose: a static
 * property declared in a trait is duplicated into every composing class, which
 * would both fragment the cache per list component and make it impossible to
 * flush centrally (fresh app boots in tests, Octane workers after migrations).
 */
final class SchemaColumnCache
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private static array $columnsByTable = [];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function columns(string $table): array
    {
        return self::$columnsByTable[$table]
            ??= collect(Schema::getColumns($table))->keyBy('name')->all();
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return array_key_exists($column, self::columns($table));
    }

    public static function clear(): void
    {
        self::$columnsByTable = [];
    }
}
