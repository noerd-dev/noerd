<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Noerd\Commands\Concerns\PublishesConfigDirectory;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | publishConfigDirectory() overwrites an existing host config file in place and
 | leaves no `.bak` sibling behind — the published YAMLs live in the host
 | repository, so version control is the change history.
 */

class PublishConfigDirectoryFixtureCommand extends Command
{
    use PublishesConfigDirectory;

    public static string $sourceDir = '';
    public static string $targetDir = '';
    public static bool $quiet = false;
    protected $signature = 'test:publish-config-directory {--force : Overwrite existing files}';

    public function handle(): int
    {
        $this->publishConfigDirectory(static::$sourceDir, static::$targetDir, quiet: static::$quiet);

        return 0;
    }
}

beforeEach(function (): void {
    $this->app[Kernel::class]->registerCommand(new PublishConfigDirectoryFixtureCommand());

    $base = base_path('storage/framework/testing/publish-config-directory');
    File::deleteDirectory($base);

    PublishConfigDirectoryFixtureCommand::$quiet = false;
    PublishConfigDirectoryFixtureCommand::$sourceDir = $base . '/source';
    PublishConfigDirectoryFixtureCommand::$targetDir = $base . '/target';

    File::ensureDirectoryExists(PublishConfigDirectoryFixtureCommand::$sourceDir . '/lists');
    File::put(PublishConfigDirectoryFixtureCommand::$sourceDir . '/lists/accounts-list.yml', "title: Accounts\n");
});

afterEach(function (): void {
    File::deleteDirectory(base_path('storage/framework/testing/publish-config-directory'));
});

it('copies a new config file', function (): void {
    $this->artisan('test:publish-config-directory', ['--no-interaction' => true])
        ->assertExitCode(0);

    expect(File::get(PublishConfigDirectoryFixtureCommand::$targetDir . '/lists/accounts-list.yml'))
        ->toBe("title: Accounts\n");
});

it('overwrites an existing config file without writing a .bak backup', function (): void {
    $target = PublishConfigDirectoryFixtureCommand::$targetDir . '/lists/accounts-list.yml';
    File::ensureDirectoryExists(dirname($target));
    File::put($target, "title: Host customization\n");

    $this->artisan('test:publish-config-directory', ['--force' => true, '--no-interaction' => true])
        ->assertExitCode(0);

    expect(File::get($target))->toBe("title: Accounts\n")
        ->and(File::exists($target . '.bak'))->toBeFalse();
});

describe('quiet mode', function (): void {
    beforeEach(function (): void {
        PublishConfigDirectoryFixtureCommand::$quiet = true;
    });

    it('publishes the files without naming any of them', function (): void {
        $this->artisan('test:publish-config-directory', ['--no-interaction' => true])
            ->doesntExpectOutputToContain('accounts-list.yml')
            ->doesntExpectOutputToContain('Created directory')
            ->assertExitCode(0);

        expect(File::exists(PublishConfigDirectoryFixtureCommand::$targetDir . '/lists/accounts-list.yml'))->toBeTrue();
    });

    it('still asks before overwriting an existing file', function (): void {
        // The per-file LINE is suppressed, never the prompt — a hidden question
        // would leave an interactive run waiting for an answer nobody sees.
        $target = PublishConfigDirectoryFixtureCommand::$targetDir . '/lists/accounts-list.yml';
        File::ensureDirectoryExists(dirname($target));
        File::put($target, "title: Host customization\n");

        $this->artisan('test:publish-config-directory')
            ->expectsChoice(
                'File already exists: lists/accounts-list.yml. What do you want to do?',
                'skip',
                ['skip', 'overwrite', 'overwrite-all'],
            )
            ->assertExitCode(0);

        expect(File::get($target))->toBe("title: Host customization\n");
    });
});
