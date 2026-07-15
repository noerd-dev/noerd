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
     * @param  class-string|null  $modelClass  the model the view renders, when the caller knows it.
     *                                         Config YAML almost never declares a `model:` key, so this is
     *                                         the only reliable way for a resolver to key off the model
     *                                         rather than the component. Null when the caller has no model
     *                                         (e.g. tooling reading a config out of context).
     * @return array<string, mixed>  the config with overrides applied (unchanged when there are none)
     */
    public function apply(string $viewType, string $component, array $config, ?string $modelClass = null): array;
}
