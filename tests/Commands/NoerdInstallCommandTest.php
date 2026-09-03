<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Noerd\Commands\NoerdInstallCommand;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 | noerd:install runs a chain of side-effecting steps. Every step is exercised
 | in isolation through ONE fixture command: --step runs the named steps only,
 | no --step runs the real handle() with every step neutralised (so the ORDER
 | of the flow can be asserted without touching the filesystem, the database
 | or npm). Sub-commands are always recorded instead of executed.
 */
class ZzInstallFixtureCommand extends NoerdInstallCommand
{
    /** @var array<int, array{command: string, arguments: array<string, mixed>}> */
    public static array $calledCommands = [];

    /** @var array<int, string> */
    public static array $steps = [];

    /** Neutralise every installation step and only record its name. */
    public static bool $recordStepsOnly = false;

    protected $signature = 'test:noerd-install
                            {--step=* : Installation steps to run in isolation}
                            {--force : Overwrite existing files}
                            {--migrate : Run migrations without asking}
                            {--build : Run npm build without asking}
                            {--demo : Install the demo app without asking}';

    public function handle(): int
    {
        $steps = (array) $this->option('step');

        if ($steps === []) {
            return parent::handle();
        }

        foreach ($steps as $step) {
            $this->{$step}();
        }

        return 0;
    }

    public function call($command, array $arguments = [])
    {
        static::$calledCommands[] = ['command' => $command, 'arguments' => $arguments];

        return 0;
    }

    protected function copyDirectoryContents(string $sourceDir, string $targetDir): array
    {
        if (! static::$recordStepsOnly) {
            return parent::copyDirectoryContents($sourceDir, $targetDir);
        }

        static::$steps[] = 'copyDirectoryContents';

        return [
            'created_dirs' => 0,
            'copied_files' => 0,
            'skipped_files' => 0,
            'overwritten_files' => 0,
        ];
    }

    protected function displaySummary(array $results): void
    {
        if (! static::$recordStepsOnly) {
            parent::displaySummary($results);
        }
    }

    protected function ensureAppModulesDirectory(): void
    {
        $this->step('ensureAppModulesDirectory', fn() => parent::ensureAppModulesDirectory());
    }

    protected function updatePhpunitXml(): void
    {
        $this->step('updatePhpunitXml', fn() => parent::updatePhpunitXml());
    }

    protected function publishNoerdConfig(): void
    {
        $this->step('publishNoerdConfig', fn() => parent::publishNoerdConfig());
    }

    protected function setupFrontendAssets(): void
    {
        $this->step('setupFrontendAssets', fn() => parent::setupFrontendAssets());
    }

    protected function publishNoerdAssets(): void
    {
        $this->step('publishNoerdAssets', fn() => parent::publishNoerdAssets());
    }

    protected function runMigrationsAndSetupAdmin(): void
    {
        $this->step('runMigrationsAndSetupAdmin', fn() => parent::runMigrationsAndSetupAdmin());
    }

    protected function runNpmBuild(): void
    {
        $this->step('runNpmBuild', fn() => parent::runNpmBuild());
    }

    protected function installDemoApp(): void
    {
        $this->step('installDemoApp', fn() => parent::installDemoApp());
    }

    protected function displayApplicationReady(): void
    {
        $this->step('displayApplicationReady', fn() => parent::displayApplicationReady());
    }

    private function step(string $name, callable $original): void
    {
        if (static::$recordStepsOnly) {
            static::$steps[] = $name;

            return;
        }

        $original();
    }
}

beforeEach(function (): void {
    ZzInstallFixtureCommand::$calledCommands = [];
    ZzInstallFixtureCommand::$steps = [];
    ZzInstallFixtureCommand::$recordStepsOnly = false;

    $this->app[Kernel::class]->registerCommand(new ZzInstallFixtureCommand());

    $this->calledCommandNames = fn(): array => array_column(ZzInstallFixtureCommand::$calledCommands, 'command');
});

describe('demo prompt', function (): void {
    it('runs noerd:demo when the demo app prompt is confirmed', function (): void {
        $this->artisan('test:noerd-install', ['--step' => ['installDemoApp'], '--force' => true])
            ->expectsConfirmation('Would you like to install the Demo App?', 'yes')
            ->assertExitCode(0);

        expect(ZzInstallFixtureCommand::$calledCommands)
            ->toHaveCount(1)
            ->and(ZzInstallFixtureCommand::$calledCommands[0]['command'])->toBe('noerd:demo')
            ->and(ZzInstallFixtureCommand::$calledCommands[0]['arguments'])->toBe(['--force' => true, '--migrate' => false, '--seed' => false]);
    });

    it('skips noerd:demo when the demo app prompt is declined', function (): void {
        $this->artisan('test:noerd-install', ['--step' => ['installDemoApp']])
            ->expectsConfirmation('Would you like to install the Demo App?')
            ->expectsOutputToContain('Demo app will NOT be installed. You can run it later with: php artisan noerd:demo')
            ->assertExitCode(0);

        expect(ZzInstallFixtureCommand::$calledCommands)->toBeEmpty();
    });

    it('offers the demo app as the last installation step', function (): void {
        ZzInstallFixtureCommand::$recordStepsOnly = true;

        $this->artisan('test:noerd-install')->assertExitCode(0);

        $steps = ZzInstallFixtureCommand::$steps;

        expect($steps)->toContain('installDemoApp');

        // The demo question used to be the very first prompt of noerd:install, which
        // made users decide before seeing anything. It must stay at the end of the
        // flow — behind every other step that asks something.
        $demoStep = array_search('installDemoApp', $steps, true);

        foreach (['publishNoerdConfig', 'setupFrontendAssets', 'runMigrationsAndSetupAdmin', 'runNpmBuild'] as $earlierStep) {
            $earlierStepIndex = array_search($earlierStep, $steps, true);

            expect($earlierStepIndex)->not->toBeFalse("Step {$earlierStep} did not run")
                ->and($demoStep)->toBeGreaterThan($earlierStepIndex, "The demo prompt must come after {$earlierStep}");
        }
    });
});

/*
 | noerd:install must be safe under --no-interaction: prompts default to "yes"
 | interactively, but a scripted run (CI, deploy) must NEVER inherit those
 | defaults — migrations, npm build, admin setup and the demo app all require
 | an explicit opt-in flag. Admin credentials cannot be prompted at all, so
 | that step is always skipped with a pointer to noerd:make-admin-user.
 */
describe('non-interactive', function (): void {
    beforeEach(function (): void {
        $this->runOptInSteps = fn(array $parameters = []) => $this->artisan(
            'test:noerd-install',
            array_merge(['--step' => ['runMigrationsAndSetupAdmin', 'installDemoApp']], $parameters),
        );
    });

    it('skips migrations, admin setup and the demo app in a non-interactive run without flags', function (): void {
        ($this->runOptInSteps)(['--no-interaction' => true])
            ->expectsOutputToContain('Non-interactive run: skipping migrations. Pass --migrate to run them.')
            ->expectsOutputToContain('Non-interactive run: skipping the demo app. Pass --demo to install it.')
            ->assertExitCode(0);

        expect(ZzInstallFixtureCommand::$calledCommands)->toBeEmpty();
    });

    it('runs migrations non-interactively when --migrate is passed and still skips the admin prompts', function (): void {
        // An existing tenant keeps the flow off the tenant-creation branch — this
        // test asserts the migrate opt-in and the admin skip, nothing else.
        Tenant::factory()->create();

        ($this->runOptInSteps)(['--migrate' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Non-interactive run: skipping admin setup.')
            ->expectsOutputToContain('Non-interactive run: skipping the demo app. Pass --demo to install it.')
            ->assertExitCode(0);

        expect(($this->calledCommandNames)())->toContain('migrate')
            ->not->toContain('noerd:demo');
    });

    it('installs the demo app non-interactively only with the --demo flag', function (): void {
        ($this->runOptInSteps)(['--demo' => true, '--no-interaction' => true])
            ->assertExitCode(0);

        expect(($this->calledCommandNames)())->toContain('noerd:demo');
    });

    it('still asks the demo question in interactive runs', function (): void {
        ($this->runOptInSteps)()
            ->expectsConfirmation('Would you like to run "php artisan migrate" now?')
            ->expectsConfirmation('Would you like to install the Demo App?', 'yes')
            ->assertExitCode(0);

        expect(($this->calledCommandNames)())->toContain('noerd:demo');
    });
});

describe('ready callout', function (): void {
    it('displays the application ready callout with the next steps', function (): void {
        config(['app.url' => 'https://example.test']);

        Artisan::call('test:noerd-install', ['--step' => ['displayApplicationReady']]);
        $output = Artisan::output();

        // The callout copy is content — asserted is only the functional next step:
        // the apps-dashboard URL derived from app.url.
        expect($output)->toContain('https://example.test/noerd-apps');
    });
});

/*
 | publishNoerdConfig() must never clobber a host's config/noerd.php silently:
 | an existing file is diffed against the stub, missing top-level keys are
 | reported, and an overwrite leaves no .bak behind (the host config is under
 | version control).
 */
describe('config publishing', function (): void {
    beforeEach(function (): void {
        $this->configPath = base_path('config/noerd.php');
        $this->originalConfig = File::exists($this->configPath) ? File::get($this->configPath) : null;

        $this->publishConfig = fn(array $parameters = []) => $this->artisan(
            'test:noerd-install',
            array_merge(['--step' => ['publishNoerdConfig'], '--no-interaction' => true], $parameters),
        );
    });

    afterEach(function (): void {
        if ($this->originalConfig === null) {
            File::delete($this->configPath);
        } else {
            File::put($this->configPath, $this->originalConfig);
        }
        File::delete($this->configPath . '.bak');
    });

    it('publishes the stub when no config exists yet', function (): void {
        File::delete($this->configPath);

        ($this->publishConfig)()
            ->expectsOutputToContain('Published config/noerd.php successfully.')
            ->assertExitCode(0);

        expect(File::exists($this->configPath))->toBeTrue();
    });

    it('leaves an up-to-date config untouched', function (): void {
        File::copy(dirname(__DIR__, 2) . '/stubs/noerd.php.stub', $this->configPath);
        File::append($this->configPath, "\n// host marker\n");
        $before = File::get($this->configPath);

        ($this->publishConfig)()
            ->expectsOutputToContain('left untouched')
            ->assertExitCode(0);

        expect(File::get($this->configPath))->toBe($before);
    });

    it('reports missing top-level keys and keeps the file without --force', function (): void {
        File::put($this->configPath, "<?php\n\nreturn ['routes' => ['prefix' => 'noerd']];\n");

        ($this->publishConfig)()
            ->expectsOutputToContain('missing new top-level keys')
            ->assertExitCode(0);

        expect(File::get($this->configPath))->toContain("['prefix' => 'noerd']");
    });

    it('overwrites with --force without writing a .bak backup', function (): void {
        File::put($this->configPath, "<?php\n\nreturn ['routes' => ['prefix' => 'custom']];\n");

        ($this->publishConfig)(['--force' => true])
            ->expectsOutputToContain('Published config/noerd.php successfully.')
            ->assertExitCode(0);

        expect(File::exists($this->configPath . '.bak'))->toBeFalse()
            ->and(File::get($this->configPath))->not->toContain("'prefix' => 'custom'");
    });
});
