<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use Illuminate\Support\Facades\Process;

/**
 * Run `npm run build` in the project root, streaming output to the terminal.
 * Shared by noerd:install, noerd:update and the module install commands.
 */
trait RunsNpmBuild
{
    protected function executeNpmBuild(): void
    {
        $this->line('Running npm run build...');
        $this->newLine();

        $result = Process::path(base_path())
            ->timeout(600)
            ->tty(false)
            ->run('npm run build', function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });

        $this->newLine();
        if ($result->successful()) {
            $this->info('Frontend assets compiled successfully!');
        } else {
            $this->warn('npm run build finished with errors. You may need to run it manually.');
        }
    }
}
