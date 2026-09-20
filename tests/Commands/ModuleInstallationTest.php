<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Noerd\Models\TenantApp;
use Noerd\Tests\TestCase;
use Noerd\Traits\HasModuleInstallation;
use Noerd\Traits\RequiresNoerdInstallation;
use Symfony\Component\Yaml\Yaml;

uses(TestCase::class);
uses(RefreshDatabase::class);

const ZZ_MODULE_KEY = 'zz-install-fixture';

const ZZ_MODULE_APP_KEY = 'ZZ-INSTALL-FIXTURE';

/**
 * Minimal install command built on the shared installation traits, pointed at a
 * temporary source directory so install and update can be exercised without
 * touching any module's actual app-configs. It ships no migration stub —
 * exactly like the CRM module — so publishMigration() returns null and the only
 * thing that can register the app is ensureTenantAppRegistered().
 */
class ZzModuleInstallFixtureCommand extends Command
{
    use HasModuleInstallation;
    use RequiresNoerdInstallation;

    public static int $boostUpdateCalls = 0;

    public static int $setupCalls = 0;

    protected $signature = 'noerd:install-zz-install-fixture {--force : Overwrite existing files without asking} {--scaffold : Silent post-scaffold run}';

    protected $description = 'Test fixture install command';

    public function handle(): int
    {
        return $this->runModuleInstallation();
    }

    protected function getModuleName(): string
    {
        return 'Zz Install Fixture';
    }

    protected function getModuleKey(): string
    {
        return ZZ_MODULE_KEY;
    }

    protected function getDefaultAppTitle(): string
    {
        return 'Zz Install Fixture';
    }

    protected function getAppIcon(): string
    {
        return 'noerd::icons.app';
    }

    protected function getAppRoute(): string
    {
        return ZZ_MODULE_KEY;
    }

    protected function getSourceDir(): string
    {
        // Nested so that publishSkills() (dirname twice + /skills) lands inside the
        // disposable tests-tmp tree and finds nothing to publish.
        return base_path('tests-tmp/module/app-configs/' . ZZ_MODULE_KEY);
    }

    protected function ensureModuleSetup(): void
    {
        static::$setupCalls++;
    }

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
    $sourceDir = base_path('tests-tmp/module/app-configs/' . ZZ_MODULE_KEY);

    File::ensureDirectoryExists($sourceDir);
    File::put($sourceDir . '/navigation.yml', Yaml::dump([
        [
            'name' => ZZ_MODULE_KEY,
            'title' => 'Zz Install Fixture',
            'route' => ZZ_MODULE_KEY,
        ],
    ]));

    File::ensureDirectoryExists($sourceDir . '/settings');
    File::put($sourceDir . '/settings/zz-fixture-settings-page.yml', "title: Fixture Settings\n");

    // No target app-configs dir and no tenant_apps row: forces the fresh install
    // path (not the update path) on the first run.
    File::deleteDirectory(base_path('app-configs/' . ZZ_MODULE_KEY));
    TenantApp::where('name', ZZ_MODULE_APP_KEY)->delete();

    $this->app[Kernel::class]->registerCommand(new ZzModuleInstallFixtureCommand());
});

afterEach(function (): void {
    File::deleteDirectory(base_path('tests-tmp'));
    File::deleteDirectory(base_path('app-configs/' . ZZ_MODULE_KEY));
});

function runZzModuleInstall(object $test): void
{
    $test->artisan('noerd:install-' . ZZ_MODULE_KEY, ['--force' => true])
        ->expectsQuestion('App title', 'Zz Install Fixture')
        ->expectsConfirmation('Would you like to assign the app to tenants now?', 'no')
        ->expectsConfirmation('Would you like to run php artisan migrate now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);
}

function registerZzModuleApp(): TenantApp
{
    return TenantApp::create([
        'name' => ZZ_MODULE_APP_KEY,
        'title' => 'Zz Install Fixture',
        'icon' => 'noerd::icons.app',
        'route' => ZZ_MODULE_KEY,
        'is_active' => true,
    ]);
}

describe('ensure app', function (): void {
    it('registers the tenant app even though the module ships no migration stub', function (): void {
        runZzModuleInstall($this);

        expect(TenantApp::where('name', ZZ_MODULE_APP_KEY)->count())->toBe(1);

        $app = TenantApp::where('name', ZZ_MODULE_APP_KEY)->first();
        expect($app->title)->toBe('Zz Install Fixture')
            ->and($app->route)->toBe(ZZ_MODULE_KEY)
            ->and($app->is_active)->toBeTrue();
    });

    it('self-heals the update path when the app is registered but its config dir is missing', function (): void {
        // The app is already registered (so runModuleInstallation diverts to update),
        // but its app-configs folder was never published.
        registerZzModuleApp();

        $targetDir = base_path('app-configs/' . ZZ_MODULE_KEY);
        expect(File::isDirectory($targetDir))->toBeFalse();

        $this->artisan('noerd:install-' . ZZ_MODULE_KEY, ['--force' => true])
            ->expectsConfirmation('Would you like to assign the app to tenants now?', 'no')
            ->assertExitCode(0);

        // The missing config folder is created and the navigation published into it.
        expect(File::exists($targetDir . '/navigation.yml'))->toBeTrue();
    });

    it('publishes the settings folder on install and update', function (): void {
        $settingsTarget = base_path('app-configs/' . ZZ_MODULE_KEY . '/settings/zz-fixture-settings-page.yml');

        // Fresh install path.
        runZzModuleInstall($this);
        expect(File::exists($settingsTarget))->toBeTrue();

        // A re-run diverts to the update path — the settings copy must run there too.
        File::delete($settingsTarget);
        $this->artisan('noerd:install-' . ZZ_MODULE_KEY, ['--force' => true])
            ->expectsConfirmation('Would you like to assign the app to tenants now?', 'no')
            ->assertExitCode(0);

        expect(File::exists($settingsTarget))->toBeTrue();
    });

    it('restores the tenant app row when it was manually deleted after install', function (): void {
        // First install creates the row.
        runZzModuleInstall($this);
        expect(TenantApp::where('name', ZZ_MODULE_APP_KEY)->count())->toBe(1);

        // Someone manually deletes it (the registering migration, if any, stays
        // recorded as run and would never re-insert it).
        TenantApp::where('name', ZZ_MODULE_APP_KEY)->delete();
        // Re-running install must restore it without the target config dir present.
        File::deleteDirectory(base_path('app-configs/' . ZZ_MODULE_KEY));
        expect(TenantApp::where('name', ZZ_MODULE_APP_KEY)->count())->toBe(0);

        runZzModuleInstall($this);

        expect(TenantApp::where('name', ZZ_MODULE_APP_KEY)->count())->toBe(1);
    });
});

describe('module setup hook', function (): void {
    it('runs the idempotent setup steps on install and again on every update', function (): void {
        ZzModuleInstallFixtureCommand::$setupCalls = 0;

        runZzModuleInstall($this);

        expect(ZzModuleInstallFixtureCommand::$setupCalls)->toBe(1);

        // Re-running install on a registered app takes the update path.
        $this->artisan('noerd:install-' . ZZ_MODULE_KEY, ['--force' => true])
            ->expectsConfirmation('Would you like to assign the app to tenants now?', 'no')
            ->assertExitCode(0);

        expect(ZzModuleInstallFixtureCommand::$setupCalls)->toBe(2);
    });

    it('no longer writes the unread hidden key into the published navigation', function (): void {
        runZzModuleInstall($this);

        $navigation = Yaml::parseFile(base_path('app-configs/' . ZZ_MODULE_KEY . '/navigation.yml'));

        expect($navigation[0])->not->toHaveKey('hidden');
    });
});

describe('registration migration', function (): void {
    beforeEach(function (): void {
        // The fixture ships a stub here only; every other test runs without one.
        $stubDir = base_path('tests-tmp/module/app-configs/stubs');
        File::ensureDirectoryExists($stubDir);
        File::put($stubDir . '/add_' . ZZ_MODULE_KEY . '_tenant_app.php.stub', <<<'STUB'
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Support\Facades\DB;

            return new class () extends Migration {
                public function up(): void
                {
                    if (! DB::table('tenant_apps')->where('name', '{{APP_NAME}}')->exists()) {
                        DB::table('tenant_apps')->insert([
                            'title' => '{{APP_TITLE}}',
                            'name' => '{{APP_NAME}}',
                            'icon' => '{{APP_ICON}}',
                            'route' => '{{APP_ROUTE}}',
                            'is_active' => true,
                        ]);
                    }
                }
            };
            STUB);

        $this->publishedMigrations = fn(): array => File::glob(database_path('migrations/*_add_' . ZZ_MODULE_KEY . '_tenant_app.php'));
        File::delete(($this->publishedMigrations)());
    });

    afterEach(function (): void {
        File::delete(($this->publishedMigrations)());
    });

    it('publishes the registering migration on update when the app row came from elsewhere', function (): void {
        // Registered by a module migration or a seeder: install diverts to the
        // update path, which used to leave the host without the migration — the
        // next deployment then never registered the app.
        $app = registerZzModuleApp();
        $app->update(['title' => "Zz Owner's Title"]);

        $this->artisan('noerd:install-' . ZZ_MODULE_KEY, ['--force' => true])
            ->expectsConfirmation('Would you like to assign the app to tenants now?', 'no')
            ->expectsOutputToContain('Migration published')
            ->assertExitCode(0);

        $published = ($this->publishedMigrations)();

        expect($published)->toHaveCount(1);

        // The file carries what the ROW says (an apostrophe must not break it) and
        // registers exactly that app on a database that does not have it yet.
        TenantApp::where('name', ZZ_MODULE_APP_KEY)->delete();
        (require $published[0])->up();

        expect(TenantApp::where('name', ZZ_MODULE_APP_KEY)->value('title'))->toBe("Zz Owner's Title");
    });

    it('never publishes a second registering migration', function (): void {
        registerZzModuleApp();

        foreach ([1, 2] as $run) {
            $this->artisan('noerd:install-' . ZZ_MODULE_KEY, ['--force' => true])
                ->expectsConfirmation('Would you like to assign the app to tenants now?', 'no')
                ->assertExitCode(0);
        }

        expect(($this->publishedMigrations)())->toHaveCount(1);
    });
});

describe('tenant prompt', function (): void {
    it('offers tenant assignment when re-running install on an already-installed app', function (): void {
        registerZzModuleApp();

        // The update path runs against an already published app-configs directory.
        File::ensureDirectoryExists(base_path('app-configs/' . ZZ_MODULE_KEY));

        $this->artisan('noerd:install-' . ZZ_MODULE_KEY, ['--force' => true])
            ->expectsOutputToContain('is already installed. Running update instead...')
            ->expectsConfirmation('Would you like to assign the app to tenants now?', 'no')
            ->assertExitCode(0);
    });
});

describe('scaffold mode', function (): void {
    it('publishes, registers and only asks for the tenant assignment', function (): void {
        $tenant = Noerd\Models\Tenant::factory()->create(['name' => 'Zz Scaffold Tenant']);

        // No hidden-app / title / migrate / npm questions — the multiselect is the only prompt.
        $this->artisan('noerd:install-' . ZZ_MODULE_KEY, ['--scaffold' => true])
            ->expectsQuestion("Which tenants should 'Zz Install Fixture' be assigned to?", [$tenant->id])
            ->doesntExpectOutput('Installing Zz Install Fixture...')
            ->doesntExpectOutput("✓ 'Zz Install Fixture' assigned to 'Zz Scaffold Tenant'")
            ->expectsOutput("'Zz Install Fixture' is now assigned to 1 tenant(s).")
            ->assertExitCode(0);

        $app = TenantApp::where('name', ZZ_MODULE_APP_KEY)->first();
        expect($app)->not->toBeNull()
            ->and($app->tenants()->pluck('tenants.id')->all())->toBe([$tenant->id])
            ->and(File::exists(base_path('app-configs/' . ZZ_MODULE_KEY . '/navigation.yml')))->toBeTrue()
            ->and(File::exists(base_path('app-configs/' . ZZ_MODULE_KEY . '/settings/zz-fixture-settings-page.yml')))->toBeTrue();
    });
});

describe('boost registration', function (): void {
    beforeEach(function (): void {
        ZzModuleInstallFixtureCommand::$boostUpdateCalls = 0;
        \Noerd\Support\BoostConfig::markRefreshedInProcess(false);

        $this->boostJson = base_path('boost.json');
        $this->boostJsonBackup = File::exists($this->boostJson) ? File::get($this->boostJson) : null;
    });

    afterEach(function (): void {
        if ($this->boostJsonBackup === null) {
            File::delete($this->boostJson);
        } else {
            File::put($this->boostJson, $this->boostJsonBackup);
        }
    });

    it('registers a module that ships a boost guideline in the host boost.json', function (): void {
        $moduleRoot = base_path('tests-tmp/module');
        File::put($moduleRoot . '/composer.json', json_encode(['name' => 'zz/install-fixture']));
        File::ensureDirectoryExists($moduleRoot . '/resources/boost/guidelines');
        File::put($moduleRoot . '/resources/boost/guidelines/core.blade.php', "## Zz\n");
        File::put($this->boostJson, json_encode(['agents' => ['claude_code'], 'guidelines' => true, 'packages' => ['noerd/noerd'], 'skills' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        runZzModuleInstall($this);

        expect((new \Noerd\Support\BoostConfig($this->boostJson))->packages())->toBe(['noerd/noerd', 'zz/install-fixture'])
            ->and(ZzModuleInstallFixtureCommand::$boostUpdateCalls)->toBe(1);

        // The update path is idempotent: nothing new, no second render.
        $this->artisan('noerd:install-' . ZZ_MODULE_KEY, ['--force' => true])
            ->expectsConfirmation('Would you like to assign the app to tenants now?', 'no')
            ->assertExitCode(0);

        expect((new \Noerd\Support\BoostConfig($this->boostJson))->packages())->toBe(['noerd/noerd', 'zz/install-fixture'])
            ->and(ZzModuleInstallFixtureCommand::$boostUpdateCalls)->toBe(1);
    });

    it('leaves boost.json alone for a module without agent guidelines', function (): void {
        File::put($this->boostJson, "{\n    \"packages\": [\n        \"noerd/noerd\"\n    ]\n}\n");

        runZzModuleInstall($this);

        expect(File::get($this->boostJson))->toBe("{\n    \"packages\": [\n        \"noerd/noerd\"\n    ]\n}\n")
            ->and(ZzModuleInstallFixtureCommand::$boostUpdateCalls)->toBe(0);
    });
});
