<?php

declare(strict_types=1);

namespace Noerd\Services;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Noerd\Helpers\TenantHelper;
use Noerd\Support\SchemaColumnCache;

/**
 * Resolves the display title for a foreign-key list cell (`relationBadge`): a
 * registered relation type wins (its titleResolver runs through Eloquent, so
 * tenant scopes apply), then the naming convention `user_id` → `users.name`,
 * then the raw id. Registered as a singleton — lookups are memoized per request.
 */
final class RelationTitleResolver
{
    /** @var array<string, string|null> */
    private array $titles = [];

    /** @var array<string, string|false> */
    private array $nameColumns = [];

    /** @var array<string, class-string<Model>|null> */
    private array $modelsByTable = [];

    public function __construct(private readonly RelationFieldRegistry $registry) {}

    public function title(string $fkColumn, mixed $id): ?string
    {
        if ($id === null || $id === '' || ! str_ends_with($fkColumn, '_id')) {
            return null;
        }

        $key = $this->memoKey($fkColumn, $id);

        return $this->titles[$key] ??= $this->resolve($fkColumn, $id);
    }

    /**
     * Prime the per-request memo for a whole page of ids in ONE query per source
     * instead of one per row: a registered relation type loads via a single
     * whereIn on its model (tenant scopes and the titleResolver apply exactly
     * like the per-id path), remaining ids via a single whereIn on the
     * convention table. Ids neither source resolves are memoized to the raw-id
     * fallback, so title() never re-queries them one by one.
     *
     * @param  array<int, mixed>  $ids
     */
    public function prime(string $fkColumn, array $ids): void
    {
        if (! str_ends_with($fkColumn, '_id')) {
            return;
        }

        $pending = [];
        foreach ($ids as $id) {
            if ($id === null || $id === '' || array_key_exists($this->memoKey($fkColumn, $id), $this->titles)) {
                continue;
            }
            $pending[(string) $id] = $id;
        }

        if ($pending === []) {
            return;
        }

        $base = Str::beforeLast($fkColumn, '_id');
        $resolved = [];

        $definition = $this->registry->resolve(Str::camel($base) . 'Relation');
        if ($definition?->modelClass !== null && class_exists($definition->modelClass)) {
            foreach ($definition->modelClass::query()->findMany(array_values($pending)) as $model) {
                $title = $definition->resolveTitle($model);
                if ($title !== '') {
                    $resolved[(string) $model->getKey()] = $title;
                }
            }
        }

        $remaining = array_diff_key($pending, $resolved);
        if ($remaining !== []) {
            $table = Str::plural($base);
            $nameColumn = $this->nameColumn($table);
            if ($nameColumn !== false) {
                $names = $this->scopedQuery($table)
                    ->whereIn('id', array_values($remaining))
                    ->pluck($nameColumn, 'id');
                foreach ($names as $id => $name) {
                    if (is_string($name) && mb_trim($name) !== '') {
                        $resolved[(string) $id] = $name;
                    }
                }
            }
        }

        foreach ($pending as $idKey => $id) {
            $this->titles[$this->memoKey($fkColumn, $id)] = $resolved[$idKey] ?? (string) $id;
        }
    }

    /**
     * Memo key including the current tenant: the convention lookup is a raw
     * unscoped DB read, so a memoized title must never be served to another
     * tenant a long-lived worker later handles (defense in depth on top of the
     * per-request reset in the provider's Octane listener).
     */
    private function memoKey(string $fkColumn, mixed $id): string
    {
        return (TenantHelper::currentTenantId() ?? 0) . '|' . $fkColumn . '|' . $id;
    }

    private function resolve(string $fkColumn, mixed $id): string
    {
        $base = Str::beforeLast($fkColumn, '_id');

        $definition = $this->registry->resolve(Str::camel($base) . 'Relation');
        if ($definition !== null) {
            $title = $definition->resolveTitleForValue($id);
            if ($title !== '') {
                return $title;
            }
        }

        $table = Str::plural($base);
        $nameColumn = $this->nameColumn($table);
        if ($nameColumn !== false) {
            $name = $this->scopedQuery($table)->where('id', $id)->value($nameColumn);
            if (is_string($name) && mb_trim($name) !== '') {
                return $name;
            }
        }

        return (string) $id;
    }

    /**
     * The convention lookup must never cross the tenant boundary. Prefer the
     * Eloquent model behind the table (its global scopes — TenantScope above
     * all — then apply); when no model can be found, fall back to the query
     * builder and narrow it by tenant_id ourselves whenever the table has
     * that column.
     */
    private function scopedQuery(string $table): Builder|EloquentBuilder
    {
        $modelClass = $this->modelForTable($table);

        if ($modelClass !== null) {
            return $modelClass::query();
        }

        $query = DB::table($table);

        if (SchemaColumnCache::hasColumn($table, 'tenant_id')) {
            $query->where('tenant_id', TenantHelper::getSelectedTenantId());
        }

        return $query;
    }

    /**
     * The Eloquent model backing a table: a morph-map entry wins (the host
     * declares it explicitly), then the naming convention
     * `customers` → `App\Models\Customer`.
     *
     * @return class-string<Model>|null
     */
    private function modelForTable(string $table): ?string
    {
        if (array_key_exists($table, $this->modelsByTable)) {
            return $this->modelsByTable[$table];
        }

        foreach (Relation::morphMap() as $class) {
            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            if ((new $class())->getTable() === $table) {
                return $this->modelsByTable[$table] = $class;
            }
        }

        $candidate = 'App\\Models\\' . Str::studly(Str::singular($table));

        if (class_exists($candidate) && is_subclass_of($candidate, Model::class) && (new $candidate())->getTable() === $table) {
            return $this->modelsByTable[$table] = $candidate;
        }

        return $this->modelsByTable[$table] = null;
    }

    private function nameColumn(string $table): string|false
    {
        return $this->nameColumns[$table]
            ??= (Schema::hasTable($table) && Schema::hasColumn($table, 'name')) ? 'name' : false;
    }
}
