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
 | frontend steps and the Livewire layout patch are neutralised — they would
 | scaffold a package.json, shell out to npm and publish config/livewire.php.
 */
class ZzNoerdUpdateFixtureCommand extends NoerdUpdateCommand
{
    public static int $boostUpdateCalls = 0;
    protected $signature = 'test:noerd-update
                            {--force : Overwrite existing files without asking}
                            {--reset-navigation : Replace the setup and profile navigation}
                            {--dry-run : List the YAML files the rewrite would change}
                            {--build : Run npm build after update}';

    protected function setupFrontendAssets(): void {}

    protected function updateLivewireConfig(): void {}

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
    \Noerd\Commands\Support\BoostConfig::markRefreshedInProcess(false);

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
    File::copy(dirname(__DIR__, 2) . '/config/noerd.php', $configPath);
    File::append($configPath, "\n// host marker\n");
    $before = File::get($configPath);

    $this->artisan('test:noerd-update', ['--no-interaction' => true])
        ->assertExitCode(0);

    expect(File::get($configPath))->toBe($before);
});

it('rewrites the project YAML to the current vocabulary', function (): void {
    File::ensureDirectoryExists($this->hostPath . '/app-configs/zzapp/lists');
    File::put($this->hostPath . '/app-configs/zzapp/navigation.yml', "- title: Zz\n  block_menus: []\n");
    File::put($this->hostPath . '/app-configs/zzapp/lists/things-list.yml', "columns:\n  - field: state\n    type: colored_text\n");

    $this->artisan('test:noerd-update', ['--force' => true, '--no-interaction' => true])
        ->assertExitCode(0);

    expect(File::get($this->hostPath . '/app-configs/zzapp/navigation.yml'))->toBe("- title: Zz\n  blockMenus: []\n")
        ->and(File::get($this->hostPath . '/app-configs/zzapp/lists/things-list.yml'))->toContain('type: coloredText');
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
            ->expectsOutputToContain('Failed to create directory')
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

        $config = new \Noerd\Commands\Support\BoostConfig($this->hostPath . '/boost.json');
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

it('never overwrites an existing host config, not even with --force', function (): void {
    $configPath = $this->hostPath . '/config/noerd.php';
    File::put($configPath, "<?php\n\nreturn ['routes' => ['prefix' => 'zz']];\n");

    $this->artisan('test:noerd-update', ['--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('is never overwritten')
        ->assertExitCode(0);

    expect(File::get($configPath))->toBe("<?php\n\nreturn ['routes' => ['prefix' => 'zz']];\n");
});

it('keeps the host setup navigation with --force, adds missing core entries and drops the retired one', function (): void {
    $target = $this->hostPath . '/app-configs/setup/navigation.yml';
    File::ensureDirectoryExists(dirname($target));
    File::put($target, Symfony\Component\Yaml\Yaml::dump([[
        'title' => 'Setup',
        'name' => 'setup',
        'route' => 'noerd.setup',
        'blockMenus' => [[
            'title' => 'Administration',
            'navigations' => [
                ['title' => 'Zz Module Screen', 'route' => 'zz.module'],
                ['title' => 'My Users', 'route' => 'noerd.users', 'heroicon' => 'users'],
                ['title' => 'Collection Definitions', 'route' => 'noerd.setup-collection-definitions', 'config' => 'noerd.collections.show_definitions_ui'],
            ],
        ]],
    ]], 10, 2));

    $this->artisan('test:noerd-update', ['--force' => true, '--no-interaction' => true])->assertExitCode(0);

    $navigation = Symfony\Component\Yaml\Yaml::parse(File::get($target));
    $routes = array_column($navigation[0]['blockMenus'][0]['navigations'], 'route');

    // Host and module entries stay in their order and with their titles, the
    // core entries the host lacked follow, the retired definitions entry is gone.
    expect(array_slice($routes, 0, 2))->toBe(['zz.module', 'noerd.users'])
        ->and($routes)->toContain('noerd.tenants')
        ->and($routes)->not->toContain('noerd.setup-collection-definitions')
        ->and($navigation[0]['blockMenus'][0]['navigations'][1]['title'])->toBe('My Users')
        ->and(collect($navigation[0]['blockMenus'])->pluck('dynamic')->filter()->values()->all())->toBe(['setup-collections']);

    $this->artisan('test:noerd-update', ['--force' => true, '--reset-navigation' => true, '--no-interaction' => true])->assertExitCode(0);

    expect(File::get($target))->toBe(File::get($this->sourceDir . '/navigation.yml'));
});

it('lists what the vocabulary rewrite would change and writes nothing in a dry run', function (): void {
    File::ensureDirectoryExists($this->hostPath . '/app-configs/zzapp/lists');
    File::put($this->hostPath . '/app-configs/zzapp/lists/things-list.yml', "columns:\n  - field: state\n    type: colored_text\n");

    $this->artisan('test:noerd-update', ['--dry-run' => true, '--no-interaction' => true])
        ->expectsOutputToContain('1 YAML file(s) would be updated')
        ->expectsOutputToContain('app-configs/zzapp/lists/things-list.yml')
        ->assertExitCode(0);

    expect(File::get($this->hostPath . '/app-configs/zzapp/lists/things-list.yml'))->toContain('colored_text')
        ->and(File::exists($this->hostPath . '/config/noerd.php'))->toBeFalse()
        ->and(File::exists($this->hostPath . '/app-configs/setup'))->toBeFalse();
});

it('lists every file a real run rewrote', function (): void {
    File::ensureDirectoryExists($this->hostPath . '/app-configs/zzapp/lists');
    File::put($this->hostPath . '/app-configs/zzapp/lists/things-list.yml', "columns:\n  - field: state\n    type: colored_text\n");

    $this->artisan('test:noerd-update', ['--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('app-configs/zzapp/lists/things-list.yml')
        ->assertExitCode(0);
});

it('rewrites the 0.17 test-helper loader in the host tests/Pest.php once', function (): void {
    File::ensureDirectoryExists($this->hostPath . '/tests');
    File::put($this->hostPath . '/tests/Pest.php', "<?php\n\npest()->extend(Tests\\TestCase::class)->in('Feature');\n\n\\Noerd\\Tests\\HelperLoader::load();\n");

    $this->artisan('test:noerd-update', ['--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('tests/Pest.php (loads the noerd test helpers through Noerd\\Testing\\HelperLoader)')
        ->assertExitCode(0);

    $pest = File::get($this->hostPath . '/tests/Pest.php');

    expect($pest)->toContain("\\Noerd\\Testing\\HelperLoader::load();\n")
        ->and($pest)->not->toContain('Noerd\\Tests\\HelperLoader')
        ->and($pest)->toContain("pest()->extend(Tests\\TestCase::class)->in('Feature');");

    // A second run finds nothing to rewrite and leaves the file alone.
    $this->artisan('test:noerd-update', ['--force' => true, '--no-interaction' => true])
        ->doesntExpectOutputToContain('tests/Pest.php')
        ->assertExitCode(0);

    expect(File::get($this->hostPath . '/tests/Pest.php'))->toBe($pest);
});

it('names the test-helper loader rewrite in a dry run and writes nothing', function (): void {
    File::ensureDirectoryExists($this->hostPath . '/tests');
    File::put($this->hostPath . '/tests/Pest.php', "<?php\n\n\\Noerd\\Tests\\HelperLoader::load();\n");

    $this->artisan('test:noerd-update', ['--dry-run' => true, '--no-interaction' => true])
        ->expectsOutputToContain('tests/Pest.php would load the noerd test helpers through Noerd\\Testing\\HelperLoader')
        ->assertExitCode(0);

    expect(File::get($this->hostPath . '/tests/Pest.php'))->toContain('\\Noerd\\Tests\\HelperLoader::load();');
});

describe('stored setup collection definitions', function (): void {
    beforeEach(function (): void {
        \Illuminate\Support\Facades\Schema::create('setup_collection_definitions', function ($table): void {
            $table->id();
            $table->string('key');
        });
    });

    afterEach(function (): void {
        \Illuminate\Support\Facades\Schema::dropIfExists('setup_collection_definitions');
    });

    it('warns when the table holds definitions the YAML repository does not read', function (): void {
        \Illuminate\Support\Facades\DB::table('setup_collection_definitions')->insert(['key' => 'ZZ_STORED']);

        $this->artisan('test:noerd-update', ['--dry-run' => true, '--no-interaction' => true])
            ->expectsOutputToContain('setup_collection_definitions holds 1 setup collection definition(s)')
            ->assertExitCode(0);

        expect(\Illuminate\Support\Facades\Schema::hasTable('setup_collection_definitions'))->toBeTrue();
    });

    it('stays quiet for an empty table or when another repository reads the definitions', function (): void {
        $this->artisan('test:noerd-update', ['--dry-run' => true, '--no-interaction' => true])
            ->doesntExpectOutputToContain('setup_collection_definitions')
            ->assertExitCode(0);

        \Illuminate\Support\Facades\DB::table('setup_collection_definitions')->insert(['key' => 'ZZ_STORED']);
        $this->app->instance(
            \Noerd\Contracts\SetupCollectionDefinitionRepositoryContract::class,
            Mockery::mock(\Noerd\Contracts\SetupCollectionDefinitionRepositoryContract::class),
        );

        $this->artisan('test:noerd-update', ['--dry-run' => true, '--no-interaction' => true])
            ->doesntExpectOutputToContain('setup_collection_definitions')
            ->assertExitCode(0);
    });
});
