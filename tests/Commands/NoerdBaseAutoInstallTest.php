<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Noerd\Tests\TestCase;
use Noerd\Traits\RequiresNoerdInstallation;

uses(TestCase::class);

/**
 * Records what the auto-installation did, so the mechanics can be asserted
 * without publishing anything into the host tree.
 */
final class BaseInstallRecorder
{
    public static int $calls = 0;

    public static bool $installed = false;

    /** @var array<string, bool> */
    public static array $options = [];

    /** Whether the fake noerd:install actually leaves the base installed. */
    public static bool $succeeds = true;
}

/**
 * Stand-in for noerd:install — it carries the same options the real command
 * declares, so a regression that forwards an unknown option makes the input
 * throw and fails the test on its own.
 */
class ZzFakeNoerdInstallCommand extends Command
{
    protected $signature = 'noerd:install
                            {--force : Overwrite existing files without asking}
                            {--migrate : Run migrations without asking}
                            {--build : Run npm build without asking}
                            {--demo : Install the demo app without asking}';

    public function handle(): int
    {
        BaseInstallRecorder::$calls++;
        BaseInstallRecorder::$options = [
            'force' => (bool) $this->option('force'),
            'migrate' => (bool) $this->option('migrate'),
        ];
        BaseInstallRecorder::$installed = BaseInstallRecorder::$succeeds;

        return self::SUCCESS;
    }
}

/** A module install command: the entry point that may install the base itself. */
class ZzBaseFixtureInstallCommand extends Command
{
    use RequiresNoerdInstallation;

    protected $signature = 'noerd:install-zz-base-fixture {--force : Overwrite existing files without asking}';

    public function handle(): int
    {
        if (! $this->ensureNoerdInstalled()) {
            return self::FAILURE;
        }

        $this->info('module installed');

        return self::SUCCESS;
    }

    protected function isNoerdInstalled(): bool
    {
        return BaseInstallRecorder::$installed;
    }
}

/** A module update command: it must keep asking for noerd:install. */
class ZzBaseFixtureUpdateCommand extends ZzBaseFixtureInstallCommand
{
    protected $signature = 'noerd:update-zz-base-fixture {--force : Overwrite existing files without asking}';
}

beforeEach(function (): void {
    BaseInstallRecorder::$calls = 0;
    BaseInstallRecorder::$installed = false;
    BaseInstallRecorder::$options = [];
    BaseInstallRecorder::$succeeds = true;

    $kernel = $this->app[Kernel::class];

    // registerCommand() replaces a same-named entry — mandatory for noerd:install,
    // which would otherwise really publish into the host and run migrations.
    $kernel->registerCommand(new ZzFakeNoerdInstallCommand());
    $kernel->registerCommand(new ZzBaseFixtureInstallCommand());
    $kernel->registerCommand(new ZzBaseFixtureUpdateCommand());
});

it('installs the noerd base package on the fly and continues with the module install', function (): void {
    $this->artisan('noerd:install-zz-base-fixture', ['--no-interaction' => true])
        ->expectsOutputToContain('running "php artisan noerd:install" first')
        ->expectsOutputToContain('module installed')
        ->assertExitCode(0);

    expect(BaseInstallRecorder::$calls)->toBe(1);
});

it('does not run noerd:install when the base package is already installed', function (): void {
    BaseInstallRecorder::$installed = true;

    $this->artisan('noerd:install-zz-base-fixture', ['--no-interaction' => true])
        ->assertExitCode(0);

    expect(BaseInstallRecorder::$calls)->toBe(0);
});

it('forwards the options it shares with noerd:install', function (): void {
    $this->artisan('noerd:install-zz-base-fixture', ['--force' => true, '--no-interaction' => true])
        ->assertExitCode(0);

    expect(BaseInstallRecorder::$options)->toBe(['force' => true, 'migrate' => false]);
});

it('fails with the manual instruction when the base installation did not complete', function (): void {
    BaseInstallRecorder::$succeeds = false;

    $this->artisan('noerd:install-zz-base-fixture', ['--no-interaction' => true])
        ->expectsOutputToContain('The noerd base installation did not complete.')
        ->expectsOutputToContain('php artisan noerd:install')
        ->assertExitCode(1);

    expect(BaseInstallRecorder::$calls)->toBe(1);
});

it('keeps asking for noerd:install on a command that is not an install command', function (): void {
    $this->artisan('noerd:update-zz-base-fixture', ['--no-interaction' => true])
        ->expectsOutputToContain('Noerd base package has not been installed yet.')
        ->assertExitCode(1);

    expect(BaseInstallRecorder::$calls)->toBe(0);
});
