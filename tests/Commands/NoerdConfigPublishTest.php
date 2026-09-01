<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Noerd\Commands\NoerdInstallCommand;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | publishNoerdConfig() must never clobber a host's config/noerd.php silently:
 | an existing file is diffed against the stub, missing top-level keys are
 | reported, and an overwrite leaves no .bak behind (the host config is under
 | version control).
 */

class ConfigPublishFixtureCommand extends NoerdInstallCommand
{
    protected $signature = 'test:config-publish {--force : Overwrite existing files}';

    public function handle(): int
    {
        $this->publishNoerdConfig();

        return 0;
    }
}

beforeEach(function (): void {
    $this->app[Kernel::class]->registerCommand(new ConfigPublishFixtureCommand());
    $this->configPath = base_path('config/noerd.php');
    $this->originalConfig = File::exists($this->configPath) ? File::get($this->configPath) : null;
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

    $this->artisan('test:config-publish', ['--no-interaction' => true])
        ->expectsOutputToContain('Published config/noerd.php successfully.')
        ->assertExitCode(0);

    expect(File::exists($this->configPath))->toBeTrue();
});

it('leaves an up-to-date config untouched', function (): void {
    File::copy(dirname(__DIR__, 2) . '/stubs/noerd.php.stub', $this->configPath);
    File::append($this->configPath, "\n// host marker\n");
    $before = File::get($this->configPath);

    $this->artisan('test:config-publish', ['--no-interaction' => true])
        ->expectsOutputToContain('left untouched')
        ->assertExitCode(0);

    expect(File::get($this->configPath))->toBe($before);
});

it('reports missing top-level keys and keeps the file without --force', function (): void {
    File::put($this->configPath, "<?php\n\nreturn ['routes' => ['prefix' => 'noerd']];\n");

    $this->artisan('test:config-publish', ['--no-interaction' => true])
        ->expectsOutputToContain('missing new top-level keys')
        ->assertExitCode(0);

    expect(File::get($this->configPath))->toContain("['prefix' => 'noerd']");
});

it('overwrites with --force without writing a .bak backup', function (): void {
    File::put($this->configPath, "<?php\n\nreturn ['routes' => ['prefix' => 'custom']];\n");

    $this->artisan('test:config-publish', ['--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Published config/noerd.php successfully.')
        ->assertExitCode(0);

    expect(File::exists($this->configPath . '.bak'))->toBeFalse()
        ->and(File::get($this->configPath))->not->toContain("'prefix' => 'custom'");
});
