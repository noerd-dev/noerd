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

    public static int $demoCalls = 0;
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
                            {--demo : Install the demo app without asking}
                            {--no-demo : Never install the demo app and do not ask for it}';

    public function handle(): int
    {
        BaseInstallRecorder::$calls++;
        BaseInstallRecorder::$options = [
            'force' => (bool) $this->option('force'),
            'migrate' => (bool) $this->option('migrate'),
            'demo' => (bool) $this->option('demo'),
            'no-demo' => (bool) $this->option('no-demo'),
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

/** A module install command that declares --demo itself. */
class ZzBaseFixtureDemoInstallCommand extends ZzBaseFixtureInstallCommand
{
    protected $signature = 'noerd:install-zz-demo-fixture {--force : Overwrite existing files without asking} {--demo : Install the demo app}';
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
    $kernel->registerCommand(new ZzBaseFixtureDemoInstallCommand());
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

    expect(BaseInstallRecorder::$options['force'])->toBeTrue()
        ->and(BaseInstallRecorder::$options['migrate'])->toBeFalse();
});

it('skips the demo app on an implicit base installation', function (): void {
    $this->artisan('noerd:install-zz-base-fixture', ['--no-interaction' => true])
        ->assertExitCode(0);

    expect(BaseInstallRecorder::$options['no-demo'])->toBeTrue()
        ->and(BaseInstallRecorder::$options['demo'])->toBeFalse();
});

it('asks for the demo app when the module command was given --demo', function (): void {
    $this->artisan('noerd:install-zz-demo-fixture', ['--demo' => true, '--no-interaction' => true])
        ->assertExitCode(0);

    expect(BaseInstallRecorder::$options['demo'])->toBeTrue()
        ->and(BaseInstallRecorder::$options['no-demo'])->toBeFalse();
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

/**
 * The real installer, cut down to the demo step: everything before it writes
 * into the host tree, and only the demo decision is under test here.
 */
class ZzDemoStepProbeCommand extends Noerd\Commands\NoerdInstallCommand
{
    protected $signature = 'noerd:install-zz-demo-probe
                            {--force : Overwrite existing files without asking}
                            {--migrate : Run migrations without asking}
                            {--demo : Install the demo app without asking}
                            {--no-demo : Never install the demo app and do not ask for it}';

    public function handle(): int
    {
        $this->installDemoApp();

        return self::SUCCESS;
    }
}

/** Stand-in for noerd:demo, which would otherwise publish into the host tree. */
class ZzFakeNoerdDemoCommand extends Command
{
    protected $signature = 'noerd:demo {--force} {--migrate} {--seed}';

    public function handle(): int
    {
        BaseInstallRecorder::$demoCalls++;

        return self::SUCCESS;
    }
}

describe('--no-demo', function (): void {
    beforeEach(function (): void {
        BaseInstallRecorder::$demoCalls = 0;

        $kernel = $this->app[Kernel::class];
        $kernel->registerCommand(new ZzDemoStepProbeCommand());
        $kernel->registerCommand(new ZzFakeNoerdDemoCommand());
    });

    it('skips the demo question and the demo install', function (): void {
        $this->artisan('noerd:install-zz-demo-probe', ['--no-demo' => true])
            ->doesntExpectOutputToContain('Demo App')
            ->assertExitCode(0);

        expect(BaseInstallRecorder::$demoCalls)->toBe(0);
    });

    it('wins over --demo', function (): void {
        $this->artisan('noerd:install-zz-demo-probe', ['--no-demo' => true, '--demo' => true])
            ->assertExitCode(0);

        expect(BaseInstallRecorder::$demoCalls)->toBe(0);
    });

    it('installs the demo app when only --demo is given', function (): void {
        $this->artisan('noerd:install-zz-demo-probe', ['--demo' => true])
            ->assertExitCode(0);

        expect(BaseInstallRecorder::$demoCalls)->toBe(1);
    });
});

/**
 * The real installer, cut down to its closing callout: the box says the whole
 * application is ready, which is wrong in the middle of a module installation.
 */
class ZzReadyCalloutProbeCommand extends Noerd\Commands\NoerdInstallCommand
{
    protected $signature = 'noerd:install-zz-ready-probe';

    public function handle(): int
    {
        $this->displayApplicationReady();

        return self::SUCCESS;
    }
}

describe('Application ready callout', function (): void {
    beforeEach(function (): void {
        $this->app[Kernel::class]->registerCommand(new ZzReadyCalloutProbeCommand());
    });

    it('closes a base installation the user started', function (): void {
        $this->artisan('noerd:install-zz-ready-probe')
            ->expectsOutputToContain('Application ready')
            ->assertExitCode(0);
    });

    it('is suppressed while the base is installed for a module install', function (): void {
        Noerd\Support\ModuleInstallContext::asDependency(function (): void {
            $this->artisan('noerd:install-zz-ready-probe')
                ->doesntExpectOutputToContain('Application ready')
                ->assertExitCode(0);
        });
    });
});
