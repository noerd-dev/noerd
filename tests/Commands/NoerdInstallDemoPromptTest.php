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
    protected $signature = 'test:install-demo-prompt {--force : Overwrite existing files without asking}';

    /** @var array<int, array{command: string, arguments: array<string, mixed>}> */
    public static array $calledCommands = [];

    public function handle()
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

beforeEach(function (): void {
    InstallDemoPromptFixtureCommand::$calledCommands = [];
    $this->app[Kernel::class]->registerCommand(new InstallDemoPromptFixtureCommand());
});

it('runs noerd:demo when the demo app prompt is confirmed', function (): void {
    $this->artisan('test:install-demo-prompt', ['--force' => true])
        ->expectsConfirmation('Would you like to install the Demo App?', 'yes')
        ->assertExitCode(0);

    expect(InstallDemoPromptFixtureCommand::$calledCommands)
        ->toHaveCount(1)
        ->and(InstallDemoPromptFixtureCommand::$calledCommands[0]['command'])->toBe('noerd:demo')
        ->and(InstallDemoPromptFixtureCommand::$calledCommands[0]['arguments'])->toBe(['--force' => true]);
});

it('skips noerd:demo when the demo app prompt is declined', function (): void {
    $this->artisan('test:install-demo-prompt')
        ->expectsConfirmation('Would you like to install the Demo App?')
        ->expectsOutputToContain('Demo app will NOT be installed. You can run it later with: php artisan noerd:demo')
        ->assertExitCode(0);

    expect(InstallDemoPromptFixtureCommand::$calledCommands)->toBeEmpty();
});
