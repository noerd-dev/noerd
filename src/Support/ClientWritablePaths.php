<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Str;

/**
 * Which form paths the CLIENT may change on a noerd component, derived from the
 * resolved field layout (`$pageLayout`, a locked property) plus the paths a
 * component declares for hand-built inputs (`additionalDetailFields()`).
 *
 * Two kinds of paths are protected:
 * - a field marked `readonly: true` — the browser only renders the control as
 *   read-only, so the value must not be changeable through a crafted update;
 * - everything below a NESTED ROOT that is not a bound field: a key the layout
 *   binds only through nested paths (`detailData.custom_attributes.sap_number`)
 *   must not let the client add or replace the other keys of that array.
 *
 * Server-side writes are never affected — this only describes the client channel.
 */
final class ClientWritablePaths
{
    /**
     * @param  list<string>  $readonly  full field names (`detailData.code`)
     * @param  list<string>  $writable  full field names
     */
    private function __construct(
        private readonly array $readonly,
        private readonly array $writable,
    ) {}

    /**
     * @param  array<int, mixed>  $fields
     * @param  array<int, mixed>  $additional  full field names of hand-built inputs
     */
    public static function fromLayout(array $fields, array $additional = []): self
    {
        $readonly = [];
        $writable = [];

        LayoutFields::walk($fields, function (array $field) use (&$readonly, &$writable): void {
            $name = $field['name'] ?? null;
            if (! is_string($name) || ! str_contains($name, '.') || ComputedFields::isComputed($field)) {
                return;
            }

            if (! empty($field['readonly'])) {
                $readonly[] = $name;
            } else {
                $writable[] = $name;
            }
        });

        foreach ($additional as $name) {
            if (is_string($name) && str_contains($name, '.')) {
                $writable[] = $name;
            }
        }

        $writable = array_values(array_unique($writable));

        // A path an editable field also binds stays editable.
        return new self(array_values(array_diff(array_unique($readonly), $writable)), $writable);
    }

    /**
     * The paths of a component: its resolved layout plus its declared
     * hand-built fields (a protected hook method, read without making it a
     * client-callable action).
     */
    public static function forComponent(object $component): self
    {
        $fields = $component->pageLayout['fields'] ?? [];

        $additional = method_exists($component, 'additionalDetailFields')
            ? (fn(): array => $this->additionalDetailFields())->call($component)
            : [];

        return self::fromLayout(is_array($fields) ? $fields : [], $additional);
    }

    /**
     * Two dotted paths overlap when one equals or contains the other.
     */
    public static function touches(string $path, string $other): bool
    {
        return $path === $other
            || str_starts_with($other, $path . '.')
            || str_starts_with($path, $other . '.');
    }

    /**
     * Whether a client update of $fullPath may stand as sent.
     */
    public function allows(string $fullPath): bool
    {
        foreach ($this->readonly as $path) {
            if (self::touches($fullPath, $path)) {
                return false;
            }
        }

        foreach ($this->nestedRoots() as $root => $allowed) {
            if (! self::touches($fullPath, $root)) {
                continue;
            }

            foreach ($allowed as $path) {
                if ($fullPath === $path || str_starts_with($fullPath, $path . '.')) {
                    continue 2;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * The component properties holding protected paths (`detailData`, …).
     *
     * @return list<string>
     */
    public function properties(): array
    {
        $roots = array_merge($this->readonly, array_keys($this->nestedRoots()));

        return array_values(array_unique(array_map(fn(string $path): string => Str::before($path, '.'), $roots)));
    }

    /**
     * Readonly paths of one property, relative to it (`code`).
     *
     * @return list<string>
     */
    public function readonlyPaths(string $property): array
    {
        return self::relativeTo($property, $this->readonly);
    }

    /**
     * Nested roots of one property, relative to it: root key => the bound,
     * editable paths below it (`custom_attributes` => [`custom_attributes.sap`]).
     *
     * @return array<string, list<string>>
     */
    public function nestedRootsOf(string $property): array
    {
        $roots = [];

        foreach ($this->nestedRoots() as $root => $allowed) {
            if (Str::before($root, '.') === $property) {
                $roots[Str::after($root, '.')] = self::relativeTo($property, $allowed);
            }
        }

        return $roots;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private static function relativeTo(string $property, array $paths): array
    {
        $relative = [];

        foreach ($paths as $path) {
            if (str_starts_with($path, $property . '.')) {
                $relative[] = Str::after($path, $property . '.');
            }
        }

        return $relative;
    }

    /**
     * Full root (`detailData.custom_attributes`) => the editable full paths
     * below it, for every top-level key the layout binds ONLY through nested
     * paths. A key that one field binds as a whole is no nested root.
     *
     * @return array<string, list<string>>
     */
    private function nestedRoots(): array
    {
        $roots = [];
        $wholeKeys = [];

        foreach (array_merge($this->readonly, $this->writable) as $path) {
            $segments = explode('.', $path);
            if (count($segments) < 2) {
                continue;
            }

            $root = $segments[0] . '.' . $segments[1];

            if (count($segments) === 2) {
                $wholeKeys[$root] = true;

                continue;
            }

            $roots[$root] ??= [];
        }

        foreach ($this->writable as $path) {
            foreach (array_keys($roots) as $root) {
                if (str_starts_with($path, $root . '.')) {
                    $roots[$root][] = $path;
                }
            }
        }

        return array_diff_key($roots, $wholeKeys);
    }
}
