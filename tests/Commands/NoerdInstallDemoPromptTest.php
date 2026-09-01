<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Noerd\Commands\NoerdInstallCommand;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/**
 * Fixture exposing the demo-app installation step in isolation and recording
 * sub-command calls instead of running the real noerd:demo installation.
 */
class InstallDemoPromptFixtureCommand extends NoerdInstallCommand
{
    /** @var array<int, array{command: string, arguments: array<string, mixed>}> */
    public static array $calledCommands = [];
    protected $signature = 'test:install-demo-prompt {--force : Overwrite existing files}';

    public function handle(): int
    {
        $this->installDemoApp();

        return 0;
    }

    public function call($command, array $arguments = [])
    {
        static::$calledCommands[] = ['command' => $command, 'arguments' => $arguments];

        return 0;
    }
}

/**
 * Fixture recording the ORDER of the installation steps. Every side-effecting
 * step is neutralised and only reports its name, so the flow can be asserted
 * without touching the filesystem, the database or npm.
 */
class InstallFlowOrderFixtureCommand extends NoerdInstallCommand
{
    /** @var array<int, string> */
    public static array $steps = [];
    protected $signature = 'test:install-flow-order';

    protected function copyDirectoryContents(string $sourceDir, string $targetDir): array
    {
        static::$steps[] = 'copyDirectoryContents';

        return [
            'created_dirs' => 0,
            'copied_files' => 0,
            'skipped_files' => 0,
            'overwritten_files' => 0,
        ];
    }

    protected function displaySummary(array $results): void {}

    protected function ensureAppModulesDirectory(): void
    {
        static::$steps[] = 'ensureAppModulesDirectory';
    }

    protected function updatePhpunitXml(): void
    {
        static::$steps[] = 'updatePhpunitXml';
    }

    protected function publishNoerdConfig(): void
    {
        static::$steps[] = 'publishNoerdConfig';
    }

    protected function setupFrontendAssets(): void
    {
        static::$steps[] = 'setupFrontendAssets';
    }

    protected function publishNoerdAssets(): void
    {
        static::$steps[] = 'publishNoerdAssets';
    }

    protected function runMigrationsAndSetupAdmin(): void
    {
        static::$steps[] = 'runMigrationsAndSetupAdmin';
    }

    protected function runNpmBuild(): void
    {
        static::$steps[] = 'runNpmBuild';
    }

    protected function installDemoApp(): void
    {
        static::$steps[] = 'installDemoApp';
    }

    protected function displayApplicationReady(): void
    {
        static::$steps[] = 'displayApplicationReady';
    }
}

beforeEach(function (): void {
    InstallDemoPromptFixtureCommand::$calledCommands = [];
    InstallFlowOrderFixtureCommand::$steps = [];

    $this->app[Kernel::class]->registerCommand(new InstallDemoPromptFixtureCommand());
    $this->app[Kernel::class]->registerCommand(new InstallFlowOrderFixtureCommand());
});

it('runs noerd:demo when the demo app prompt is confirmed', function (): void {
    $this->artisan('test:install-demo-prompt', ['--force' => true])
        ->expectsConfirmation('Would you like to install the Demo App?', 'yes')
        ->assertExitCode(0);

    expect(InstallDemoPromptFixtureCommand::$calledCommands)
        ->toHaveCount(1)
        ->and(InstallDemoPromptFixtureCommand::$calledCommands[0]['command'])->toBe('noerd:demo')
        ->and(InstallDemoPromptFixtureCommand::$calledCommands[0]['arguments'])->toBe(['--force' => true, '--migrate' => false, '--seed' => false]);
});

it('skips noerd:demo when the demo app prompt is declined', function (): void {
    $this->artisan('test:install-demo-prompt')
        ->expectsConfirmation('Would you like to install the Demo App?')
        ->expectsOutputToContain('Demo app will NOT be installed. You can run it later with: php artisan noerd:demo')
        ->assertExitCode(0);

    expect(InstallDemoPromptFixtureCommand::$calledCommands)->toBeEmpty();
});

it('offers the demo app as the last installation step', function (): void {
    $this->artisan('test:install-flow-order')->assertExitCode(0);

    $steps = InstallFlowOrderFixtureCommand::$steps;

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
