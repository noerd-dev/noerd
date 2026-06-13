<?php

namespace Noerd\Contracts;

/**
 * Applies user/tenant layout overrides to a freshly parsed YAML config.
 *
 * Consulted by StaticConfigHelper right after Yaml::parse() for every list and
 * detail config. The core binding is a no-op (NullLayoutOverrideResolver); the
 * noerd-pro module rebinds it to inject DB-backed tenant-default and per-user
 * overrides. Keeping this as a container-resolved contract means noerd core never
 * references the Pro module.
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
