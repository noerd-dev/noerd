<?php

declare(strict_types=1);

namespace Noerd\Services;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;
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
     * Prime the memo for every `type: relationBadge` column of a list page, so
     * the table cells only read memoized titles.
     *
     * @param  array<int, array<string, mixed>>  $columns
     */
    public function primeColumns(array $columns, ?Collection $rows): void
    {
        if ($rows === null || $rows->isEmpty()) {
            return;
        }

        foreach ($columns as $column) {
            if (($column['type'] ?? null) === 'relationBadge' && isset($column['field'])) {
                $this->prime($column['field'], $rows->map(fn($row) => data_get($row, $column['field']))->all());
            }
        }
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

        $type = $this->registry->typeForColumn($fkColumn);
        $definition = $type !== null ? $this->registry->get($type) : null;
        if ($definition?->modelClass !== null && class_exists($definition->modelClass) && AccessHelper::canReadObject($definition->modelClass)) {
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
            $query = $nameColumn !== false ? $this->scopedQuery($table) : null;
            if ($query !== null) {
                $names = $query
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

        $type = $this->registry->typeForColumn($fkColumn);
        $definition = $type !== null ? $this->registry->get($type) : null;
        if ($definition !== null && AccessHelper::canReadObject($definition->modelClass)) {
            $title = $definition->resolveTitleForValue($id);
            if ($title !== '') {
                return $title;
            }
        }

        $table = Str::plural($base);
        $nameColumn = $this->nameColumn($table);
        $query = $nameColumn !== false ? $this->scopedQuery($table) : null;
        if ($query !== null) {
            $name = $query->where('id', $id)->value($nameColumn);
            if (is_string($name) && mb_trim($name) !== '') {
                return $name;
            }
        }

        return (string) $id;
    }

    /**
     * The convention lookup must never cross the tenant boundary nor a read
     * permission. Prefer the Eloquent model behind the table (its global
     * scopes — TenantScope above all — then apply, and the object read
     * permission of that model is asked); when no model can be found, the raw
     * query builder is used ONLY for a table that has a tenant_id column,
     * narrowed to the current tenant. A guessed table without one is never
     * read — the cell shows the raw id. Null means: no lookup.
     */
    private function scopedQuery(string $table): Builder|EloquentBuilder|null
    {
        // `tenant_id` → the tenants table: only the names of tenants the user
        // may work in, never an arbitrary tenant looked up by id.
        if ($table === (new Tenant())->getTable()) {
            $user = NoerdAuth::user();

            if ($user === null) {
                return null;
            }

            return $user->isSuperAdmin()
                ? Tenant::query()
                : Tenant::query()->whereIn('id', $user->tenants()->select('tenants.id'));
        }

        $modelClass = $this->modelForTable($table);

        if ($modelClass !== null) {
            return AccessHelper::canReadObject($modelClass) ? $modelClass::query() : null;
        }

        $tenantId = TenantHelper::currentTenantId();

        if ($tenantId === null || ! SchemaColumnCache::hasColumn($table, 'tenant_id')) {
            return null;
        }

        return DB::table($table)->where('tenant_id', $tenantId);
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
