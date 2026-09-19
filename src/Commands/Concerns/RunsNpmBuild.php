<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use Illuminate\Support\Facades\Process;
use Noerd\Support\ModuleInstallContext;

/**
 * Run npm in the project root, streaming output to the terminal.
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

    /**
     * Install the npm packages the scaffolder added to package.json
     *
     * @param  array<int, string>  $packages
     */
    protected function installNpmPackages(array $packages): void
    {
        if ($packages === []) {
            $this->line('<comment>All required npm packages are already declared.</comment>');

            return;
        }

        // Installing for a module: node belongs at the end of THAT run, once the
        // module's files — and whatever it pulls in, e.g. the website boilerplate
        // behind the CMS — are in the project.
        if (ModuleInstallContext::isDependencyInstall()) {
            ModuleInstallContext::deferNpm($packages);
            $this->line('<comment>npm runs once the module installation has finished.</comment>');

            return;
        }

        $this->executeNpmInstall($packages);
    }

    /**
     * Run `npm install ... --save-dev` in the project root.
     *
     * @param  array<int, string>  $packages
     */
    protected function executeNpmInstall(array $packages): void
    {
        $this->line('<comment>Installing npm packages...</comment>');

        // Process handles the working directory and argument escaping — the
        // previous string-built `cd <path> && npm install ...` broke on paths
        // with spaces and discarded npm's diagnostics on failure.
        $result = Process::path(base_path())
            ->timeout(300)
            ->run(array_merge(['npm', 'install'], $packages, ['--save-dev']));

        if ($result->failed()) {
            $this->warn('Failed to install npm packages:');
            $this->warn(mb_trim($result->errorOutput() ?: $result->output()));
            $this->warn('You may need to run the following manually:');
            $this->warn('npm install ' . implode(' ', $packages) . ' --save-dev');
        } else {
            $this->line('<info>NPM packages installed successfully.</info>');
        }
    }

    /**
     * Install whatever a nested base installation handed over: its npm work runs
     * here, at the end of the installation the user actually started, so node
     * sees the module's files — and the modules it pulled in — as well.
     */
    protected function installDeferredNpmPackages(): void
    {
        $packages = ModuleInstallContext::takeDeferredNpmPackages();

        if ($packages === []) {
            return;
        }

        $this->line('');
        $this->installNpmPackages($packages);
    }
}
