<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Throwable;

/**
 * What a list column field means for a model: its column type from the DB
 * schema, whether a dotted field is a path into a JSON-cast column
 * (`custom_attributes.x`) or through Eloquent relations (`customer.name`).
 *
 * The answers depend on the model class, its relation methods and the schema
 * only — never on request state — so they are cached per process like
 * SchemaColumnCache and flushed together with it.
 */
final class ListColumnIntrospection
{
    /** DB column type → list column type. Types missing here render as text. */
    public const COLUMN_TYPE_MAP = [
        'tinyint' => 'bool',
        'boolean' => 'bool',
        'integer' => 'number',
        'bigint' => 'number',
        'smallint' => 'number',
        'decimal' => 'number',
        'float' => 'number',
        'double' => 'number',
        'date' => 'date',
        'datetime' => 'datetime',
        'timestamp' => 'datetime',
    ];

    private const ARRAY_CASTS = ['array', 'json', 'object', 'collection'];

    /** @var array<class-string<Model>, Model> */
    private static array $instances = [];

    /** @var array<string, bool> */
    private static array $jsonPaths = [];

    /** @var array<string, array{relation: string, column: string, table: string}|null> */
    private static array $relationPaths = [];

    /**
     * One shared instance of a model class for table-name and cast lookups.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function instance(string $modelClass): Model
    {
        return self::$instances[$modelClass] ??= new $modelClass();
    }

    /**
     * The list column type of a DB column type (`varchar(255)` → null,
     * `tinyint(1)` → `bool`), or null when it renders as plain text.
     */
    public static function columnType(string $dbType): ?string
    {
        return self::COLUMN_TYPE_MAP[mb_strtolower((string) preg_replace('/\(.*\)/', '', $dbType))] ?? null;
    }

    /**
     * Map of column name => list column type for the given table.
     *
     * @return array<string, string>
     */
    public static function schemaTypes(string $table): array
    {
        $types = [];
        foreach (SchemaColumnCache::columns($table) as $name => $column) {
            $type = self::columnType((string) $column['type_name']);
            if ($type !== null) {
                $types[$name] = $type;
            }
        }

        return $types;
    }

    /**
     * Give every YAML column without an explicit `type:` the type of its DB
     * column and right-align number/currency columns without an `align:`.
     *
     * @param  array<string, mixed>  $listSettings
     * @return array<string, mixed>
     */
    public static function applyAutoTypes(array $listSettings, ?Model $model): array
    {
        if ($model === null) {
            return $listSettings;
        }

        $types = self::schemaTypes($model->getTable());

        foreach ($listSettings['columns'] ?? [] as $i => $column) {
            if (! isset($column['type']) && ! ComputedFields::isComputed($column)) {
                $field = $column['field'] ?? null;
                if (is_string($field) && isset($types[$field])) {
                    $listSettings['columns'][$i]['type'] = $column['type'] = $types[$field];
                }
            }

            if (! isset($column['align']) && in_array($column['type'] ?? 'text', ['number', 'currency'], true)) {
                $listSettings['columns'][$i]['align'] = 'right';
            }
        }

        return $listSettings;
    }

    /**
     * Whether a dotted field addresses a path inside a JSON column of the model
     * (`custom_attributes.sap_number`): the base segment is a real table column
     * the model casts to an array/object, so the query can filter it through
     * the JSON arrow operator.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function isJsonPath(string $modelClass, string $field): bool
    {
        return self::$jsonPaths[$modelClass . '|' . $field] ??= (function () use ($modelClass, $field): bool {
            $model = self::instance($modelClass);
            $base = Str::before($field, '.');

            return SchemaColumnCache::hasColumn($model->getTable(), $base)
                && $model->hasCast($base, self::ARRAY_CASTS);
        })();
    }

    /**
     * Resolve a dotted field into a relation filter target: everything before
     * the last dot is the (possibly nested) relation path handed to whereHas(),
     * the last segment the column on the related table —
     * `defaultDeliveryAddress.locality` => relation `defaultDeliveryAddress`,
     * column `locality`. Null whenever a segment is not a relation method or
     * the column does not exist, so a YAML typo never breaks the list.
     *
     * @param  class-string<Model>  $modelClass
     * @return array{relation: string, column: string, table: string}|null
     */
    public static function relationPath(string $modelClass, string $field): ?array
    {
        $key = $modelClass . '|' . $field;

        if (! array_key_exists($key, self::$relationPaths)) {
            self::$relationPaths[$key] = self::resolveRelationPath($modelClass, $field);
        }

        return self::$relationPaths[$key];
    }

    /**
     * The relations behind the dotted relation columns of a list, ready for
     * `$query->with()`. JSON paths resolve in memory and are skipped.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, string>
     */
    public static function eagerLoads(string $modelClass, array $columns): array
    {
        $relations = [];

        foreach ($columns as $column) {
            $field = $column['field'] ?? null;
            if (! is_string($field) || ! str_contains($field, '.') || self::isJsonPath($modelClass, $field)) {
                continue;
            }

            $path = self::relationPath($modelClass, $field);
            if ($path !== null) {
                $relations[$path['relation']] = true;
            }
        }

        return array_keys($relations);
    }

    /**
     * The rows of a list result as a collection (paginator, collection or
     * array), or null for anything else.
     */
    public static function rows(mixed $rows): ?Collection
    {
        return match (true) {
            $rows instanceof LengthAwarePaginator, $rows instanceof Paginator => $rows->getCollection(),
            $rows instanceof Collection => $rows,
            is_array($rows) => collect($rows),
            default => null,
        };
    }

    /**
     * The first row of a list result when it is a model.
     */
    public static function firstModel(mixed $rows): ?Model
    {
        $first = self::rows($rows)?->first();

        return $first instanceof Model ? $first : null;
    }

    public static function clear(): void
    {
        self::$instances = [];
        self::$jsonPaths = [];
        self::$relationPaths = [];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array{relation: string, column: string, table: string}|null
     */
    private static function resolveRelationPath(string $modelClass, string $field): ?array
    {
        $segments = explode('.', $field);
        $column = (string) array_pop($segments);

        if ($segments === [] || $column === '') {
            return null;
        }

        $model = self::instance($modelClass);
        foreach ($segments as $segment) {
            $model = self::relatedModel($model, $segment);
            if ($model === null) {
                return null;
            }
        }

        $table = $model->getTable();
        if (! Schema::hasTable($table) || ! SchemaColumnCache::hasColumn($table, $column)) {
            return null;
        }

        return ['relation' => implode('.', $segments), 'column' => $column, 'table' => $table];
    }

    /**
     * The related model behind one relation segment, or null when the method is
     * not a callable no-argument relation. Calling the method is unavoidable to
     * learn the related class, so it is guarded by reflection and try/catch.
     */
    private static function relatedModel(Model $model, string $method): ?Model
    {
        if ($method === '' || ! method_exists($model, $method)) {
            return null;
        }

        try {
            $reflection = new ReflectionMethod($model, $method);

            if (! $reflection->isPublic() || $reflection->isStatic() || $reflection->getNumberOfRequiredParameters() > 0) {
                return null;
            }

            $relation = $model->{$method}();
        } catch (Throwable) {
            return null;
        }

        return $relation instanceof Relation ? $relation->getRelated() : null;
    }
}
