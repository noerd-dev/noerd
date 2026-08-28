<?php

namespace Noerd\Commands\Concerns;

/**
 * Run `npm run build` in the project root, streaming output to the terminal.
 * Shared by noerd:install, noerd:update and the module install commands —
 * previously three byte-identical proc_open blocks.
 */
trait RunsNpmBuild
{
    protected function executeNpmBuild(): void
    {
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

        if (!is_resource($process)) {
            $this->warn('Could not execute npm run build. Please run it manually.');

            return;
        }

        $exitCode = proc_close($process);

        $this->newLine();
        if ($exitCode === 0) {
            $this->info('Frontend assets compiled successfully!');
        } else {
            $this->warn('npm run build finished with errors. You may need to run it manually.');
        }
    }
}
