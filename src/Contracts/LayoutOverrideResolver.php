<?php

namespace Noerd\Contracts;

/**
 * Applies user/tenant layout overrides to a freshly parsed YAML config.
 *
 * Consulted by StaticConfigHelper right after Yaml::parse() for every list and
 * detail config. The core binding is a no-op (NullLayoutOverrideResolver); an
 * optional module may rebind this contract to inject overrides of its own.
 * Resolving it through the container means the core never needs to know about
 * any such module.
 */
interface LayoutOverrideResolver
{
    /**
     * @param  'list'|'detail'|string  $viewType
     * @param  string  $component  the getListConfig()/getComponentFields() key (e.g. 'customers-list')
     * @param  array<string, mixed>  $config  the parsed YAML config
     * @return array<string, mixed>  the config with overrides applied (unchanged when there are none)
     */
    public function apply(string $viewType, string $component, array $config): array;
}
