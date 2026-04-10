<?php

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Noerd\Models\SetupCollection;
use Noerd\Models\Tenant;
use Noerd\Repositories\DatabaseSetupCollectionDefinitionRepository;
use Noerd\Repositories\YamlSetupCollectionDefinitionRepository;

class ImportSetupCollectionDefinitionsCommand extends Command
{
    protected $signature = 'noerd:setup-collections:import-yaml
                            {--tenant-id= : Import definitions for a specific tenant ID}
                            {--all-tenants : Import definitions for every tenant}
                            {--delete : Delete source YAML files after a successful import}
                            {--dry-run : Show what would happen without writing anything}';

    protected $description = 'Import setup collection definitions from YAML files into the setup_collection_definitions table';

    public function handle(): int
    {
        $yamlPath = base_path(config('noerd.collections.setup_yaml_path', 'app-configs/setup/collections'));
        $yamlRepo = new YamlSetupCollectionDefinitionRepository($yamlPath);
        $dbRepo = new DatabaseSetupCollectionDefinitionRepository();

        $definitions = $yamlRepo->all();
        if ($definitions->isEmpty()) {
            $this->warn("No YAML files found in {$yamlPath}.");

            return self::SUCCESS;
        }

        $tenants = $this->resolveTargetTenants();
        if ($tenants->isEmpty()) {
            $this->error('No tenants resolved. Use --tenant-id or --all-tenants.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $imported = 0;

        foreach ($tenants as $tenant) {
            foreach ($definitions as $definition) {
                if ($dryRun) {
                    $this->line("[dry-run] would import {$definition->filename} for tenant {$tenant->id}");

                    continue;
                }

                $existing = $dbRepo->find($definition->filename, $tenant->id);
                $dbRepo->save(
                    $definition,
                    originalFilename: $existing ? $definition->filename : null,
                    tenantId: $tenant->id,
                );

                // Ensure the per-tenant SetupCollection instance bucket exists so
                // the dynamic sidebar entry surfaces the imported definition.
                SetupCollection::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'collection_key' => $definition->key,
                ], [
                    'name' => $definition->titleList,
                ]);

                $imported++;
                $this->line("imported {$definition->filename} for tenant {$tenant->id}");
            }
        }

        if (! $dryRun && $this->option('delete')) {
            foreach (glob($yamlPath . '/*.yml') ?: [] as $file) {
                @unlink($file);
            }
            $this->info('Deleted source YAML files.');
        }

        $this->newLine();
        $this->info("Imported {$imported} definition(s).");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    private function resolveTargetTenants(): \Illuminate\Support\Collection
    {
        if ($this->option('all-tenants')) {
            return Tenant::all();
        }

        if ($tenantId = $this->option('tenant-id')) {
            return Tenant::whereKey((int) $tenantId)->get();
        }

        $current = \Noerd\Helpers\TenantHelper::getSelectedTenantId();
        if ($current === null) {
            return collect();
        }

        return Tenant::whereKey($current)->get();
    }
}
