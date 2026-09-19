<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Noerd\Commands\Concerns\RunsNpmBuild;
use Noerd\Support\ModuleInstallContext;
use Noerd\Tests\TestCase;
use Noerd\Traits\HasModuleInstallation;
use Noerd\Traits\RequiresNoerdInstallation;

uses(TestCase::class);

/**
 * Installing a module on a fresh project installs the base package on the way.
 * Node must not run in the middle of that: at the point the base installer is
 * done, the module — and whatever it pulls in, e.g. the website boilerplate
 * behind the CMS — is not in the project yet, so the build would compile the
 * wrong thing and ask the same question twice. The base installer hands its npm
 * work to the command the user actually started.
 */
final class NpmRecorder
{
    /** @var array<int, array<int, string>> */
    public static array $installs = [];

    public static int $builds = 0;

    public static function reset(): void
    {
        self::$installs = [];
        self::$builds = 0;
    }
}

/** The real installer's npm steps, with node replaced by a recorder. */
class ZzNpmInstallProbeCommand extends Noerd\Commands\NoerdInstallCommand
{
    protected $signature = 'noerd:install-zz-npm-probe {--build} {--migrate} {--force} {--demo} {--no-demo}';

    public function handle(): int
    {
        $this->installNpmPackages(['zz-tooling']);
        $this->runNpmBuild();

        return self::SUCCESS;
    }

    protected function executeNpmInstall(array $packages): void
    {
        NpmRecorder::$installs[] = $packages;
    }

    protected function executeNpmBuild(): void
    {
        NpmRecorder::$builds++;
    }
}

/** A module install command whose only remaining step is the npm question. */
class ZzNpmModuleCommand extends Command
{
    use HasModuleInstallation;
    use RequiresNoerdInstallation;
    use RunsNpmBuild;

    protected $signature = 'noerd:install-zz-npm-module';

    public function handle(): int
    {
        $this->askForNpmBuild();

        return self::SUCCESS;
    }

    protected function executeNpmInstall(array $packages): void
    {
        NpmRecorder::$installs[] = $packages;
    }

    protected function executeNpmBuild(): void
    {
        NpmRecorder::$builds++;
    }

    protected function getModuleName(): string
    {
        return 'Zz Npm Module';
    }

    protected function getModuleKey(): string
    {
        return 'zz-npm-module';
    }

    protected function getDefaultAppTitle(): string
    {
        return 'Zz Npm Module';
    }

    protected function getAppIcon(): string
    {
        return 'noerd::icons.app';
    }

    protected function getAppRoute(): string
    {
        return 'zz-npm-module';
    }

    protected function getSourceDir(): string
    {
        return base_path('tests-tmp/zz-npm-module/app-configs/zz-npm-module');
    }
}

/** The failure path: the run dies before the handed-over npm work could be done. */
class ZzNpmFailingModuleCommand extends ZzNpmModuleCommand
{
    protected $signature = 'noerd:install-zz-npm-failing';

    public function handle(): int
    {
        $this->warnAboutDeferredNpm();

        return self::FAILURE;
    }
}

beforeEach(function (): void {
    ModuleInstallContext::reset();
    NpmRecorder::reset();

    $kernel = $this->app[Kernel::class];
    $kernel->registerCommand(new ZzNpmInstallProbeCommand());
    $kernel->registerCommand(new ZzNpmModuleCommand());
    $kernel->registerCommand(new ZzNpmFailingModuleCommand());
});

afterEach(function (): void {
    ModuleInstallContext::reset();
});

it('runs npm itself for a base installation the user started', function (): void {
    $this->artisan('noerd:install-zz-npm-probe', ['--build' => true])->assertExitCode(0);

    expect(NpmRecorder::$installs)->toBe([['zz-tooling']])
        ->and(NpmRecorder::$builds)->toBe(1)
        ->and(ModuleInstallContext::hasDeferredNpm())->toBeFalse();
});

it('hands its npm work over while it installs for a module', function (): void {
    ModuleInstallContext::asDependency(function (): void {
        $this->artisan('noerd:install-zz-npm-probe', ['--build' => true])
            ->expectsOutputToContain('runs once the module installation has finished')
            ->assertExitCode(0);
    });

    expect(NpmRecorder::$installs)->toBe([])
        ->and(NpmRecorder::$builds)->toBe(0)
        ->and(ModuleInstallContext::hasDeferredNpm())->toBeTrue();
});

it('installs the handed-over packages and builds once at the end of the module install', function (): void {
    ModuleInstallContext::deferNpm(['zz-tooling'], build: true);

    $this->artisan('noerd:install-zz-npm-module')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'yes')
        ->assertExitCode(0);

    expect(NpmRecorder::$installs)->toBe([['zz-tooling']])
        ->and(NpmRecorder::$builds)->toBe(1)
        ->and(ModuleInstallContext::hasDeferredNpm())->toBeFalse();
});

it('asks about the build only once', function (): void {
    ModuleInstallContext::deferNpm(['zz-tooling'], build: true);

    // Declining is the user's answer for the whole run — the handed-over build
    // never sneaks a second one in.
    $this->artisan('noerd:install-zz-npm-module')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);

    expect(NpmRecorder::$builds)->toBe(0)
        ->and(ModuleInstallContext::hasDeferredNpm())->toBeFalse();
});

it('says what to run by hand when the module install died before npm ran', function (): void {
    ModuleInstallContext::deferNpm(['zz-tooling'], build: true);

    $this->artisan('noerd:install-zz-npm-failing')
        ->expectsOutputToContain('npm install && npm run build')
        ->assertExitCode(1);

    // Nothing is left waiting for a run that will never come.
    expect(NpmRecorder::$installs)->toBe([])
        ->and(NpmRecorder::$builds)->toBe(0)
        ->and(ModuleInstallContext::hasDeferredNpm())->toBeFalse();
});

it('says nothing about npm when none was handed over', function (): void {
    $this->artisan('noerd:install-zz-npm-failing')
        ->doesntExpectOutputToContain('npm install && npm run build')
        ->assertExitCode(1);
});
