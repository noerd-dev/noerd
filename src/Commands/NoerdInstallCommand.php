<?php

namespace Noerd\Noerd\Commands;

use Illuminate\Console\Command;

class NoerdInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noerd:install-content {--force : Overwrite existing files without asking} {--dry-run : Show what would be copied without actually copying}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install noerd content to the local content directory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No files will actually be copied');
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
            if (!$this->option('dry-run')) {
                if (!mkdir($targetDir, 0755, true)) {
                    $this->error("Failed to create target directory: {$targetDir}");
                    return 1;
                }
            }
            $this->info("Created target directory: {$targetDir}");
        }

        try {
            $results = $this->copyDirectoryContents($sourceDir, $targetDir);

            $this->displaySummary($results);

            if (!$this->option('dry-run')) {
                $this->info('Noerd content successfully installed!');
            }

            return 0;
        } catch (\Exception $e) {
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

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = substr($sourcePath, strlen($sourceDir) + 1);
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                // Create directory if it doesn't exist
                if (!is_dir($targetPath)) {
                    if (!$this->option('dry-run')) {
                        if (!mkdir($targetPath, 0755, true)) {
                            throw new \Exception("Failed to create directory: {$targetPath}");
                        }
                    }
                    $this->line("<info>Created directory:</info> {$relativePath}");
                    $results['created_dirs']++;
                }
            } else {
                // Check if file already exists
                if (file_exists($targetPath)) {
                    if (!$this->option('force')) {
                        if ($this->option('dry-run')) {
                            $this->line("<comment>Would ask about:</comment> {$relativePath} (already exists)");
                            $results['skipped_files']++;
                            continue;
                        }

                        $choice = $this->choice(
                            "File already exists: {$relativePath}. What do you want to do?",
                            ['skip', 'overwrite', 'overwrite-all'],
                            'skip'
                        );

                        if ($choice === 'skip') {
                            $this->line("<comment>Skipped:</comment> {$relativePath}");
                            $results['skipped_files']++;
                            continue;
                        } elseif ($choice === 'overwrite-all') {
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

                // Copy the file (unless dry-run)
                if (!$this->option('dry-run')) {
                    if (!copy($sourcePath, $targetPath)) {
                        throw new \Exception("Failed to copy file: {$sourcePath} to {$targetPath}");
                    }
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
            ]
        );
    }
}
