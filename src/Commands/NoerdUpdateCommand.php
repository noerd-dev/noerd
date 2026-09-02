<?php

declare(strict_types=1);

namespace Noerd\Commands;

use Exception;

class NoerdUpdateCommand extends NoerdInstallCommand
{
    protected $signature = 'noerd:update {--force : Overwrite existing files without asking} {--build : Run npm build after update}';

    protected $description = 'Refresh the published setup app configs, config and frontend assets of an existing installation';

    public function handle(): int
    {
        $this->info('Updating noerd content...');

        $sourceDir = dirname(__DIR__, 2) . '/app-configs/setup';
        $targetDir = base_path('app-configs/setup');

        if (! is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");
            return self::FAILURE;
        }

        // Create target directory if it doesn't exist
        if (! is_dir($targetDir)) {
            if (! mkdir($targetDir, 0755, true)) {
                $this->error("Failed to create target directory: {$targetDir}");
                return self::FAILURE;
            }

            $this->info("Created target directory: {$targetDir}");
        }

        try {
            // 1. Copy setup files
            $results = $this->copyDirectoryContents($sourceDir, $targetDir);
            $this->displaySummary($results);

            // 2. Update configs
            $this->updatePhpunitXml();
            $this->publishNoerdConfig();

            // 3. Setup frontend assets (creates what is missing, patches what exists)
            $this->setupFrontendAssets();

            // 4. Refresh published fonts + built Vite assets
            $this->publishNoerdAssets();

            // 5. Optional: npm build (only if --build flag is set)
            if ($this->option('build')) {
                $this->runNpmBuildWithoutPrompt();
            }

            $this->info('Noerd content successfully updated!');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Error updating noerd content: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Run npm build without prompting the user
     */
    protected function runNpmBuildWithoutPrompt(): void
    {
        $this->newLine();
        $this->executeNpmBuild();
    }
}
