<?php

namespace Noerd\Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class NoerdInstallCommand extends Command
{
    protected $signature = 'noerd:install {--force : Overwrite existing files without asking}';

    protected $description = 'Install noerd content to the local content directory';

    public function handle()
    {
        $sourceFileName = 'noerd.css';

        // Define paths
        $sourceFile = __DIR__ . '/../../dist/' . $sourceFileName;
        $targetDir = public_path('css');
        $targetFile = $targetDir . '/noerd.css';

        // Check if source file exists
        if (!File::exists($sourceFile)) {
            $this->error("Source file not found: {$sourceFile}");
            $this->info('Please run "npm run build-css" in the noerd module directory first.');
            return self::FAILURE;
        }

        // Create target directory if it doesn't exist
        if (!File::exists($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
            $this->info("Created directory: {$targetDir}");
        }

        // Check if target file exists and handle accordingly
        if (File::exists($targetFile) && !$this->option('force')) {
            if (!$this->confirm("File {$targetFile} already exists. Overwrite?")) {
                $this->info('Installation cancelled.');
                return self::SUCCESS;
            }
        }

        // Copy the file
        try {
            File::copy($sourceFile, $targetFile);

            $this->line("   Source: {$sourceFile}");
            $this->line("   Target: {$targetFile}");

            // Show usage instructions
            $this->newLine();
            $this->info('📝 Usage in your Blade templates:');
            $this->line('<link rel="stylesheet" href="{{ asset(\'css/noerd.css\') }}">');

            $this->newLine();
            $this->info('🎨 Available CSS classes: noerd-input, noerd-button-primary, noerd-nav-link, etc.');
            $this->info('📖 Documentation: app-modules/noerd/README.md');

        } catch (Exception $e) {
            $this->error("Failed to copy file: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->info('Installing noerd content...');

        $sourceDir = base_path('vendor/noerd/noerd/content');
        $targetDir = base_path('content');

        if (!is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");
            return 1;
        }

        // Create target directory if it doesn't exist
        if (!is_dir($targetDir)) {
            $this->info("Created target directory: {$targetDir}");
        }

        try {
            $results = $this->copyDirectoryContents($sourceDir, $targetDir);

            $this->displaySummary($results);

            return 0;
        } catch (Exception $e) {
            $this->error('Error installing noerd content: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Copy directory contents recursively
     */
    private function copyDirectoryContents(string $sourceDir, string $targetDir): array
    {
        $results = [
            'created_dirs' => 0,
            'copied_files' => 0,
            'skipped_files' => 0,
            'overwritten_files' => 0,
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = mb_substr($sourcePath, mb_strlen($sourceDir) + 1);
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                // Create directory if it doesn't exist
                if (!is_dir($targetPath)) {
                    $this->line("<info>Created directory:</info> {$relativePath}");
                    $results['created_dirs']++;
                }
            } else {
                // Check if file already exists
                if (file_exists($targetPath)) {
                    if (!$this->option('force')) {

                        $choice = $this->choice(
                            "File already exists: {$relativePath}. What do you want to do?",
                            ['skip', 'overwrite', 'overwrite-all'],
                            'skip',
                        );

                        if ($choice === 'skip') {
                            $this->line("<comment>Skipped:</comment> {$relativePath}");
                            $results['skipped_files']++;
                            continue;
                        }
                        if ($choice === 'overwrite-all') {
                            // Set force option for remaining files
                            $this->input->setOption('force', true);
                        }
                    }

                    $this->line("<comment>Overwriting:</comment> {$relativePath}");
                    $results['overwritten_files']++;
                } else {
                    $this->line("<info>Copying:</info> {$relativePath}");
                    $results['copied_files']++;
                }

            }
        }

        return $results;
    }

    /**
     * Display summary of operations
     */
    private function displaySummary(array $results): void
    {
        $this->line('');
        $this->info('Installation Summary:');
        $this->table(
            ['Operation', 'Count'],
            [
                ['Directories created', $results['created_dirs']],
                ['Files copied', $results['copied_files']],
                ['Files overwritten', $results['overwritten_files']],
                ['Files skipped', $results['skipped_files']],
            ],
        );
    }
}
