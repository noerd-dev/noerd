<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Noerd\Commands\Concerns\RegistersBoostPackage;
use Noerd\Support\BoostConfig;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | Every noerd install/update command registers its package in the host's
 | boost.json so Laravel Boost renders the package's guideline and skills. The
 | concern is exercised through a fixture command against a throwaway base path
 | holding a fake package root — boost:update itself is only recorded: in a host
 | run the real command exists and would render agent files into the temp path.
 */
class ZzBoostRegistrationFixtureCommand extends Command
{
    use RegistersBoostPackage;

    public static bool $updateAvailable = true;

    public static int $updateCalls = 0;

    protected $signature = 'test:register-boost {root}';

    public function handle(): int
    {
        $this->registerBoostPackage((string) $this->argument('root'));

        return 0;
    }

    protected function boostUpdateAvailable(): bool
    {
        return static::$updateAvailable;
    }

    protected function runBoostUpdate(): void
    {
        static::$updateCalls++;
    }
}

beforeEach(function (): void {
    ZzBoostRegistrationFixtureCommand::$updateAvailable = true;
    ZzBoostRegistrationFixtureCommand::$updateCalls = 0;
    BoostConfig::markRefreshedInProcess(false);

    $this->app[Kernel::class]->registerCommand(new ZzBoostRegistrationFixtureCommand());

    $this->originalBasePath = $this->app->basePath();
    $this->hostPath = storage_path('framework/testing/zz-boost-registration');
    File::deleteDirectory($this->hostPath);
    File::ensureDirectoryExists($this->hostPath);
    $this->app->setBasePath($this->hostPath);

    $this->packageRoot = $this->hostPath . '/packages/boost-fixture';
    File::ensureDirectoryExists($this->packageRoot . '/resources/boost/guidelines');
    File::ensureDirectoryExists($this->packageRoot . '/resources/boost/skills/zz-skill');
    File::put($this->packageRoot . '/composer.json', json_encode(['name' => 'zz/boost-fixture']));
    File::put($this->packageRoot . '/resources/boost/guidelines/core.blade.php', "## Zz\n");
    File::put($this->packageRoot . '/resources/boost/skills/zz-skill/SKILL.md', "---\nname: zz-skill\ndescription: Fixture skill\n---\n# Zz\n");
    File::ensureDirectoryExists($this->hostPath . '/vendor/zz/boost-fixture');

    $this->boostJson = $this->hostPath . '/boost.json';
    File::put($this->boostJson, json_encode(['agents' => ['claude_code'], 'guidelines' => true, 'packages' => ['vendor/other'], 'skills' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
});

afterEach(function (): void {
    $this->app->setBasePath($this->originalBasePath);
    File::deleteDirectory($this->hostPath);
    BoostConfig::markRefreshedInProcess(false);
});

it('registers the package and its skills and refreshes the agent files', function (): void {
    $this->artisan('test:register-boost', ['root' => $this->packageRoot])
        ->expectsOutputToContain('Boost package registered: zz/boost-fixture')
        ->expectsOutputToContain('Boost skills registered: zz-skill')
        ->assertExitCode(0);

    $config = new BoostConfig($this->boostJson);

    expect($config->packages())->toBe(['vendor/other', 'zz/boost-fixture'])
        ->and($config->skills())->toBe(['zz-skill'])
        ->and(ZzBoostRegistrationFixtureCommand::$updateCalls)->toBe(1);
});

it('refreshes only once per process unless a new entry was added', function (): void {
    $this->artisan('test:register-boost', ['root' => $this->packageRoot])->assertExitCode(0);
    $this->artisan('test:register-boost', ['root' => $this->packageRoot])
        ->expectsOutputToContain('Boost package already registered: zz/boost-fixture')
        ->assertExitCode(0);

    expect(ZzBoostRegistrationFixtureCommand::$updateCalls)->toBe(1);

    // A second package registered later in the same process (noerd:update-all) re-renders.
    File::put($this->packageRoot . '/composer.json', json_encode(['name' => 'zz/second']));
    File::ensureDirectoryExists($this->hostPath . '/vendor/zz/second');
    $this->artisan('test:register-boost', ['root' => $this->packageRoot])->assertExitCode(0);

    expect(ZzBoostRegistrationFixtureCommand::$updateCalls)->toBe(2);
});

it('prints a hint and writes nothing when Boost is not set up', function (): void {
    File::delete($this->boostJson);

    $this->artisan('test:register-boost', ['root' => $this->packageRoot])
        ->expectsOutputToContain('Laravel Boost is not set up (no boost.json)')
        ->assertExitCode(0);

    expect(File::exists($this->boostJson))->toBeFalse()
        ->and(ZzBoostRegistrationFixtureCommand::$updateCalls)->toBe(0);
});

it('stays silent for a package without guideline and skills', function (): void {
    File::deleteDirectory($this->packageRoot . '/resources/boost');
    $before = File::get($this->boostJson);

    $this->artisan('test:register-boost', ['root' => $this->packageRoot])
        ->doesntExpectOutputToContain('Boost')
        ->assertExitCode(0);

    expect(File::get($this->boostJson))->toBe($before)
        ->and(ZzBoostRegistrationFixtureCommand::$updateCalls)->toBe(0);
});

it('stays silent for a package root without a composer name', function (): void {
    File::put($this->packageRoot . '/composer.json', json_encode(['description' => 'nameless']));
    $before = File::get($this->boostJson);

    $this->artisan('test:register-boost', ['root' => $this->packageRoot])
        ->doesntExpectOutputToContain('Boost')
        ->assertExitCode(0);

    expect(File::get($this->boostJson))->toBe($before);
});

it('tracks only skills Boost will discover', function (): void {
    // A top-level skills/ folder is published by noerd itself — Boost would treat
    // its name as stale and delete the published link; a SKILL.md without front
    // matter name is not a skill for Boost either.
    File::ensureDirectoryExists($this->packageRoot . '/skills/zz-top-level');
    File::put($this->packageRoot . '/skills/zz-top-level/SKILL.md', "---\nname: zz-top-level\ndescription: Published by noerd\n---\n");
    File::ensureDirectoryExists($this->packageRoot . '/resources/boost/skills/zz-nameless');
    File::put($this->packageRoot . '/resources/boost/skills/zz-nameless/SKILL.md', "# No front matter\n");

    $this->artisan('test:register-boost', ['root' => $this->packageRoot])->assertExitCode(0);

    expect((new BoostConfig($this->boostJson))->skills())->toBe(['zz-skill']);
});

it('warns and writes nothing when the package is not installed under vendor', function (): void {
    File::deleteDirectory($this->hostPath . '/vendor/zz/boost-fixture');
    $before = File::get($this->boostJson);

    $this->artisan('test:register-boost', ['root' => $this->packageRoot])
        ->expectsOutputToContain('zz/boost-fixture is not installed via Composer')
        ->assertExitCode(0);

    expect(File::get($this->boostJson))->toBe($before)
        ->and(ZzBoostRegistrationFixtureCommand::$updateCalls)->toBe(0);
});

it('leaves an invalid boost.json untouched', function (): void {
    File::put($this->boostJson, "{ broken\n");

    $this->artisan('test:register-boost', ['root' => $this->packageRoot])
        ->expectsOutputToContain('boost.json is not valid JSON')
        ->assertExitCode(0);

    expect(File::get($this->boostJson))->toBe("{ broken\n");
});

it('points to boost:update when the command is not available', function (): void {
    ZzBoostRegistrationFixtureCommand::$updateAvailable = false;

    $this->artisan('test:register-boost', ['root' => $this->packageRoot])
        ->expectsOutputToContain('Run php artisan boost:update')
        ->assertExitCode(0);

    expect((new BoostConfig($this->boostJson))->packages())->toContain('zz/boost-fixture')
        ->and(ZzBoostRegistrationFixtureCommand::$updateCalls)->toBe(0);
});
