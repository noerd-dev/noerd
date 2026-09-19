<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Noerd\Commands\Concerns\GuardsPublishTargets;
use Throwable;

trait PublishesAuditMigration
{
    use GuardsPublishTargets;

    protected function publishAuditingMigrationIfNeeded(): void
    {
        $migrationsPath = database_path('migrations');
        $existingMigrations = glob($migrationsPath . '/*_create_audits_table.php');

        if (! empty($existingMigrations)) {
            $this->line('<comment>Auditing migration already published.</comment>');

            return;
        }

        // A project that squashed its migrations into a schema dump has the table
        // but no migration file any more — publishing a second "create" migration
        // would break its next `php artisan migrate`.
        if ($this->auditsTableExists()) {
            $this->line('<comment>The audits table already exists, no auditing migration needed.</comment>');

            return;
        }

        // vendor:publish does not follow a moved base path (a test fixture, a build
        // tool) — it would write the migration into the real installation.
        if (! $this->publishTargetsCurrentInstallation('migrations', 'OwenIt\\Auditing\\AuditingServiceProvider')) {
            $this->line('<comment>Skipping the auditing migration: it would be published outside this installation.</comment>');

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

    /**
     * False when the database cannot be asked (not configured yet) — publishing
     * the migration is then the safe default.
     */
    private function auditsTableExists(): bool
    {
        try {
            return Schema::hasTable((string) config('audit.drivers.database.table', 'audits'));
        } catch (Throwable) {
            return false;
        }
    }
}
