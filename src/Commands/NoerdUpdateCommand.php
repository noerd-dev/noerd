<?php

namespace Noerd\Commands;

use Exception;

class NoerdUpdateCommand extends NoerdInstallCommand
{
    protected $signature = 'noerd:update {--force : Overwrite existing files without asking} {--build : Run npm build after update}';

    protected $description = 'Update noerd content files without running installation setup';

    public function handle()
    {
        $this->info('Updating noerd content...');

        $sourceDir = dirname(__DIR__, 2) . '/app-configs/setup';
        $targetDir = base_path('app-configs/setup');

        if (!is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");
            return 1;
        }

        // Create target directory if it doesn't exist
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                $this->error("Failed to create target directory: {$targetDir}");
                return 1;
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

            return 0;
        } catch (Exception $e) {
            $this->error('Error updating noerd content: ' . $e->getMessage());
            return 1;
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
