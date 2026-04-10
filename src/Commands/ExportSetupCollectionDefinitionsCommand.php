<?php

namespace Noerd\Commands;

use Illuminate\Console\Command;
use Noerd\Repositories\DatabaseSetupCollectionDefinitionRepository;
use Symfony\Component\Yaml\Yaml;

class ExportSetupCollectionDefinitionsCommand extends Command
{
    protected $signature = 'noerd:setup-collections:export-yaml
                            {--tenant-id= : Export definitions for a specific tenant ID}
                            {--delete : Delete the database rows after a successful export}
                            {--force : Overwrite existing YAML files}';

    protected $description = 'Export setup collection definitions from the database as YAML files';

    public function handle(DatabaseSetupCollectionDefinitionRepository $repository): int
    {
        $yamlPath = base_path(config('noerd.collections.setup_yaml_path', 'app-configs/setup/collections'));
        if (! is_dir($yamlPath)) {
            mkdir($yamlPath, 0755, true);
        }

        $tenantId = $this->option('tenant-id') ? (int) $this->option('tenant-id') : null;
        $definitions = $repository->all($tenantId);

        if ($definitions->isEmpty()) {
            $this->warn('No database definitions found for export.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $written = 0;
        $skipped = 0;

        foreach ($definitions as $definition) {
            $file = $yamlPath . '/' . $definition->filename . '.yml';

            if (file_exists($file) && ! $force) {
                $this->warn("skipped (exists): {$definition->filename}");
                $skipped++;

                continue;
            }

            file_put_contents($file, Yaml::dump($definition->toYamlArray(), 4, 2));
            $this->line("wrote {$definition->filename}.yml");
            $written++;
        }

        if ($this->option('delete')) {
            foreach ($definitions as $definition) {
                $repository->delete($definition->filename, $tenantId);
            }
            $this->info('Deleted source database rows.');
        }

        $this->newLine();
        $this->info("Exported {$written} definition(s) to {$yamlPath}." . ($skipped ? " Skipped: {$skipped}." : ''));

        return self::SUCCESS;
    }
}
