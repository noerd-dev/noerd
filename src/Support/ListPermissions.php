<?php

declare(strict_types=1);

namespace Noerd\Support;

use Noerd\Helpers\AccessHelper;

/**
 * Strips the list affordances the current user may not use from a list config.
 * A model class of null (in-memory lists) is never restricted.
 *
 * @internal
 */
final class ListPermissions
{
    /**
     * - read denied: no header actions at all
     * - "New …" buttons (`action: listAction` or a record opened by
     *   `modalRoute:`) need CREATE, every other header action WRITE
     * - the generic `deleteSelected` bulk action needs DELETE, every other
     *   bulk action WRITE (a bulk action changes the selected records)
     *
     * @param  array<string, mixed>  $listSettings
     * @return array<string, mixed>
     */
    public static function strip(array $listSettings, ?string $modelClass): array
    {
        if (! AccessHelper::canReadObject($modelClass)) {
            unset($listSettings['actions']);
        } else {
            $canCreate = AccessHelper::canCreateObject($modelClass);
            $canWrite = AccessHelper::canWriteObject($modelClass);

            if (! $canCreate || ! $canWrite) {
                $listSettings['actions'] = array_values(array_filter(
                    $listSettings['actions'] ?? [],
                    function (array $action) use ($canCreate, $canWrite): bool {
                        $isCreate = ($action['action'] ?? null) === 'listAction' || isset($action['modalRoute']);

                        return $isCreate ? $canCreate : $canWrite;
                    },
                ));

                if ($listSettings['actions'] === []) {
                    unset($listSettings['actions']);
                }
            }
        }

        if (isset($listSettings['bulkActions']) && is_array($listSettings['bulkActions'])) {
            $canRead = AccessHelper::canReadObject($modelClass);
            $canDelete = $canRead && AccessHelper::canDeleteObject($modelClass);
            $canWrite = $canRead && AccessHelper::canWriteObject($modelClass);

            $listSettings['bulkActions'] = array_values(array_filter(
                $listSettings['bulkActions'],
                fn(mixed $action): bool => is_array($action)
                    && (($action['action'] ?? null) === 'deleteSelected' ? $canDelete : $canWrite),
            ));
        }

        return $listSettings;
    }

    /**
     * Whether $method is a bulk action the RAW list config declares but the
     * stripped config no longer offers this user (and no remaining header
     * action names it) — the server-side refusal behind the hidden button.
     *
     * @param  array<string, mixed>  $listSettings  the raw list config
     */
    public static function refusesAction(array $listSettings, ?string $modelClass, string $method): bool
    {
        $declares = static fn(array $actions): bool => collect($actions)
            ->contains(fn(mixed $action): bool => is_array($action) && ($action['action'] ?? null) === $method);

        if (! $declares($listSettings['bulkActions'] ?? [])) {
            return false;
        }

        $stripped = self::strip($listSettings, $modelClass);

        return ! $declares($stripped['bulkActions'] ?? []) && ! $declares($stripped['actions'] ?? []);
    }
}
