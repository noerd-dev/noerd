<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Noerd\Events\TenantAppAssigned;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Support\ModuleInstallContext;
use Noerd\Tests\TestCase;
use Noerd\Traits\HasModuleInstallation;
use Noerd\Traits\RequiresNoerdInstallation;
use Symfony\Component\Yaml\Yaml;

uses(TestCase::class);
uses(RefreshDatabase::class);

/**
 * A module that cannot work without another app (the CMS without MEDIA) names it
 * in getRequiredAppKeys(). The required app is then assigned to exactly the
 * tenants the module's own app was assigned to — in the SAME prompt, and never
 * removed again, because another installed module may equally depend on it.
 *
 * Both fixtures are minimal install commands on the shared traits, pointed at a
 * disposable source directory so no module's real app-configs are touched.
 */
abstract class ZzRequiredFixtureCommand extends Command
{
    use HasModuleInstallation;
    use RequiresNoerdInstallation;

    protected function getAppIcon(): string
    {
        return 'noerd::icons.app';
    }

    protected function getAppRoute(): string
    {
        return $this->getModuleKey();
    }

    protected function getSourceDir(): string
    {
        // Nested so publishSkills() (dirname twice + /skills) stays inside tests-tmp.
        return base_path('tests-tmp/module/app-configs/' . $this->getModuleKey());
    }

    protected function boostUpdateAvailable(): bool
    {
        return true;
    }

    protected function boostPackageInstalled(string $name): bool
    {
        return true;
    }

    protected function runBoostUpdate(): void {}
}

class ZzRequiredHostInstallCommand extends ZzRequiredFixtureCommand
{
    protected $signature = 'noerd:install-zz-required-host {--force : Overwrite existing files without asking}';

    protected $description = 'Test fixture install command with a required app';

    public function handle(): int
    {
        return $this->runModuleInstallation();
    }

    protected function getModuleName(): string
    {
        return 'Zz Required Host';
    }

    protected function getModuleKey(): string
    {
        return 'zz-required-host';
    }

    protected function getDefaultAppTitle(): string
    {
        return 'Zz Required Host';
    }

    protected function getRequiredAppKeys(): array
    {
        return ['ZZ-REQUIRED-DEP'];
    }
}

class ZzRequiredDepInstallCommand extends ZzRequiredFixtureCommand
{
    protected $signature = 'noerd:install-zz-required-dep {--force : Overwrite existing files without asking}';

    protected $description = 'Test fixture install command of a required app';

    public function handle(): int
    {
        return $this->runModuleInstallation();
    }

    protected function getModuleName(): string
    {
        return 'Zz Required Dep';
    }

    protected function getModuleKey(): string
    {
        return 'zz-required-dep';
    }

    protected function getDefaultAppTitle(): string
    {
        return 'Zz Required Dep';
    }
}

/** The command that installs the dependency, exactly the way the CMS installs media. */
class ZzRequiredParentInstallCommand extends ZzRequiredHostInstallCommand
{
    protected $signature = 'noerd:install-zz-required-parent {--force : Overwrite existing files without asking}';

    public function handle(): int
    {
        $this->installDependencyModule('noerd:install-zz-required-dep', ['--force' => true]);

        return $this->runModuleInstallation();
    }
}

function zzWriteFixtureSource(string $moduleKey, string $title): void
{
    $sourceDir = base_path('tests-tmp/module/app-configs/' . $moduleKey);

    File::ensureDirectoryExists($sourceDir);
    File::put($sourceDir . '/navigation.yml', Yaml::dump([
        [
            'name' => $moduleKey,
            'title' => $title,
            'route' => $moduleKey,
        ],
    ]));
}

function zzRegisterRequiredApp(): TenantApp
{
    return TenantApp::create([
        'name' => 'ZZ-REQUIRED-DEP',
        'title' => 'Zz Required Dep',
        'icon' => 'noerd::icons.app',
        'route' => 'zz-required-dep',
        'is_active' => true,
    ]);
}

/**
 * Run the host install through its prompts, assigning the app to $tenantIds.
 *
 * @param  array<int>  $tenantIds
 */
function zzRunHostInstall(object $test, array $tenantIds): void
{
    $test->artisan('noerd:install-zz-required-host', ['--force' => true])
        ->expectsConfirmation('Should Zz Required Host be installed as a hidden app (not shown in main navigation)?', 'no')
        ->expectsQuestion('App title', 'Zz Required Host')
        ->expectsConfirmation('Would you like to assign the app to tenants now?', 'yes')
        ->expectsQuestion("Which tenants should 'Zz Required Host' be assigned to?", $tenantIds)
        ->expectsConfirmation('Would you like to run php artisan migrate now?', 'no')
        ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
        ->assertExitCode(0);
}

beforeEach(function (): void {
    ModuleInstallContext::reset();

    foreach ([
        'zz-required-host' => 'Zz Required Host',
        'zz-required-dep' => 'Zz Required Dep',
        'zz-required-parent' => 'Zz Required Parent',
    ] as $moduleKey => $title) {
        zzWriteFixtureSource($moduleKey, $title);
        File::deleteDirectory(base_path('app-configs/' . $moduleKey));
    }

    TenantApp::whereIn('name', ['ZZ-REQUIRED-HOST', 'ZZ-REQUIRED-DEP', 'ZZ-REQUIRED-PARENT'])->delete();

    $this->app[Kernel::class]->registerCommand(new ZzRequiredHostInstallCommand());
    $this->app[Kernel::class]->registerCommand(new ZzRequiredDepInstallCommand());
    $this->app[Kernel::class]->registerCommand(new ZzRequiredParentInstallCommand());

    $this->tenantA = Tenant::factory()->create(['name' => 'Zz Tenant A']);
    $this->tenantB = Tenant::factory()->create(['name' => 'Zz Tenant B']);
});

afterEach(function (): void {
    ModuleInstallContext::reset();

    File::deleteDirectory(base_path('tests-tmp'));

    foreach (['zz-required-host', 'zz-required-dep', 'zz-required-parent'] as $moduleKey) {
        File::deleteDirectory(base_path('app-configs/' . $moduleKey));
    }
});

describe('required apps', function (): void {
    it('assigns a required app to the tenants the module was assigned to', function (): void {
        zzRegisterRequiredApp();

        zzRunHostInstall($this, [$this->tenantA->id]);

        $required = TenantApp::where('name', 'ZZ-REQUIRED-DEP')->first();

        expect($required->tenants()->pluck('tenants.id')->all())->toBe([$this->tenantA->id]);
    });

    it('never removes a required app from a tenant that loses the module', function (): void {
        $required = zzRegisterRequiredApp();

        // Tenant B already runs the required app because of some other module.
        $this->tenantB->tenantApps()->attach($required->id);

        zzRunHostInstall($this, [$this->tenantA->id]);

        expect($required->fresh()->tenants()->pluck('tenants.id')->sort()->values()->all())
            ->toBe(collect([$this->tenantA->id, $this->tenantB->id])->sort()->values()->all());
    });

    it('announces the required app it newly assigned', function (): void {
        Event::fake([TenantAppAssigned::class]);
        zzRegisterRequiredApp();

        $this->artisan('noerd:install-zz-required-host', ['--force' => true])
            ->expectsConfirmation('Should Zz Required Host be installed as a hidden app (not shown in main navigation)?', 'no')
            ->expectsQuestion('App title', 'Zz Required Host')
            ->expectsConfirmation('Would you like to assign the app to tenants now?', 'yes')
            ->expectsQuestion("Which tenants should 'Zz Required Host' be assigned to?", [$this->tenantA->id])
            ->expectsOutput("'Zz Required Dep' is required and was assigned to 1 tenant(s).")
            ->expectsConfirmation('Would you like to run php artisan migrate now?', 'no')
            ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
            ->assertExitCode(0);

        Event::assertDispatched(
            TenantAppAssigned::class,
            fn(TenantAppAssigned $event): bool => $event->appName === 'ZZ-REQUIRED-DEP'
                && $event->tenantId === $this->tenantA->id,
        );
    });

    it('warns instead of failing when the required app is not installed', function (): void {
        $this->artisan('noerd:install-zz-required-host', ['--force' => true])
            ->expectsConfirmation('Should Zz Required Host be installed as a hidden app (not shown in main navigation)?', 'no')
            ->expectsQuestion('App title', 'Zz Required Host')
            ->expectsConfirmation('Would you like to assign the app to tenants now?', 'yes')
            ->expectsQuestion("Which tenants should 'Zz Required Host' be assigned to?", [$this->tenantA->id])
            ->expectsOutputToContain("Required app 'ZZ-REQUIRED-DEP' is not installed")
            ->expectsConfirmation('Would you like to run php artisan migrate now?', 'no')
            ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
            ->assertExitCode(0);

        expect(TenantApp::where('name', 'ZZ-REQUIRED-HOST')->first()->tenants()->count())->toBe(1);
    });
});

describe('dependency installs', function (): void {
    it('does not ask a dependency module about tenants', function (): void {
        ModuleInstallContext::asDependency(function (): void {
            $this->artisan('noerd:install-zz-required-dep', ['--force' => true])
                ->expectsConfirmation('Should Zz Required Dep be installed as a hidden app (not shown in main navigation)?', 'no')
                ->expectsQuestion('App title', 'Zz Required Dep')
                ->expectsOutputToContain("Tenant assignment for 'Zz Required Dep' follows the app that requires it.")
                ->expectsConfirmation('Would you like to run php artisan migrate now?', 'no')
                ->expectsConfirmation('Would you like to run "npm run build" to compile frontend assets?', 'no')
                ->assertExitCode(0);
        });

        expect(TenantApp::where('name', 'ZZ-REQUIRED-DEP')->first()->tenants()->count())->toBe(0);
    });

    it('asks about tenants again once the dependency install finished', function (): void {
        ModuleInstallContext::asDependency(fn(): null => null);

        expect(ModuleInstallContext::isDependencyInstall())->toBeFalse();
    });
});
