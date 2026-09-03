<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Js;

/**
 * Shared tab visibility rules of the tab bar (`x-noerd::tabs`) and the tab
 * panels (`x-noerd::tab-content`): the server-side conditions (`requiresId`,
 * `permission`, `viewExists`) decide whether a tab renders at all, `showIf`
 * becomes a reactive Alpine expression against the Livewire component.
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

    /**
     * The Alpine `x-show` expression for a `showIf` key (string form: property
     * truthy; object form: property equals value), or null without one.
     *
     * @param  array<string, mixed>  $tab
     */
    public static function showIfExpression(array $tab): ?string
    {
        $showIf = $tab['showIf'] ?? null;

        if (is_string($showIf) && $showIf !== '') {
            return '$wire.' . $showIf;
        }

        if (is_array($showIf) && isset($showIf['field'])) {
            return '$wire.' . $showIf['field'] . ' === ' . Js::from($showIf['value'] ?? null);
        }

        return null;
    }
}
