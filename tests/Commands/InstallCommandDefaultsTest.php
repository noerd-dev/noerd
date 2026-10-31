<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Noerd\Commands\Concerns\HasModuleInstallation;
use Noerd\Commands\Concerns\InstallsNoerdModule;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/*
 | A module command declares its signature and the getters — handle() and the
 | shared options come from the install traits.
 */

class ZzDefaultsInstallCommand extends Command
{
    use HasModuleInstallation;

    public static array $ran = [];

    protected $signature = 'noerd:install-zz-defaults';

    protected $description = 'Fixture';

    protected function runModuleInstallation(): int
    {
        self::$ran[] = 'install:' . $this->getName();

        return Command::SUCCESS;
    }

    protected function runModuleUpdate(): int
    {
        self::$ran[] = 'update:' . $this->getName();

        return Command::SUCCESS;
    }

    protected function getModuleName(): string
    {
        return 'Zz Defaults';
    }

    protected function getModuleKey(): string
    {
        return 'zz-defaults';
    }

    protected function getAppIcon(): string
    {
        return 'heroicon:outline:cube';
    }

    protected function getAppRoute(): string
    {
        return 'zz-defaults';
    }

    protected function getSourceDir(): string
    {
        return __DIR__ . '/zz-defaults/app-configs/zz-defaults';
    }
}

class ZzDefaultsUpdateCommand extends ZzDefaultsInstallCommand
{
    protected $signature = 'noerd:update-zz-defaults';
}

class ZzDefaultsOwnOptionCommand extends ZzDefaultsInstallCommand
{
    protected $signature = 'noerd:install-zz-own-option {--force : My own wording}';
}

class ZzDefaultsSupportCommand extends Command
{
    use InstallsNoerdModule;

    protected $signature = 'noerd:install-zz-support';

    protected function getModuleName(): string
    {
        return 'Zz Support';
    }
}

beforeEach(function (): void {
    ZzDefaultsInstallCommand::$ran = [];
});

it('runs the installation or the update from the trait handle(), picked by the command name', function (): void {
    $this->app[Kernel::class]->registerCommand(new ZzDefaultsInstallCommand());
    $this->app[Kernel::class]->registerCommand(new ZzDefaultsUpdateCommand());

    $this->artisan('noerd:install-zz-defaults')->assertExitCode(0);
    $this->artisan('noerd:update-zz-defaults')->assertExitCode(0);

    expect(ZzDefaultsInstallCommand::$ran)->toBe(['install:noerd:install-zz-defaults', 'update:noerd:update-zz-defaults']);
});

it('declares the shared options on a signature that lacks them', function (): void {
    $tenantApp = (new ZzDefaultsInstallCommand())->getDefinition();
    $support = (new ZzDefaultsSupportCommand())->getDefinition();

    expect($tenantApp->hasOption('force'))->toBeTrue()
        ->and($tenantApp->hasOption('migrate'))->toBeTrue()
        ->and($tenantApp->hasOption('build'))->toBeTrue()
        ->and($tenantApp->hasOption('scaffold'))->toBeTrue()
        // A support module has no frontend and no scaffold run.
        ->and($support->hasOption('force'))->toBeTrue()
        ->and($support->hasOption('migrate'))->toBeTrue()
        ->and($support->hasOption('build'))->toBeFalse()
        ->and($support->hasOption('scaffold'))->toBeFalse();
});

it('keeps an option the signature declares itself, with its own description', function (): void {
    $definition = (new ZzDefaultsOwnOptionCommand())->getDefinition();

    expect($definition->getOption('force')->getDescription())->toBe('My own wording')
        ->and($definition->hasOption('migrate'))->toBeTrue();
});

it('offers the module name as the default app title', function (): void {
    expect(\Livewire\invade(new ZzDefaultsInstallCommand())->getDefaultAppTitle())->toBe('Zz Defaults');
});
