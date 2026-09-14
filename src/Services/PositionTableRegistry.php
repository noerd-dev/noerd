<?php

declare(strict_types=1);

namespace Noerd\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Noerd\Contracts\DefinesPositionColumns;

/**
 * Which position table a detail component renders: its column catalog and its
 * position model. A module registers every detail with a position table in its
 * provider's boot(); `NoerdPage::positionColumns()` and tooling that edits the
 * `positions:` block (layout tooling) read the same entry, so
 * the wiring exists exactly once.
 *
 * Keys are the BARE component name (a `module::` prefix is stripped), the name
 * the layout tooling knows a detail by — keep it unique across modules.
 */
final class PositionTableRegistry
{
    /** @var array<string, array{catalog: class-string<DefinesPositionColumns>, model: class-string<Model>}> */
    private array $tables = [];

    /**
     * @param  class-string<DefinesPositionColumns>  $catalogClass
     * @param  class-string<Model>  $modelClass
     */
    public function register(string $detailComponent, string $catalogClass, string $modelClass): void
    {
        $this->tables[self::key($detailComponent)] = [
            'catalog' => $catalogClass,
            'model' => $modelClass,
        ];
    }

    public function has(string $component): bool
    {
        return isset($this->tables[self::key($component)]);
    }

    /**
     * @return array{catalog: class-string<DefinesPositionColumns>, model: class-string<Model>}|null
     */
    public function for(string $component): ?array
    {
        return $this->tables[self::key($component)] ?? null;
    }

    public function catalogFor(string $component): ?DefinesPositionColumns
    {
        $table = $this->for($component);

        return $table === null ? null : app($table['catalog']);
    }

    private static function key(string $component): string
    {
        return Str::afterLast($component, '::');
    }
}
