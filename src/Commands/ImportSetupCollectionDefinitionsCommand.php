<?php

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;
use Noerd\Support\SetupCollectionDefinitionImport;

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
        $yamlPath = SetupCollectionDefinitionImport::yamlPath();

        $definitions = SetupCollectionDefinitionImport::availableDefinitions();
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
            if ($dryRun) {
                foreach ($definitions as $definition) {
                    $this->line("[dry-run] would import {$definition->filename} for tenant {$tenant->id}");
                }

                continue;
            }

            foreach (SetupCollectionDefinitionImport::forTenant($tenant->id) as $filename) {
                $imported++;
                $this->line("imported {$filename} for tenant {$tenant->id}");
            }
        }

        if (! $dryRun && $this->option('delete')) {
            foreach (glob($yamlPath . '/*.yml') ?: [] as $file) {
                File::delete($file);
            }
            $this->info('Deleted source YAML files.');
        }

        $this->newLine();
        $this->info("Imported {$imported} definition(s).");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function resolveTargetTenants(): Collection
    {
        if ($this->option('all-tenants')) {
            return Tenant::all();
        }

        if ($tenantId = $this->option('tenant-id')) {
            return Tenant::whereKey((int) $tenantId)->get();
        }

        $current = TenantHelper::getSelectedTenantId();
        if ($current === null) {
            return collect();
        }

        return Tenant::whereKey($current)->get();
    }
}
