<?php

declare(strict_types=1);

namespace Noerd\Support;

/**
 * The `arguments:` map of a YAML entry — a relation-box tile, a widget, an
 * embedded list, a modal action, a tab — with its tokens resolved: `$modelId`
 * is the current record id, `$property` a public property of the rendering
 * component, everything else a static value that passes through unchanged.
 * The one place the convention lives; every component that reads `arguments:`
 * resolves them here.
 */
final class LayoutArguments
{
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public static function resolve(array $arguments, mixed $modelId, ?object $component = null): array
    {
        $resolved = [];

        foreach ($arguments as $key => $value) {
            if (! is_string($value) || ! str_starts_with($value, '$')) {
                $resolved[$key] = $value;

                continue;
            }

            $property = mb_substr($value, 1);

            if ($property === 'modelId') {
                $resolved[$key] = $modelId;

                continue;
            }

            $resolved[$key] = $component !== null && property_exists($component, $property)
                ? $component->{$property}
                : $value;
        }

        return $resolved;
    }
}
