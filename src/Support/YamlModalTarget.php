<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Js;

/**
 * The ONE vocabulary of a modal target in app-config YAML. Wherever a YAML entry
 * opens a modal — a list or detail action, a row action, a `relationLink` column,
 * a modal tab, a relation-box tile, a navigation entry — it names the target with
 * the same two keys:
 *
 * - `modalRoute:` a named `Route::livewire()` route whose component opens as the
 *   modal (the browser URL follows the record where the place allows it),
 * - `modalComponent:` the component opened as a modal — the fallback while the
 *   route is not registered, or the target on its own for a component modal.
 *
 * A place with a second target prefixes both keys: the "+" of a navigation entry
 * is `newModalRoute:` / `newModalComponent:`, the "Show more" link of a page
 * widget `showMoreModalRoute:` / `showMoreModalComponent:`. `route:` never opens
 * a modal — where it is read at all it navigates.
 */
final class YamlModalTarget
{
    /**
     * The route and component key of a target, e.g. `newModalRoute` / `newModalComponent`
     * for the prefix `new`.
     *
     * @return array{0: string, 1: string}
     */
    public static function keys(string $prefix = ''): array
    {
        return $prefix === ''
            ? ['modalRoute', 'modalComponent']
            : [$prefix . 'ModalRoute', $prefix . 'ModalComponent'];
    }

    /**
     * The target's route when it is registered, otherwise null.
     *
     * @param  array<string, mixed>  $config
     */
    public static function route(array $config, string $prefix = ''): ?string
    {
        $route = $config[self::keys($prefix)[0]] ?? null;

        return is_string($route) && $route !== '' && Route::has($route) ? $route : null;
    }

    /**
     * The target's component (the fallback of the route, or the target itself).
     *
     * @param  array<string, mixed>  $config
     */
    public static function component(array $config, string $prefix = ''): ?string
    {
        $component = $config[self::keys($prefix)[1]] ?? null;

        return is_string($component) && $component !== '' ? $component : null;
    }

    /**
     * Whether the entry names a modal target at all — also a route that is not
     * registered (the caller decides whether such an entry is dropped).
     *
     * @param  array<string, mixed>  $config
     */
    public static function declares(array $config, string $prefix = ''): bool
    {
        [$routeKey, $componentKey] = self::keys($prefix);

        return ! empty($config[$routeKey]) || ! empty($config[$componentKey]);
    }

    /**
     * Whether the entry opens a modal: a registered route or a component.
     *
     * @param  array<string, mixed>  $config
     */
    public static function opens(array $config, string $prefix = ''): bool
    {
        return self::route($config, $prefix) !== null || self::component($config, $prefix) !== null;
    }

    /**
     * The Alpine expression that opens the target — `$modalRoute(…)` with the
     * component as fallback, `$modal(…)` for a component alone — or null when the
     * entry opens nothing.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $options  extra `$modalRoute` options, e.g. `['rewriteUrl' => false]`
     */
    public static function clickExpression(array $config, array $arguments = [], array $options = [], string $prefix = '', ?string $size = null): ?string
    {
        $route = self::route($config, $prefix);
        $component = self::component($config, $prefix);

        if ($route !== null) {
            $options = array_filter([...$options, 'fallbackComponent' => $component], fn(mixed $value): bool => $value !== null);

            return '$modalRoute(' . Js::from($route) . ', ' . Js::from($arguments) . ', null, null, ' . Js::from($size) . ', ' . Js::from((object) $options) . ')';
        }

        if ($component !== null) {
            return '$modal(' . Js::from($component) . ', ' . Js::from($arguments) . ', null, null, ' . Js::from($size) . ')';
        }

        return null;
    }
}
