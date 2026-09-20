<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use Exception;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The single recursive config-publishing implementation shared by
 * noerd:install/noerd:update and the module install commands — previously two
 * divergent ~65-line copies. Existing files prompt skip/overwrite/overwrite-all
 * (or are overwritten under --force). Overwrites leave no `.bak` behind: the
 * published configs live in the host repository, so version control is the
 * change history.
 */
trait PublishesConfigDirectory
{
    /**
     * @param  string|null  $displayBase  base path stripped from printed paths;
     *                                    defaults to the source directory
     * @param  bool  $quiet  print no per-file line — the caller reports the counts
     *                       instead. Prompts are never suppressed.
     * @return array{created_dirs: int, copied_files: int, skipped_files: int, overwritten_files: int}
     */
    protected function publishConfigDirectory(string $sourceDir, string $targetDir, ?string $displayBase = null, bool $quiet = false): array
    {
        $results = [
            'created_dirs' => 0,
            'copied_files' => 0,
            'skipped_files' => 0,
            'overwritten_files' => 0,
        ];

        $display = function (string $path) use ($targetDir, $displayBase): string {
            if ($displayBase !== null) {
                return str_replace($displayBase . DIRECTORY_SEPARATOR, '', $path);
            }

            return mb_substr($path, mb_strlen($targetDir) + 1) ?: $path;
        };

        if (! File::isDirectory($targetDir)) {
            if (! File::makeDirectory($targetDir, 0755, true)) {
                throw new Exception("Failed to create directory: {$targetDir}");
            }
            if (! $quiet) {
                $this->line('<info>Created directory:</info> ' . $display($targetDir));
            }
            $results['created_dirs']++;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = mb_substr($sourcePath, mb_strlen($sourceDir) + 1);
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;
            $displayPath = $display($targetPath);

            if ($item->isDir()) {
                if (! File::isDirectory($targetPath)) {
                    if (! File::makeDirectory($targetPath, 0755, true)) {
                        throw new Exception("Failed to create directory: {$targetPath}");
                    }
                    if (! $quiet) {
                        $this->line("<info>Created directory:</info> {$displayPath}");
                    }
                    $results['created_dirs']++;
                }

                continue;
            }

            $results[$this->publishFile($sourcePath, $targetPath, $displayPath, $quiet)]++;
        }

        return $results;
    }

    /**
     * Publish ONE file: an existing target prompts skip/overwrite/overwrite-all
     * (or is overwritten under --force). Returns the counter the outcome belongs
     * to, so callers keep their summary without repeating the decision tree.
     *
     * $quiet drops the per-file line only — the prompt still renders, or an
     * interactive run would wait for an answer to a question nobody sees.
     *
     * @return 'copied_files'|'skipped_files'|'overwritten_files'
     */
    protected function publishFile(string $sourcePath, string $targetPath, string $displayPath, bool $quiet = false): string
    {
        $outcome = 'copied_files';

        if (File::exists($targetPath)) {
            if (! $this->option('force')) {
                $choice = $this->choice(
                    "File already exists: {$displayPath}. What do you want to do?",
                    ['skip', 'overwrite', 'overwrite-all'],
                    'skip',
                );

                if ($choice === 'skip') {
                    if (! $quiet) {
                        $this->line("<comment>Skipped:</comment> {$displayPath}");
                    }

                    return 'skipped_files';
                }

                if ($choice === 'overwrite-all') {
                    // Set force option for remaining files
                    $this->input->setOption('force', true);
                }
            }

            if (! $quiet) {
                $this->line("<comment>Overwriting:</comment> {$displayPath}");
            }
            $outcome = 'overwritten_files';
        } elseif (! $quiet) {
            $this->line("<info>Copying:</info> {$displayPath}");
        }

        File::ensureDirectoryExists(dirname($targetPath));

        if (! File::copy($sourcePath, $targetPath)) {
            throw new Exception("Failed to copy file: {$sourcePath} to {$targetPath}");
        }

        return $outcome;
    }

    /**
     * @param  array{created_dirs: int, copied_files: int, skipped_files: int, overwritten_files: int}  $results
     */
    protected function displayPublishSummary(array $results): void
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
