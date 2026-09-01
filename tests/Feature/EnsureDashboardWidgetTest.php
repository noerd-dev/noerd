<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\File;
use Noerd\Tests\TestCase;
use Noerd\Traits\HasModuleInstallation;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Yaml\Yaml;

uses(TestCase::class);

function makeDashboardWidgetCommand(): Command
{
    $command = new class extends Command {
        use HasModuleInstallation;

        protected function getModuleName(): string
        {
            return 'WidgetTest';
        }

        protected function getModuleKey(): string
        {
            return 'widget-test';
        }

        protected function getDefaultAppTitle(): string
        {
            return 'Widget Test';
        }

        protected function getAppIcon(): string
        {
            return 'noerd::icons.app';
        }

        protected function getAppRoute(): string
        {
            return 'noerd-apps';
        }

        protected function getSourceDir(): string
        {
            return __DIR__;
        }
    };

    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput()));

    return $command;
}

function invokeEnsureDashboardWidget(array $widget, array $legacyComponents = []): void
{
    $command = makeDashboardWidgetCommand();
    $method = new ReflectionMethod($command, 'ensureDashboardWidget');
    $method->invoke($command, $widget, $legacyComponents);
}

beforeEach(function (): void {
    File::delete(base_path('app-configs/dashboard-widgets.yml'));
});

afterEach(function (): void {
    File::delete(base_path('app-configs/dashboard-widgets.yml'));
});

it('creates the config file with the widget when it does not exist', function (): void {
    invokeEnsureDashboardWidget([
        'policy' => 'canWidgetTest',
        'component' => 'noerd-test::dashboard-widget-test',
        'width' => 2,
        'height' => 2,
    ]);

    $config = Yaml::parseFile(base_path('app-configs/dashboard-widgets.yml'));

    expect($config['widgets'])->toHaveCount(1)
        ->and($config['widgets'][0])->toBe([
            'policy' => 'canWidgetTest',
            'component' => 'noerd-test::dashboard-widget-test',
            'width' => 2,
            'height' => 2,
        ]);
});

it('appends the widget to an existing config', function (): void {
    $path = base_path('app-configs/dashboard-widgets.yml');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, Yaml::dump([
        'widgets' => [
            ['policy' => 'canOther', 'component' => 'other::widget', 'width' => 1, 'height' => 1],
        ],
    ], 10, 2));

    invokeEnsureDashboardWidget([
        'policy' => 'canWidgetTest',
        'component' => 'noerd-test::dashboard-widget-test',
        'width' => 2,
        'height' => 2,
    ]);

    $config = Yaml::parseFile($path);

    expect($config['widgets'])->toHaveCount(2)
        ->and($config['widgets'][0]['component'])->toBe('other::widget')
        ->and($config['widgets'][1]['component'])->toBe('noerd-test::dashboard-widget-test');
});

it('does not duplicate or overwrite an existing widget with the same component', function (): void {
    $path = base_path('app-configs/dashboard-widgets.yml');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, Yaml::dump([
        'widgets' => [
            ['policy' => 'canCustomized', 'component' => 'noerd-test::dashboard-widget-test', 'width' => 3, 'height' => 1],
        ],
    ], 10, 2));

    invokeEnsureDashboardWidget([
        'policy' => 'canWidgetTest',
        'component' => 'noerd-test::dashboard-widget-test',
        'width' => 2,
        'height' => 2,
    ]);

    $config = Yaml::parseFile($path);

    expect($config['widgets'])->toHaveCount(1)
        ->and($config['widgets'][0])->toBe([
            'policy' => 'canCustomized',
            'component' => 'noerd-test::dashboard-widget-test',
            'width' => 3,
            'height' => 1,
        ]);
});

it('rewrites legacy component names to the new component', function (): void {
    $path = base_path('app-configs/dashboard-widgets.yml');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, Yaml::dump([
        'widgets' => [
            ['policy' => 'canWidgetTest', 'component' => 'noerd::legacy-widget', 'width' => 2, 'height' => 2],
        ],
    ], 10, 2));

    invokeEnsureDashboardWidget([
        'policy' => 'canWidgetTest',
        'component' => 'noerd-test::dashboard-widget-test',
        'width' => 2,
        'height' => 2,
    ], ['noerd::legacy-widget']);

    $config = Yaml::parseFile($path);

    expect($config['widgets'])->toHaveCount(1)
        ->and($config['widgets'][0]['component'])->toBe('noerd-test::dashboard-widget-test');
});
