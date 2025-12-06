<?php

namespace Noerd\Noerd\Traits;

use Exception;
use Illuminate\Support\Facades\Artisan;

trait RequiresNoerdInstallation
{
    /**
     * Check if noerd:install has been run, and run it if not
     */
    protected function ensureNoerdInstalled(): bool
    {
        if ($this->isNoerdInstalled()) {
            $this->line('<comment>Noerd base package already installed.</comment>');

            return true;
        }

        $this->line('');
        $this->warn('Noerd base package has not been installed yet.');
        $this->info('Running noerd:install first...');
        $this->line('');

        try {
            $options = $this->option('force') ? ['--force' => true] : [];
            $exitCode = Artisan::call('noerd:install', $options, $this->output);

            if ($exitCode === 0) {
                $this->line('');
                $this->info('Noerd base package installed successfully.');
                $this->line('');

                return true;
            }

            $this->error('Failed to install noerd base package.');

            return false;
        } catch (Exception $e) {
            $this->error('Failed to run noerd:install: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check if Noerd is installed by checking for config/noerd.php
     */
    protected function isNoerdInstalled(): bool
    {
        return file_exists(base_path('config/noerd.php'));
    }
}
