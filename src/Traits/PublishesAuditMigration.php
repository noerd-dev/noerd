<?php

namespace Noerd\Traits;

use Exception;
use Illuminate\Support\Facades\Artisan;

trait PublishesAuditMigration
{
    protected function publishAuditingMigrationIfNeeded(): void
    {
        $migrationsPath = database_path('migrations');
        $existingMigrations = glob($migrationsPath . '/*_create_audits_table.php');

        if (! empty($existingMigrations)) {
            $this->line('<comment>Auditing migration already published.</comment>');

            return;
        }

        $this->line('');
        $this->info('Publishing auditing migration...');

        try {
            $exitCode = Artisan::call('vendor:publish', [
                '--provider' => 'OwenIt\Auditing\AuditingServiceProvider',
                '--tag' => 'migrations',
            ], $this->output);

            if ($exitCode === 0) {
                $this->line('<info>Auditing migration published successfully.</info>');
            }
        } catch (Exception $e) {
            $this->warn('Failed to publish auditing migration: ' . $e->getMessage());
        }
    }
}
