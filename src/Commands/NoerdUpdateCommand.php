<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Noerd\Commands\Concerns\PublishesNoerdContent;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Repositories\YamlSetupCollectionDefinitionRepository;
use Throwable;

class NoerdUpdateCommand extends Command
{
    use PublishesNoerdContent;

    protected $signature = 'noerd:update
                            {--force : Overwrite the published setup YAML (lists, details, pages, settings) without asking}
                            {--reset-navigation : Replace the setup and profile navigation with the package template}
                            {--dry-run : Only list the YAML files the vocabulary rewrite would change, write nothing}
                            {--build : Run npm build after update}';

    protected $description = 'Refresh the published setup app configs, config and frontend assets of an existing installation';

    public function handle(): int
    {
        if ($this->boolOption('dry-run')) {
            $this->info('Dry run: nothing is written.');
            $this->migrateYamlVocabulary(dryRun: true);
            $this->migrateTestHelperLoader(dryRun: true);
            $this->warnAboutStoredCollectionDefinitions();

            return self::SUCCESS;
        }

        $this->info('Updating noerd content...');

        try {
            if (! $this->publishNoerdContent()) {
                return self::FAILURE;
            }

            if ($this->option('build')) {
                $this->newLine();
                $this->executeNpmBuild();
            }

            $this->warnAboutStoredCollectionDefinitions();

            $this->info('Noerd content successfully updated!');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Error updating noerd content: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /** The package root — the "module" these commands install. */
    protected function getModuleRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * The core reads the setup collection definitions from YAML. A
     * `setup_collection_definitions` table that holds rows belongs to a package
     * that reads them from the database; while no such package binds its
     * repository, those definitions are not used — say so instead of dropping
     * or ignoring them silently. The table itself is never touched.
     */
    private function warnAboutStoredCollectionDefinitions(): void
    {
        try {
            if (! Schema::hasTable('setup_collection_definitions')) {
                return;
            }

            $count = DB::table('setup_collection_definitions')->count();
        } catch (Throwable) {
            // No database reachable (a build step): nothing to check.
            return;
        }

        if ($count === 0 || ! app(SetupCollectionDefinitionRepositoryContract::class) instanceof YamlSetupCollectionDefinitionRepository) {
            return;
        }

        $this->newLine();
        $this->warn("The table setup_collection_definitions holds {$count} setup collection definition(s), but the definitions are read from the YAML files in " . config('noerd.collections.setup_yaml_path', 'app-configs/setup/collections') . '.');
        $this->warn('Install the package that reads them from the database, or write them as YAML files — the table is left untouched.');
    }
}
