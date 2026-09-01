<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Commands\NoerdInstallCommand;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | noerd:install must be safe under --no-interaction: prompts default to "yes"
 | interactively, but a scripted run (CI, deploy) must NEVER inherit those
 | defaults — migrations, npm build, admin setup and the demo app all require
 | an explicit opt-in flag. Admin credentials cannot be prompted at all, so
 | that step is always skipped with a pointer to noerd:create-admin.
 */

/**
 * Fixture exposing the opt-in installation steps in isolation. Sub-commands
 * are recorded instead of executed, so no migration/demo actually runs.
 */
class InstallNonInteractiveFixtureCommand extends NoerdInstallCommand
{
    /** @var array<int, string> */
    public static array $calledCommands = [];

    protected $signature = 'test:install-non-interactive
                            {--force : Overwrite existing files}
                            {--migrate : Run migrations without asking}
                            {--build : Run npm build without asking}
                            {--demo : Install the demo app without asking}';

    public function handle(): int
    {
        $this->runMigrationsAndSetupAdmin();
        $this->installDemoApp();

        return 0;
    }

    public function call($command, array $arguments = [])
    {
        static::$calledCommands[] = $command;

        return 0;
    }
}

beforeEach(function (): void {
    InstallNonInteractiveFixtureCommand::$calledCommands = [];
    $this->app[Kernel::class]->registerCommand(new InstallNonInteractiveFixtureCommand());
});

it('skips migrations, admin setup and the demo app in a non-interactive run without flags', function (): void {
    $this->artisan('test:install-non-interactive', ['--no-interaction' => true])
        ->expectsOutputToContain('Non-interactive run: skipping migrations. Pass --migrate to run them.')
        ->expectsOutputToContain('Non-interactive run: skipping the demo app. Pass --demo to install it.')
        ->assertExitCode(0);

    expect(InstallNonInteractiveFixtureCommand::$calledCommands)->toBeEmpty();
});

it('runs migrations non-interactively when --migrate is passed and still skips the admin prompts', function (): void {
    // An existing tenant keeps the flow off the tenant-creation branch — this
    // test asserts the migrate opt-in and the admin skip, nothing else.
    Tenant::factory()->create();

    $this->artisan('test:install-non-interactive', ['--migrate' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Non-interactive run: skipping admin setup.')
        ->expectsOutputToContain('Non-interactive run: skipping the demo app. Pass --demo to install it.')
        ->assertExitCode(0);

    expect(InstallNonInteractiveFixtureCommand::$calledCommands)->toContain('migrate')
        ->not->toContain('noerd:demo');
});

it('installs the demo app non-interactively only with the --demo flag', function (): void {
    $this->artisan('test:install-non-interactive', ['--demo' => true, '--no-interaction' => true])
        ->assertExitCode(0);

    expect(InstallNonInteractiveFixtureCommand::$calledCommands)->toContain('noerd:demo');
});

it('still asks the demo question in interactive runs', function (): void {
    $this->artisan('test:install-non-interactive')
        ->expectsConfirmation('Would you like to run "php artisan migrate" now?')
        ->expectsConfirmation('Would you like to install the Demo App?', 'yes')
        ->assertExitCode(0);

    expect(InstallNonInteractiveFixtureCommand::$calledCommands)->toContain('noerd:demo');
});
