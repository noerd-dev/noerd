<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Noerd\Tests\TestCase;
use Noerd\Traits\PublishesAuditMigration;

uses(TestCase::class);

/**
 * Modules publish the auditing migration from publishModuleExtras(), which runs
 * on every update — so the step has to recognise an installation that needs no
 * migration instead of publishing a second "create audits table".
 */
class ZzAuditMigrationCommand extends Command
{
    use PublishesAuditMigration;

    protected $signature = 'test:zz-audit-migration';

    public function handle(): int
    {
        $this->publishAuditingMigrationIfNeeded();

        return self::SUCCESS;
    }
}

beforeEach(function (): void {
    $this->app[Kernel::class]->registerCommand(new ZzAuditMigrationCommand());
    $this->migrationsBefore = File::glob(database_path('migrations/*_create_audits_table.php'));
});

afterEach(function (): void {
    Schema::dropIfExists('audits');

    foreach (array_diff(File::glob(database_path('migrations/*_create_audits_table.php')), $this->migrationsBefore) as $file) {
        File::delete($file);
    }
});

it('publishes nothing when the audits table exists without a migration file', function (): void {
    // A project that squashed its migrations into a schema dump: the table is
    // there, the file is gone. A second "create" migration would break migrate.
    Schema::create('audits', function (Blueprint $table): void {
        $table->id();
    });

    $this->artisan('test:zz-audit-migration')
        ->expectsOutputToContain('The audits table already exists')
        ->assertExitCode(0);

    expect(File::glob(database_path('migrations/*_create_audits_table.php')))->toBe($this->migrationsBefore);
});

it('publishes nothing when the migration file is already there', function (): void {
    $file = database_path('migrations/2000_01_01_000000_create_audits_table.php');
    File::ensureDirectoryExists(dirname($file));
    File::put($file, "<?php\n");

    try {
        $this->artisan('test:zz-audit-migration')
            ->expectsOutputToContain('Auditing migration already published.')
            ->assertExitCode(0);
    } finally {
        File::delete($file);
    }
});
