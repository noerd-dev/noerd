<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Noerd\Commands\NoerdUpdateCommand;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | noerd:update refreshes an existing installation: it re-publishes the setup
 | app configs over whatever the host has, keeps config/noerd.php and reports a
 | failure instead of a half-done update when the target cannot be written.
 |
 | The command runs against a THROWAWAY base path, so neither the skeleton's
 | published app-configs nor its config/noerd.php are touched. Only the two
 | frontend steps are neutralised — they would scaffold a package.json and
 | shell out to npm.
 */
class ZzNoerdUpdateFixtureCommand extends NoerdUpdateCommand
{
    public static int $boostUpdateCalls = 0;
    protected $signature = 'test:noerd-update
                            {--force : Overwrite existing files without asking}
                            {--build : Run npm build after update}';

    protected function setupFrontendAssets(): void {}

    protected function publishNoerdAssets(): void {}

    protected function boostUpdateAvailable(): bool
    {
        return true;
    }

    protected function boostPackageInstalled(string $name): bool
    {
        return true;
    }

    protected function runBoostUpdate(): void
    {
        static::$boostUpdateCalls++;
    }
}

beforeEach(function (): void {
    $this->app[Kernel::class]->registerCommand(new ZzNoerdUpdateFixtureCommand());
    ZzNoerdUpdateFixtureCommand::$boostUpdateCalls = 0;
    \Noerd\Support\BoostConfig::markRefreshedInProcess(false);

    $this->originalBasePath = $this->app->basePath();
    $this->hostPath = storage_path('framework/testing/zz-noerd-update');

    File::deleteDirectory($this->hostPath);
    File::ensureDirectoryExists($this->hostPath . '/config');

    $this->app->setBasePath($this->hostPath);

    $this->sourceDir = dirname(__DIR__, 2) . '/app-configs/setup';
});

afterEach(function (): void {
    $this->app->setBasePath($this->originalBasePath);
    File::deleteDirectory($this->hostPath);
});

it('refreshes a stale published config with --force', function (): void {
    // Pick any published file and let the host version drift from the package.
    $relative = mb_substr((string) File::allFiles($this->sourceDir)[0]->getPathname(), mb_strlen($this->sourceDir) + 1);
    $target = $this->hostPath . '/app-configs/setup/' . $relative;

    File::ensureDirectoryExists(dirname($target));
    File::put($target, "title: Stale host copy\n");

    $this->artisan('test:noerd-update', ['--force' => true, '--no-interaction' => true])
        ->assertExitCode(0);

    expect(File::get($target))->toBe(File::get($this->sourceDir . '/' . $relative));
});

it('publishes the noerd config when the host has none', function (): void {
    $this->artisan('test:noerd-update', ['--force' => true, '--no-interaction' => true])
        ->assertExitCode(0);

    expect(File::exists($this->hostPath . '/config/noerd.php'))->toBeTrue();
});

it('keeps an up-to-date host config untouched', function (): void {
    $configPath = $this->hostPath . '/config/noerd.php';
    File::copy(dirname(__DIR__, 2) . '/stubs/noerd.php.stub', $configPath);
    File::append($configPath, "\n// host marker\n");
    $before = File::get($configPath);

    $this->artisan('test:noerd-update', ['--no-interaction' => true])
        ->assertExitCode(0);

    expect(File::get($configPath))->toBe($before);
});

it('fails when the target directory cannot be created', function (): void {
    // A FILE where the app-configs directory belongs: mkdir() cannot succeed.
    File::put($this->hostPath . '/app-configs', 'not a directory');

    // The command inspects mkdir()'s return value; Laravel's error handler would
    // turn the warning it also emits into an exception before that branch is
    // reached, so the handler is muted for the duration of the run.
    set_error_handler(static fn(): bool => true);

    try {
        $this->artisan('test:noerd-update', ['--force' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Failed to create target directory')
            ->assertExitCode(1);
    } finally {
        restore_error_handler();
    }

    expect(File::exists($this->hostPath . '/config/noerd.php'))->toBeFalse();
});

describe('boost registration', function (): void {
    it('registers noerd and its skills in the host boost.json and re-renders the agent files', function (): void {
        File::put($this->hostPath . '/boost.json', json_encode(['agents' => ['claude_code'], 'guidelines' => true, 'packages' => [], 'skills' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $this->artisan('test:noerd-update', ['--force' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Boost package registered: noerd/noerd')
            ->assertExitCode(0);

        $config = new \Noerd\Support\BoostConfig($this->hostPath . '/boost.json');
        $shippedSkills = array_map('basename', File::directories(dirname(__DIR__, 2) . '/resources/boost/skills'));
        sort($shippedSkills);

        expect($config->packages())->toBe(['noerd/noerd'])
            ->and($config->skills())->toBe($shippedSkills)
            ->and(ZzNoerdUpdateFixtureCommand::$boostUpdateCalls)->toBe(1);
    });

    it('only hints at Boost when the host has no boost.json', function (): void {
        $this->artisan('test:noerd-update', ['--force' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Laravel Boost is not set up (no boost.json)')
            ->assertExitCode(0);

        expect(File::exists($this->hostPath . '/boost.json'))->toBeFalse()
            ->and(ZzNoerdUpdateFixtureCommand::$boostUpdateCalls)->toBe(0);
    });
});
