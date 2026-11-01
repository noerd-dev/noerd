<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Which record an embedded detail has just CREATED for its hosting page.
 *
 * A page adopts the id its embedded detail reports (`detailStored-{name}`) and
 * then persists its own data onto that record. That report is a listener the
 * client can call with any id, so a user who may only create would otherwise
 * hand a new page the id of an existing record and write to it. The detail
 * remembers the record it created for its host in the session (the page reacts
 * in a later request), and the page accepts a foreign id only once and only
 * when it matches.
 *
 * @internal
 */
final class EmbeddedDetailCreations
{
    private const SESSION_KEY = 'noerd.embedded-detail-created';

    public static function remember(string $hostId, Model $model): void
    {
        if (! app()->bound('session')) {
            return;
        }

        session()->put(self::SESSION_KEY . '.' . $hostId, [
            'table' => $model->getTable(),
            'id' => (string) $model->getKey(),
        ]);
    }

    /**
     * Whether the embedded detail of $hostId created exactly this record. Compared
     * by table, because page and detail may name different classes of one table
     * (a model and its subclass). The entry is consumed: it admits one adoption.
     */
    public static function consume(string $hostId, Model $model): bool
    {
        if (! app()->bound('session')) {
            return false;
        }

        $created = session()->pull(self::SESSION_KEY . '.' . $hostId);

        return is_array($created)
            && ($created['table'] ?? null) === $model->getTable()
            && ($created['id'] ?? null) === (string) $model->getKey();
    }
}
