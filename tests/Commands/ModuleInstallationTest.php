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

    protected $signature = 'noerd:install-zz-install-fixture {--force : Overwrite existing files without asking}';

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
        ->expectsConfirmation('Should Zz Install Fixture be installed as a hidden app (not shown in main navigation)?', 'no')
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
