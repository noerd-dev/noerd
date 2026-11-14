<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Str;

/**
 * Which form paths the CLIENT may change on a noerd component, derived from the
 * resolved field layout (`$pageLayout`, a locked property) plus the paths a
 * component declares for hand-built inputs (`additionalDetailFields()`).
 *
 * Three kinds of paths are protected:
 * - a field marked `readonly: true`, and a field rendered as text (a text-only
 *   theme such as `display`, on the field, its block or the whole layout — see
 *   FieldRules::rendersAsText()) — the browser shows no editable control, so
 *   the value must not be changeable through a crafted update;
 * - everything below a NESTED ROOT that is not a bound field: a key the layout
 *   binds only through nested paths (`detailData.custom_attributes.sap_number`)
 *   must not let the client add or replace the other keys of that array;
 * - every top-level key of a BOUND PROPERTY that no field declares: once the
 *   layout binds `detailData.*`, a crafted update of `detailData.tenant_id` or
 *   `detailData.id` is no input of the form (fail-closed — also when the layout
 *   carries no readonly field and no nested root).
 *
 * Server-side writes are never affected — this only describes the client channel.
 *
 * @internal
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
     * @param  string|null  $layoutTheme  the theme of the whole layout (`$pageLayout['theme']`)
     */
    public static function fromLayout(array $fields, array $additional = [], ?string $layoutTheme = null): self
    {
        $readonly = [];
        $writable = [];

        self::collect($fields, $layoutTheme, $readonly, $writable);

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

        $theme = $component->pageLayout['theme'] ?? null;

        return self::fromLayout(is_array($fields) ? $fields : [], $additional, is_string($theme) ? $theme : null);
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
        $property = Str::before($fullPath, '.');

        if (in_array($property, $this->boundProperties(), true)) {
            // Replacing the whole bound property may smuggle in any key.
            if (! str_contains($fullPath, '.')) {
                return false;
            }

            $key = Str::before(Str::after($fullPath, $property . '.'), '.');
            if (! in_array($key, $this->declaredKeysOf($property), true)) {
                return false;
            }
        }

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
     * The component properties holding protected paths (`detailData`, …): every
     * property a field of the layout (or a declared hand-built input) binds.
     *
     * @return list<string>
     */
    public function properties(): array
    {
        return $this->boundProperties();
    }

    /**
     * The top-level keys of one property a field declares (`name`,
     * `custom_attributes`, …) — every other key is no input of the form.
     *
     * @return list<string>
     */
    public function declaredKeysOf(string $property): array
    {
        $keys = [];

        foreach (array_merge($this->readonly, $this->writable) as $path) {
            if (str_starts_with($path, $property . '.')) {
                $keys[] = Str::before(Str::after($path, $property . '.'), '.');
            }
        }

        return array_values(array_unique($keys));
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
     * Sort the named fields into readonly and writable paths, depth-first
     * through `type: block` groups, a block's theme inherited by its fields.
     *
     * @param  array<int, mixed>  $fields
     * @param  list<string>  $readonly
     * @param  list<string>  $writable
     */
    private static function collect(array $fields, ?string $inheritedTheme, array &$readonly, array &$writable): void
    {
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            if (($field['type'] ?? null) === 'block') {
                $blockTheme = is_string($field['theme'] ?? null) ? $field['theme'] : $inheritedTheme;
                self::collect(is_array($field['fields'] ?? null) ? $field['fields'] : [], $blockTheme, $readonly, $writable);

                continue;
            }

            $name = $field['name'] ?? null;
            if (! is_string($name) || ! str_contains($name, '.') || ComputedFields::isComputed($field)) {
                continue;
            }

            if (! empty($field['readonly']) || FieldRules::rendersAsText($field, $inheritedTheme)) {
                $readonly[] = $name;
            } else {
                $writable[] = $name;
            }
        }
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
     * @return list<string>
     */
    private function boundProperties(): array
    {
        return array_values(array_unique(array_map(
            fn(string $path): string => Str::before($path, '.'),
            array_merge($this->readonly, $this->writable),
        )));
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
