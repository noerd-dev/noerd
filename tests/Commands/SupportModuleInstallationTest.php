<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Noerd\Support\ModuleInstallContext;
use Noerd\Tests\TestCase;
use Noerd\Traits\InstallsNoerdModule;

uses(TestCase::class);

/**
 * A SUPPORT module (no tenant app, no navigation) installs through
 * InstallsNoerdModule alone: it DECLARES what it ships — a PHP config, YAML of
 * screens that hang in other apps, setup-app YAML — and the trait publishes it,
 * offers the migrations and runs the module's idempotent setup steps.
 *
 * The fixture's module root is a disposable folder, so no real module is touched.
 */
class ZzSupportInstallCommand extends Command
{
    use InstallsNoerdModule;

    /** @var array<int, string> */
    public static array $events = [];

    protected $signature = 'noerd:install-zz-support {--force : Overwrite existing files without asking} {--migrate : Run migrations without asking}';

    public function handle(): int
    {
        return $this->runSupportModuleInstallation();
    }

    public function call($command, array $arguments = [])
    {
        static::$events[] = 'call:' . $command;

        return 0;
    }

    protected function getModuleName(): string
    {
        return 'Zz Support';
    }

    protected function getModuleRoot(): string
    {
        return base_path('tests-tmp/zz-support');
    }

    protected function getConfigFiles(): array
    {
        return ['zz-support.php'];
    }

    protected function getAppConfigsDir(): ?string
    {
        return $this->getModuleRoot() . '/app-configs/zz-support';
    }

    protected function publishModuleExtras(bool $update): void
    {
        static::$events[] = 'extras';
    }

    protected function ensureModuleSetup(): void
    {
        static::$events[] = 'setup';
    }

    protected function executeNpmInstall(array $packages): void
    {
        static::$events[] = 'npm-install:' . implode(',', $packages);
    }

    protected function executeNpmBuild(): void
    {
        static::$events[] = 'npm-build';
    }
}

class ZzSupportUpdateCommand extends ZzSupportInstallCommand
{
    protected $signature = 'noerd:update-zz-support {--force : Overwrite existing files without asking}';

    public function handle(): int
    {
        return $this->runSupportModuleUpdate();
    }
}

beforeEach(function (): void {
    ModuleInstallContext::reset();
    ZzSupportInstallCommand::$events = [];

    $root = base_path('tests-tmp/zz-support');
    File::deleteDirectory($root);
    File::ensureDirectoryExists($root . '/config');
    File::ensureDirectoryExists($root . '/database/migrations');
    File::put($root . '/database/migrations/2026_01_01_000000_create_zz_things_table.php', "<?php\n");
    File::ensureDirectoryExists($root . '/app-configs/zz-support/lists');
    File::ensureDirectoryExists($root . '/app-configs/setup/collections');
    File::put($root . '/config/zz-support.php', "<?php\n\nreturn ['driver' => 'shipped', 'timeout' => 30];\n");
    File::put($root . '/app-configs/zz-support/lists/zz-things-list.yml', "title: Zz Things\n");
    File::put($root . '/app-configs/setup/collections/zz_kinds.yml', "title: Zz Kinds\n");
    File::put($root . '/app-configs/setup/navigation.yml', "- title: must never be copied\n");

    $this->navigationBefore = File::get(base_path('app-configs/setup/navigation.yml'));

    $this->app[Kernel::class]->registerCommand(new ZzSupportInstallCommand());
    $this->app[Kernel::class]->registerCommand(new ZzSupportUpdateCommand());
});

afterEach(function (): void {
    ModuleInstallContext::reset();

    File::deleteDirectory(base_path('tests-tmp'));
    File::deleteDirectory(base_path('app-configs/zz-support'));
    File::delete([
        config_path('zz-support.php'),
        base_path('app-configs/setup/collections/zz_kinds.yml'),
    ]);
});

describe('install', function (): void {
    it('publishes what the module declares and runs the setup after the migration prompt', function (): void {
        $this->artisan('noerd:install-zz-support')
            ->expectsConfirmation('Would you like to run php artisan migrate now?', 'yes')
            ->expectsOutputToContain('Zz Support successfully installed!')
            ->assertExitCode(0);

        expect(File::get(config_path('zz-support.php')))->toContain("'shipped'")
            ->and(File::exists(base_path('app-configs/zz-support/lists/zz-things-list.yml')))->toBeTrue()
            ->and(File::exists(base_path('app-configs/setup/collections/zz_kinds.yml')))->toBeTrue()
            ->and(File::get(base_path('app-configs/setup/navigation.yml')))->toBe($this->navigationBefore)
            // A migration published by the extras is part of the migrate run; seeds in
            // the setup step need the tables that run creates.
            ->and(ZzSupportInstallCommand::$events)->toBe(['extras', 'call:migrate', 'setup']);
    });

    it('keeps an existing config unless the user agrees or --force is given', function (): void {
        File::put(config_path('zz-support.php'), "<?php\n\nreturn ['driver' => 'host'];\n");

        $this->artisan('noerd:install-zz-support')
            ->expectsConfirmation('Config file config/zz-support.php already exists. Overwrite?', 'no')
            ->expectsConfirmation('Would you like to run php artisan migrate now?', 'no')
            ->assertExitCode(0);

        expect(File::get(config_path('zz-support.php')))->toContain("'host'");

        $this->artisan('noerd:install-zz-support', ['--force' => true])
            ->expectsConfirmation('Would you like to run php artisan migrate now?', 'no')
            ->assertExitCode(0);

        expect(File::get(config_path('zz-support.php')))->toContain("'shipped'");
    });

    it('never migrates implicitly in a non-interactive run', function (): void {
        $this->artisan('noerd:install-zz-support', ['--no-interaction' => true])
            ->expectsOutputToContain('Non-interactive run: skipping migrations')
            ->assertExitCode(0);

        expect(ZzSupportInstallCommand::$events)->toBe(['extras', 'setup']);

        ZzSupportInstallCommand::$events = [];

        // --force: the first run published the YAML, a second one would ask per file.
        $this->artisan('noerd:install-zz-support', ['--no-interaction' => true, '--migrate' => true, '--force' => true])
            ->assertExitCode(0);

        expect(ZzSupportInstallCommand::$events)->toBe(['extras', 'call:migrate', 'setup']);
    });

    it('does the npm work a base installation handed over', function (): void {
        // noerd:install ran for this command on a fresh project and deferred node.
        ModuleInstallContext::deferNpm(['zz-tooling@^1.0'], build: true);

        $this->artisan('noerd:install-zz-support')
            ->expectsConfirmation('Would you like to run php artisan migrate now?', 'no')
            ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'yes')
            ->assertExitCode(0);

        expect(ZzSupportInstallCommand::$events)->toBe(['extras', 'setup', 'npm-install:zz-tooling@^1.0', 'npm-build'])
            ->and(ModuleInstallContext::hasDeferredNpm())->toBeFalse();
    });

    it('offers no migration when the module ships none', function (): void {
        File::deleteDirectory(base_path('tests-tmp/zz-support/database'));

        // No expectation registered: the migration question would fail the run.
        $this->artisan('noerd:install-zz-support')->assertExitCode(0);

        expect(ZzSupportInstallCommand::$events)->toBe(['extras', 'setup']);
    });

    it('does not mention npm when nothing was handed over', function (): void {
        $this->artisan('noerd:install-zz-support')
            ->expectsConfirmation('Would you like to run php artisan migrate now?', 'no')
            ->doesntExpectOutputToContain('npm')
            ->assertExitCode(0);
    });

    it('asks nothing while it is installed as a dependency', function (): void {
        ModuleInstallContext::asDependency(function (): void {
            $this->artisan('noerd:install-zz-support')->assertExitCode(0);
        });

        expect(ZzSupportInstallCommand::$events)->toBe(['extras', 'setup']);
    });
});

describe('update', function (): void {
    it('publishes a missing config and runs the setup, without migrating', function (): void {
        $this->artisan('noerd:update-zz-support')
            ->expectsOutputToContain('Zz Support updated!')
            ->assertExitCode(0);

        expect(File::get(config_path('zz-support.php')))->toContain("'shipped'")
            ->and(ZzSupportInstallCommand::$events)->toBe(['extras', 'setup']);
    });

    it('never overwrites the host config — not even under --force — and names the keys it lacks', function (): void {
        File::put(config_path('zz-support.php'), "<?php\n\nreturn ['driver' => 'host'];\n");

        $this->artisan('noerd:update-zz-support', ['--force' => true])
            ->expectsOutputToContain('config/zz-support.php does not declare: timeout')
            ->assertExitCode(0);

        expect(File::get(config_path('zz-support.php')))->toContain("'host'");
    });

    it('keeps edited setup YAML and refreshes it under --force', function (): void {
        File::put(base_path('app-configs/setup/collections/zz_kinds.yml'), "title: Edited\n");

        $this->artisan('noerd:update-zz-support')->assertExitCode(0);
        expect(File::get(base_path('app-configs/setup/collections/zz_kinds.yml')))->toBe("title: Edited\n");

        $this->artisan('noerd:update-zz-support', ['--force' => true])->assertExitCode(0);
        expect(File::get(base_path('app-configs/setup/collections/zz_kinds.yml')))->toBe("title: Zz Kinds\n");
    });
});
