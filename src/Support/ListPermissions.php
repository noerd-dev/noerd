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
     * - "New …" buttons (`action: listAction` or a route-opened record) need
     *   CREATE, every other header action WRITE
     * - the generic `deleteSelected` bulk action needs DELETE
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
                        $isCreate = ($action['action'] ?? null) === 'listAction' || isset($action['route']);

                        return $isCreate ? $canCreate : $canWrite;
                    },
                ));

                if ($listSettings['actions'] === []) {
                    unset($listSettings['actions']);
                }
            }
        }

        if (! AccessHelper::canDeleteObject($modelClass)) {
            $listSettings['bulkActions'] = array_values(array_filter(
                $listSettings['bulkActions'] ?? [],
                fn(array $action): bool => ($action['action'] ?? null) !== 'deleteSelected',
            ));
        }

        return $listSettings;
    }
}
