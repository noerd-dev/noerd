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

    /** @var array<string, int> exit code a recorded sub-command answers with (default 0) */
    public static array $exitCodes = [];

    protected $signature = 'test:noerd-install
                            {--step=* : Installation steps to run in isolation}
                            {--force : Overwrite existing files}
                            {--migrate : Run migrations without asking}
                            {--build : Run npm build without asking}';

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

        return static::$exitCodes[$command] ?? 0;
    }

    protected function publishSetupAppConfigs(): bool
    {
        if (! static::$recordStepsOnly) {
            return parent::publishSetupAppConfigs();
        }

        static::$steps[] = 'publishSetupAppConfigs';

        return true;
    }

    protected function updateLivewireConfig(): void
    {
        $this->step('updateLivewireConfig', fn() => parent::updateLivewireConfig());
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

    protected function registerNoerdBoostPackage(): void
    {
        $this->step('registerNoerdBoostPackage', fn() => parent::registerNoerdBoostPackage());
    }

    protected function runMigrationsAndSetupAdmin(): bool
    {
        $result = true;
        $this->step('runMigrationsAndSetupAdmin', function () use (&$result): void {
            $result = parent::runMigrationsAndSetupAdmin();
        });

        return $result;
    }

    protected function runNpmBuild(): void
    {
        $this->step('runNpmBuild', fn() => parent::runNpmBuild());
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
    ZzInstallFixtureCommand::$exitCodes = [];

    $this->app[Kernel::class]->registerCommand(new ZzInstallFixtureCommand());

    $this->calledCommandNames = fn(): array => array_column(ZzInstallFixtureCommand::$calledCommands, 'command');
});

describe('step order', function (): void {
    it('closes the installation with the ready callout, after every step that asks something', function (): void {
        ZzInstallFixtureCommand::$recordStepsOnly = true;

        $this->artisan('test:noerd-install')->assertExitCode(0);

        $steps = ZzInstallFixtureCommand::$steps;
        $readyStep = array_search('displayApplicationReady', $steps, true);

        expect($readyStep)->not->toBeFalse('The ready callout did not run');

        foreach (['publishNoerdConfig', 'setupFrontendAssets', 'registerNoerdBoostPackage', 'runMigrationsAndSetupAdmin', 'runNpmBuild'] as $earlierStep) {
            $earlierStepIndex = array_search($earlierStep, $steps, true);

            expect($earlierStepIndex)->not->toBeFalse("Step {$earlierStep} did not run")
                ->and($readyStep)->toBeGreaterThan($earlierStepIndex, "The ready callout must come after {$earlierStep}");
        }
    });
});

/*
 | noerd:install must be safe under --no-interaction: prompts default to "yes"
 | interactively, but a scripted run (CI, deploy) must NEVER inherit those
 | defaults — migrations, npm build and admin setup all require an explicit
 | opt-in flag. Admin credentials cannot be prompted at all, so that step is
 | always skipped with a pointer to noerd:make-admin-user.
 */
describe('non-interactive', function (): void {
    beforeEach(function (): void {
        $this->runOptInSteps = fn(array $parameters = []) => $this->artisan(
            'test:noerd-install',
            array_merge(['--step' => ['runMigrationsAndSetupAdmin']], $parameters),
        );
    });

    it('skips migrations and admin setup in a non-interactive run without flags', function (): void {
        ($this->runOptInSteps)(['--no-interaction' => true])
            ->expectsOutputToContain('Non-interactive run: skipping migrations. Pass --migrate to run them.')
            ->assertExitCode(0);

        expect(ZzInstallFixtureCommand::$calledCommands)->toBeEmpty();
    });

    it('runs migrations non-interactively when --migrate is passed and still skips the admin prompts', function (): void {
        // An existing tenant keeps the flow off the tenant-creation branch — this
        // test asserts the migrate opt-in and the admin skip, nothing else.
        Tenant::factory()->create();

        ($this->runOptInSteps)(['--migrate' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Non-interactive run: skipping admin setup.')
            ->assertExitCode(0);

        expect(($this->calledCommandNames)())->toContain('migrate');

        // In production `migrate` asks before it runs — a scripted run must answer it.
        $migrate = collect(ZzInstallFixtureCommand::$calledCommands)->firstWhere('command', 'migrate');
        expect($migrate['arguments'])->toMatchArray(['--force' => true]);
    });

    it('creates neither tenant nor admin when the migration fails', function (): void {
        ZzInstallFixtureCommand::$exitCodes['migrate'] = 1;

        $this->artisan('test:noerd-install', ['--step' => ['runMigrationsAndSetupAdmin'], '--migrate' => true, '--no-interaction' => true])
            ->expectsOutputToContain('php artisan migrate failed')
            ->assertExitCode(0);

        expect(($this->calledCommandNames)())->toBe(['migrate'])
            ->and(Tenant::count())->toBe(0);
    });

    it('still asks the migration question in interactive runs', function (): void {
        ($this->runOptInSteps)()
            ->expectsConfirmation('Would you like to run "php artisan migrate" now?')
            ->assertExitCode(0);

        expect(ZzInstallFixtureCommand::$calledCommands)->toBeEmpty();
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
        File::copy(dirname(__DIR__, 2) . '/config/noerd.php', $this->configPath);
        File::append($this->configPath, "\n// host marker\n");
        $before = File::get($this->configPath);

        ($this->publishConfig)()
            ->expectsOutputToContain('left untouched')
            ->assertExitCode(0);

        expect(File::get($this->configPath))->toBe($before);
    });

    it('reports missing keys and keeps the file', function (): void {
        File::put($this->configPath, "<?php\n\nreturn ['routes' => ['prefix' => 'noerd']];\n");

        ($this->publishConfig)()
            ->expectsOutputToContain('missing new keys')
            ->assertExitCode(0);

        expect(File::get($this->configPath))->toContain("['prefix' => 'noerd']");
    });

    it('never overwrites an existing config, not even with --force', function (): void {
        $config = "<?php\n\nreturn ['routes' => ['prefix' => 'custom']];\n";
        File::put($this->configPath, $config);

        ($this->publishConfig)(['--force' => true])
            ->expectsOutputToContain('is never overwritten')
            ->assertExitCode(0);

        expect(File::get($this->configPath))->toBe($config)
            ->and(File::exists($this->configPath . '.bak'))->toBeFalse();
    });

    it('names missing nested keys and keys the package no longer reads', function (): void {
        $stub = require dirname(__DIR__, 2) . '/config/noerd.php';
        unset($stub['features']['currency']);
        $stub['zz_retired'] = true;
        File::put($this->configPath, '<?php return ' . var_export($stub, true) . ';');

        ($this->publishConfig)()
            ->expectsOutputToContain('missing new keys: features.currency')
            ->expectsOutputToContain('no longer reads: zz_retired')
            ->assertExitCode(0);
    });
});

describe('boost registration', function (): void {
    it('registers the package before the migration prompt so a scripted run needs no extra step', function (): void {
        ZzInstallFixtureCommand::$recordStepsOnly = true;

        $this->artisan('test:noerd-install')->assertExitCode(0);

        $steps = ZzInstallFixtureCommand::$steps;

        expect(array_search('registerNoerdBoostPackage', $steps, true))
            ->toBeGreaterThan(array_search('publishNoerdAssets', $steps, true))
            ->toBeLessThan(array_search('runMigrationsAndSetupAdmin', $steps, true));
    });
});
