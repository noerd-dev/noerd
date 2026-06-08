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

const FIXTURE_MODULE_KEY = 'tenant-prompt-fixture';

const FIXTURE_APP_KEY = 'TENANT-PROMPT-FIXTURE';

/**
 * Minimal install command built on the shared installation traits, pointed at a
 * temporary source directory so the real update path can be exercised without
 * touching any module's actual app-configs.
 */
class TenantPromptFixtureInstallCommand extends Command
{
    use HasModuleInstallation;
    use RequiresNoerdInstallation;

    protected $signature = 'noerd:install-tenant-prompt-fixture {--force : Overwrite existing files without asking}';

    protected $description = 'Test fixture install command';

    public function handle(): int
    {
        return $this->runModuleInstallation();
    }

    protected function getModuleName(): string
    {
        return 'Tenant Prompt Fixture';
    }

    protected function getModuleKey(): string
    {
        return FIXTURE_MODULE_KEY;
    }

    protected function getDefaultAppTitle(): string
    {
        return 'Tenant Prompt Fixture';
    }

    protected function getAppIcon(): string
    {
        return 'noerd::icons.app';
    }

    protected function getAppRoute(): string
    {
        return FIXTURE_MODULE_KEY;
    }

    protected function getSourceDir(): string
    {
        // Nested so that publishSkills() (dirname twice + /skills) lands inside the
        // disposable tests-tmp tree and finds nothing to publish.
        return base_path('tests-tmp/module/app-configs/' . FIXTURE_MODULE_KEY);
    }
}

beforeEach(function (): void {
    $sourceDir = base_path('tests-tmp/module/app-configs/' . FIXTURE_MODULE_KEY);

    File::ensureDirectoryExists($sourceDir);
    File::put($sourceDir . '/navigation.yml', Yaml::dump([
        [
            'name' => FIXTURE_MODULE_KEY,
            'title' => 'Tenant Prompt Fixture',
            'route' => FIXTURE_MODULE_KEY,
        ],
    ]));

    // The update path requires the target app-configs directory to already exist.
    File::ensureDirectoryExists(base_path('app-configs/' . FIXTURE_MODULE_KEY));

    $this->app[Kernel::class]->registerCommand(new TenantPromptFixtureInstallCommand());
});

afterEach(function (): void {
    File::deleteDirectory(base_path('tests-tmp'));
    File::deleteDirectory(base_path('app-configs/' . FIXTURE_MODULE_KEY));
});

it('offers tenant assignment when re-running install on an already-installed app', function (): void {
    TenantApp::create([
        'name' => FIXTURE_APP_KEY,
        'title' => 'Tenant Prompt Fixture',
        'icon' => 'noerd::icons.app',
        'route' => FIXTURE_MODULE_KEY,
        'is_active' => true,
    ]);

    $this->artisan('noerd:install-tenant-prompt-fixture', ['--force' => true])
        ->expectsOutputToContain('is already installed. Running update instead...')
        ->expectsConfirmation('Would you like to assign the app to tenants now?', 'no')
        ->assertExitCode(0);
});
