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

        $sourceDir = dirname(__DIR__, 2) . '/app-contents/setup';
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

            // 3. Setup frontend assets
            $this->setupFrontendAssets();

            // 4. Optional: npm build (only if --build flag is set)
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
        $this->line('Running npm run build...');
        $this->newLine();

        $process = proc_open(
            'npm run build',
            [
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR,
            ],
            $pipes,
            base_path(),
        );

        if (is_resource($process)) {
            $exitCode = proc_close($process);

            $this->newLine();
            if ($exitCode === 0) {
                $this->info('Frontend assets compiled successfully!');
            } else {
                $this->warn('npm run build finished with errors. You may need to run it manually.');
            }
        } else {
            $this->warn('Could not execute npm run build. Please run it manually.');
        }
    }
}
