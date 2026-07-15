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

        $key = $fkColumn.'|'.$id;

        return $this->titles[$key] ??= $this->resolve($fkColumn, $id);
    }

    private function resolve(string $fkColumn, mixed $id): string
    {
        $base = Str::beforeLast($fkColumn, '_id');

        $definition = $this->registry->resolve(Str::camel($base).'Relation');
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
            if (is_string($name) && trim($name) !== '') {
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
