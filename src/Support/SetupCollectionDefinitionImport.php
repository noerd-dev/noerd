<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Collection;
use Noerd\Models\SetupCollection;
use Noerd\Repositories\DatabaseSetupCollectionDefinitionRepository;
use Noerd\Repositories\YamlSetupCollectionDefinitionRepository;

/**
 * Copies the shipped collection YAMLs into setup_collection_definitions for one
 * tenant. Shared by noerd:setup-collections:import-yaml and the tenant-creation
 * hook — in database mode a tenant without definition rows has no collections at
 * all, so a fresh tenant must be seeded the same way an existing one is migrated.
 */
final class SetupCollectionDefinitionImport
{
    public static function isDatabaseMode(): bool
    {
        return config('noerd.collections.mode') === 'database';
    }

    public static function yamlPath(): string
    {
        return base_path(config('noerd.collections.setup_yaml_path', 'app-configs/setup/collections'));
    }

    /**
     * Every definition the YAML source directory ships.
     *
     * @return Collection<int, SetupCollectionDefinitionData>
     */
    public static function availableDefinitions(): Collection
    {
        return (new YamlSetupCollectionDefinitionRepository(self::yamlPath()))->all();
    }

    /**
     * Import every shipped definition for one tenant. Idempotent: an existing
     * definition of that tenant is updated in place, and the SetupCollection
     * instance bucket is created so the dynamic sidebar entry surfaces it.
     *
     * @return array<int, string> the imported filenames
     */
    public static function forTenant(int $tenantId): array
    {
        $repository = new DatabaseSetupCollectionDefinitionRepository();
        $imported = [];

        foreach (self::availableDefinitions() as $definition) {
            $exists = $repository->exists($definition->filename, $tenantId);

            $repository->save(
                $definition,
                originalFilename: $exists ? $definition->filename : null,
                tenantId: $tenantId,
            );

            SetupCollection::withoutGlobalScopes()->firstOrCreate([
                'tenant_id' => $tenantId,
                'collection_key' => $definition->key,
            ], [
                'name' => $definition->titleList,
            ]);

            $imported[] = $definition->filename;
        }

        return $imported;
    }
}
