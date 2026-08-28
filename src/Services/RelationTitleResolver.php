<?php

namespace Noerd\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

    public function __construct(private readonly RelationFieldRegistry $registry) {}

    public function title(string $fkColumn, mixed $id): ?string
    {
        if ($id === null || $id === '' || ! str_ends_with($fkColumn, '_id')) {
            return null;
        }

        $key = $fkColumn . '|' . $id;

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
            if ($id === null || $id === '' || array_key_exists($fkColumn . '|' . $id, $this->titles)) {
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
                $names = DB::table($table)->whereIn('id', array_values($remaining))->pluck($nameColumn, 'id');
                foreach ($names as $id => $name) {
                    if (is_string($name) && mb_trim($name) !== '') {
                        $resolved[(string) $id] = $name;
                    }
                }
            }
        }

        foreach ($pending as $idKey => $id) {
            $this->titles[$fkColumn . '|' . $id] = $resolved[$idKey] ?? (string) $id;
        }
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
            $name = DB::table($table)->where('id', $id)->value($nameColumn);
            if (is_string($name) && mb_trim($name) !== '') {
                return $name;
            }
        }

        return (string) $id;
    }

    private function nameColumn(string $table): string|false
    {
        return $this->nameColumns[$table]
            ??= (Schema::hasTable($table) && Schema::hasColumn($table, 'name')) ? 'name' : false;
    }
}
