<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;

/**
 * Shared tab visibility rules of the tab bar (`x-noerd::tabs`) and the tab
 * panels (`x-noerd::tab-content`): the server-side conditions (`requiresId`,
 * `permission`, `viewExists`) decide whether a tab renders at all; `showIf` /
 * `showIfNot` become the reactive Alpine expression of FieldVisibility.
 */
final class TabVisibility
{
    /**
     * @param  array<string, mixed>  $tab
     */
    public static function renders(array $tab, mixed $modelId): bool
    {
        if (($tab['requiresId'] ?? false) && ! $modelId) {
            return false;
        }

        if (isset($tab['permission']) && ! Gate::allows($tab['permission'], $tab['permissionModel'] ?? null)) {
            return false;
        }

        return ! (isset($tab['viewExists']) && ! View::exists($tab['viewExists']));
    }
}
