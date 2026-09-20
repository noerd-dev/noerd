<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Illuminate\Console\Command;
use Noerd\Support\ModuleInstallContext;

trait RequiresNoerdInstallation
{
    /**
     * Ensure the noerd base package is installed.
     *
     * An INSTALL command is a legitimate entry point into a fresh project, so
     * `noerd:install-{module}` runs `noerd:install` for the user instead of
     * dead-ending with an instruction to run it by hand. Every other command
     * (update, scaffold, demo) keeps asking for it — see shouldAutoInstallNoerd().
     *
     * @param  bool|null  $autoInstall  null = decide from the command name
     */
    protected function ensureNoerdInstalled(?bool $autoInstall = null): bool
    {
        if ($this->isNoerdInstalled()) {
            return true;
        }

        if ($autoInstall ?? $this->shouldAutoInstallNoerd()) {
            return $this->installNoerdBase();
        }

        $this->line('');
        $this->error('Noerd base package has not been installed yet.');
        $this->line('');
        $this->info('Please run the following command first:');
        $this->line('  php artisan noerd:install');
        $this->line('');

        return false;
    }

    /**
     * Only an install command installs the base package on the fly: an update,
     * a scaffold run or the demo installer is never the first command a fresh
     * project is expected to run.
     */
    protected function shouldAutoInstallNoerd(): bool
    {
        return str_starts_with((string) $this->getName(), 'noerd:install');
    }

    /**
     * Run noerd:install and report whether the base package is installed afterwards.
     */
    protected function installNoerdBase(): bool
    {
        $this->line('');
        $this->info('Noerd base package has not been installed yet — running "php artisan noerd:install" first.');
        $this->line('');

        // As a dependency: the base installer leaves out everything that belongs to
        // a run the user started himself — the demo app question, the frontend
        // build (handed over to this command) and its closing "Application ready"
        // callout, so the run ends with the module's own one.
        $exitCode = (int) ModuleInstallContext::asDependency(
            fn(): int => $this->call('noerd:install', $this->noerdInstallOptions()),
        );

        if ($exitCode !== Command::SUCCESS || ! $this->isNoerdInstalled()) {
            $this->line('');
            $this->error('The noerd base installation did not complete.');
            $this->line('');
            $this->info('Please run the following command and try again:');
            $this->line('  php artisan noerd:install');
            $this->line('');

            return false;
        }

        $this->line('');
        $this->info('Noerd base package installed. Continuing...');
        $this->line('');

        return true;
    }

    /**
     * Options the caller shares with noerd:install, so an automatic base
     * installation follows the same choices the module command was given.
     * --no-interaction, --quiet and the verbosity flags are forwarded by
     * Command::call() itself.
     *
     * @return array<string, bool>
     */
    protected function noerdInstallOptions(): array
    {
        $options = [];

        foreach (['force', 'migrate', 'build', 'demo'] as $option) {
            if ($this->getDefinition()->hasOption($option) && (bool) $this->option($option)) {
                $options['--' . $option] = true;
            }
        }

        return $options;
    }

    /**
     * Check if Noerd is installed by checking for config/noerd.php
     */
    protected function isNoerdInstalled(): bool
    {
        return file_exists(base_path('config/noerd.php'));
    }
}
