<?php

namespace Noerd\Support;

/**
 * Shared ordering rule of every sort-aware registry (detail slots, relation
 * box tiles): lower sorts first, equal sorts keep registration order.
 */
final class SortedRegistrations
{
    /**
     * Order the entries by their 'sort' value and return the payload column.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, mixed>
     */
    public static function payloads(array $entries, string $payloadKey): array
    {
        usort($entries, fn(array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return array_column($entries, $payloadKey);
    }
}
