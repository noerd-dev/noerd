<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Noerd\Models\TenantApp;
use Noerd\Traits\HasModuleInstallation;
use Noerd\Traits\RequiresNoerdInstallation;
use Symfony\Component\Yaml\Yaml;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

const ENSURE_APP_MODULE_KEY = 'ensure-app-fixture';

const ENSURE_APP_KEY = 'ENSURE-APP-FIXTURE';

/**
 * Minimal install command built on the shared installation traits. It ships no
 * migration stub — exactly like the CRM module — so publishMigration() returns
 * null and the only thing that can register the app is ensureTenantAppRegistered().
 */
class EnsureAppFixtureInstallCommand extends Command
{
    use HasModuleInstallation;
    use RequiresNoerdInstallation;

    protected $signature = 'noerd:install-ensure-app-fixture {--force : Overwrite existing files without asking}';

    protected $description = 'Test fixture install command';

    public function handle(): int
    {
        return $this->runModuleInstallation();
    }

    protected function getModuleName(): string
    {
        return 'Ensure App Fixture';
    }

    protected function getModuleKey(): string
    {
        return ENSURE_APP_MODULE_KEY;
    }

    protected function getDefaultAppTitle(): string
    {
        return 'Ensure App Fixture';
    }

    protected function getAppIcon(): string
    {
        return 'noerd::icons.app';
    }

    protected function getAppRoute(): string
    {
        return ENSURE_APP_MODULE_KEY;
    }

    protected function getSourceDir(): string
    {
        // Nested so that publishSkills() (dirname twice + /skills) lands inside the
        // disposable tests-tmp tree and finds nothing to publish.
        return base_path('tests-tmp/ensure-app/app-configs/' . ENSURE_APP_MODULE_KEY);
    }
}

beforeEach(function (): void {
    $sourceDir = base_path('tests-tmp/ensure-app/app-configs/' . ENSURE_APP_MODULE_KEY);

    File::ensureDirectoryExists($sourceDir);
    File::put($sourceDir . '/navigation.yml', Yaml::dump([
        [
            'name' => ENSURE_APP_MODULE_KEY,
            'title' => 'Ensure App Fixture',
            'route' => ENSURE_APP_MODULE_KEY,
        ],
    ]));

    // No target app-configs dir and no tenant_apps row: forces the fresh install
    // path (not the update path) on the first run.
    File::deleteDirectory(base_path('app-configs/' . ENSURE_APP_MODULE_KEY));
    TenantApp::where('name', ENSURE_APP_KEY)->delete();

    $this->app[Kernel::class]->registerCommand(new EnsureAppFixtureInstallCommand());
});

afterEach(function (): void {
    File::deleteDirectory(base_path('tests-tmp'));
    File::deleteDirectory(base_path('app-configs/' . ENSURE_APP_MODULE_KEY));
});

function runEnsureAppInstall(object $test): void
{
    $test->artisan('noerd:install-ensure-app-fixture', ['--force' => true])
        ->expectsConfirmation('Should Ensure App Fixture be installed as a hidden app (not shown in main navigation)?', 'no')
        ->expectsQuestion('App title', 'Ensure App Fixture')
        ->expectsConfirmation('Would you like to assign the app to tenants now?', 'no')
        ->expectsConfirmation('Would you like to run php artisan migrate now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);
}

it('registers the tenant app even though the module ships no migration stub', function (): void {
    runEnsureAppInstall($this);

    expect(TenantApp::where('name', ENSURE_APP_KEY)->count())->toBe(1);

    $app = TenantApp::where('name', ENSURE_APP_KEY)->first();
    expect($app->title)->toBe('Ensure App Fixture')
        ->and($app->route)->toBe(ENSURE_APP_MODULE_KEY)
        ->and($app->is_active)->toBeTrue();
});

it('restores the tenant app row when it was manually deleted after install', function (): void {
    // First install creates the row.
    runEnsureAppInstall($this);
    expect(TenantApp::where('name', ENSURE_APP_KEY)->count())->toBe(1);

    // Someone manually deletes it (the registering migration, if any, stays
    // recorded as run and would never re-insert it).
    TenantApp::where('name', ENSURE_APP_KEY)->delete();
    // Re-running install must restore it without the target config dir present.
    File::deleteDirectory(base_path('app-configs/' . ENSURE_APP_MODULE_KEY));
    expect(TenantApp::where('name', ENSURE_APP_KEY)->count())->toBe(0);

    runEnsureAppInstall($this);

    expect(TenantApp::where('name', ENSURE_APP_KEY)->count())->toBe(1);
});
