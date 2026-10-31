<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Noerd\Services\ListCellRendererRegistry;

/**
 * Resolves the `cell:` key of a list column to the view of a registered
 * renderer and hands it what it needs. An unknown key or a missing view
 * falls back to the column's normal type rendering (logged once), so a YAML
 * naming a renderer of a module that is not installed never breaks a list.
 */
final class ListCellRenderer
{
    /** @var array<string, true> */
    private static array $warned = [];

    /**
     * @param  array<string, mixed>  $column
     */
    public static function viewFor(array $column): ?string
    {
        $key = $column['cell'] ?? null;

        if (! is_string($key) || $key === '') {
            return null;
        }

        $view = app(ListCellRendererRegistry::class)->get($key);

        if ($view !== null && View::exists($view)) {
            return $view;
        }

        if (! isset(self::$warned[$key])) {
            self::$warned[$key] = true;
            Log::warning('List cell renderer is not available; the column renders by its type.', [
                'cell' => $key,
                'view' => $view,
            ]);
        }

        return null;
    }

    /**
     * What a renderer view receives: the raw value, the text formatted by the
     * column type, the whole row and the column.
     *
     * @param  array<string, mixed>  $column
     * @return array{value: mixed, text: string, row: mixed, column: array<string, mixed>}
     */
    public static function data(mixed $value, mixed $row, array $column): array
    {
        return [
            'value' => $value,
            'text' => ListCellFormatter::format($value, $column),
            'row' => $row,
            'column' => $column,
        ];
    }
}
