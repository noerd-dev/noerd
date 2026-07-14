<?php

namespace Noerd\Services;

use Noerd\Contracts\LayoutOverrideResolver;

/**
 * Default no-op resolver: returns the config untouched. This keeps the core fully
 * inert unless a module rebinds the resolver — every list and detail renders
 * straight from its YAML.
 */
final class NullLayoutOverrideResolver implements LayoutOverrideResolver
{
    public function apply(string $viewType, string $component, array $config): array
    {
        return $config;
    }
}
