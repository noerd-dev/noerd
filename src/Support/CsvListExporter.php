<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Noerd\Helpers\FormatHelper;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a query as a CSV download in the notation of the list export:
 * UTF-8 BOM, the configured delimiter, per-column type formatting and a guard
 * against CSV formula injection. Rows are read lazily in chunks, so a large
 * export stays flat in memory.
 */
final class CsvListExporter
{
    public const CHUNK_SIZE = 200;

    /**
     * @param  array<int, array<string, mixed>>  $columns  List columns (`field`, `label`, `type`, `options`).
     * @param  (callable(Collection): void)|null  $prepareChunk  Runs once per chunk, e.g. to fill computed columns.
     * @param  (callable(mixed): void)|null  $prepareRow  Runs before a row is written.
     * @param  (callable(mixed, array<string, mixed>): string)|null  $format  Replaces formatValue().
     */
    public static function stream(
        Builder $query,
        array $columns,
        string $filename,
        ?callable $prepareChunk = null,
        ?callable $prepareRow = null,
        ?callable $format = null,
    ): StreamedResponse {
        $format ??= self::formatValue(...);

        return response()->streamDownload(function () use ($query, $columns, $prepareChunk, $prepareRow, $format): void {
            $handle = fopen('php://output', 'w');
            $delimiter = FormatHelper::csvDelimiter();

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_map(
                fn(array $column): string => __($column['label'] ?? $column['field'] ?? ''),
                $columns,
            ), $delimiter);

            $query->lazy(self::CHUNK_SIZE)->chunk(self::CHUNK_SIZE)->each(function ($rows) use ($handle, $columns, $delimiter, $prepareChunk, $prepareRow, $format): void {
                if ($prepareChunk !== null) {
                    $prepareChunk($rows->collect());
                }

                foreach ($rows as $row) {
                    if ($prepareRow !== null) {
                        $prepareRow($row);
                    }

                    $line = [];
                    foreach ($columns as $column) {
                        $line[] = $format(data_get($row, $column['field'] ?? ''), $column);
                    }

                    fputcsv($handle, $line, $delimiter);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * One cell in the reader's notation. Amounts stay plain decimals without a
     * currency symbol, so the column imports as a number.
     *
     * @param  array<string, mixed>  $column
     */
    public static function formatValue(mixed $value, array $column): string
    {
        return match ($column['type'] ?? 'text') {
            'bool', 'boolean' => ListCellFormatter::truthy($value) ? __('Yes') : __('No'),
            'date' => FormatHelper::date($value),
            'datetime' => FormatHelper::dateTime($value),
            'currency', 'number' => is_numeric($value)
                ? FormatHelper::decimal((float) $value)
                : self::neutralizeFormula(self::stringify($value)),
            'badge', 'preview' => self::neutralizeFormula(ListCellFormatter::format($value, $column)),
            default => self::neutralizeFormula(self::stringify($value)),
        };
    }

    /**
     * Excel and Sheets execute a cell that starts with =, +, -, @, tab or CR as
     * a formula, so a user-entered `=HYPERLINK(...)` would run on open. A
     * leading quote keeps such a value literal.
     */
    public static function neutralizeFormula(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Arrays and objects reach the CSV through JSON columns
     * (custom_attributes) — a bare string cast would fatal on them.
     */
    private static function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value) || (is_object($value) && ! method_exists($value, '__toString'))) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}
