<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Noerd\Commands\MakeModuleCommand;
use Noerd\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Yaml\Yaml;

uses(TestCase::class);

/**
 * The noerd:make-module scaffolder generates the module plumbing — package, provider,
 * install/update commands, dashboard, routes, navigation — and NO model: lists
 * and details are generated per model by noerd:make-resource. The stubs are
 * rendered directly so the tests never touch the real composer.json or
 * app-modules (the real command writes both); the composer.json update is
 * exercised against a temporary base path.
 */
beforeEach(function (): void {
    $this->basePath = storage_path('framework/testing/zz-make-module');
    File::deleteDirectory($this->basePath);

    $this->command = app(MakeModuleCommand::class);
    $this->command->setOutput(new OutputStyle(
        new ArrayInput([]),
        new NullOutput(),
    ));

    foreach ([
        'moduleName' => 'zz-widget',
        'moduleNameStudly' => 'ZzWidget',
        'appTitle' => "Zz Widget's Shop",
        'appIcon' => 'heroicon:outline:cube',
        'basePath' => $this->basePath,
    ] as $property => $value) {
        $reflection = new ReflectionProperty($this->command, $property);
        $reflection->setValue($this->command, $value);
    }

    $this->renderStub = function (string $stub): string {
        $method = new ReflectionMethod($this->command, 'getStub');

        return $method->invoke($this->command, $stub);
    };
});

afterEach(function (): void {
    File::deleteDirectory($this->basePath);
});

it('ships no model, list or detail stub — resources come from noerd:make-resource', function (): void {
    $stubs = array_map('basename', glob(dirname(__DIR__, 2) . '/src/Commands/stubs/module/*.stub') ?: []);

    expect($stubs)->not->toContain('model.stub', 'migration.stub', 'list.stub', 'detail.stub', 'list-yaml.stub', 'detail-yaml.stub', 'icon.stub');
});

it('generates a guarded dashboard route and a matching navigation', function (): void {
    $routes = ($this->renderStub)('routes.stub');
    $navigation = ($this->renderStub)('navigation.stub');

    // The app's main route opens the module dashboard, exactly like a root app.
    expect($routes)
        ->toContain("['noerd', 'app-access:zz-widget']")
        ->toContain("Route::livewire('zz-widget', 'zz-widget::zz-widget-dashboard')->name('zz-widget');")
        ->not->toContain('-list')
        ->not->toContain('-detail');

    // The app title is the navigation title (YAML-escaped), the dashboard is the only entry.
    $parsed = Yaml::parse($navigation);
    expect($parsed[0]['title'])->toBe("Zz Widget's Shop")
        ->and($parsed[0]['name'])->toBe('zz-widget')
        ->and($parsed[0]['route'])->toBe('zz-widget')
        ->and($parsed[0]['block_menus'][0]['navigations'])->toBe([[
            'title' => 'Dashboard',
            'route' => 'zz-widget',
            'heroicon' => 'home',
        ]]);
});

it('generates the module dashboard from the shared dashboard template', function (): void {
    File::ensureDirectoryExists("{$this->basePath}/resources/views/components");

    $method = new ReflectionMethod($this->command, 'createDashboard');
    $method->invoke($this->command);

    $dashboard = "{$this->basePath}/resources/views/components/zz-widget-dashboard.blade.php";

    expect(File::exists($dashboard))->toBeTrue()
        ->and(File::get($dashboard))->toBe(File::get(dirname(__DIR__, 2) . '/src/Commands/stubs/resource/dashboard.blade.stub'))
        ->and(fn() => Blade::compileString(File::get($dashboard)))->not->toThrow(Exception::class);
});

it('generates the tenant app migration stub the install command publishes', function (): void {
    File::ensureDirectoryExists("{$this->basePath}/app-configs/stubs");

    $method = new ReflectionMethod($this->command, 'createTenantAppMigrationStub');
    $method->invoke($this->command);

    // HasModuleInstallation::getMigrationStubPath() = dirname(sourceDir) . '/stubs/add_{key}_tenant_app.php.stub'
    $stub = File::get("{$this->basePath}/app-configs/stubs/add_zz-widget_tenant_app.php.stub");

    // The APP_* placeholders are filled by publishMigration() at install time.
    expect($stub)
        ->toContain("DB::table('tenant_apps')")
        ->toContain('{{APP_TITLE}}')
        ->toContain('{{APP_NAME}}')
        ->toContain('{{APP_ICON}}')
        ->toContain('{{APP_ROUTE}}');
});

it('generates a composer.json requiring the scaffolding core version', function (): void {
    $composer = json_decode(($this->renderStub)('composer.stub'), true, 512, JSON_THROW_ON_ERROR);

    expect($composer['require']['noerd/noerd'])->toMatch('/^\^\d+\.\d+$/')
        ->and($composer['require'])->toHaveKey('php')
        ->and($composer['license'])->toBe('MIT')
        // Test-only autoloading never ships in a consumer's production autoloader.
        ->and($composer['autoload']['psr-4'])->not->toHaveKey('Noerd\\ZzWidget\\Tests\\')
        ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Noerd\\ZzWidget\\Tests\\');
});

it('registers the chosen heroicon and app title through the install command', function (): void {
    $content = ($this->renderStub)('install-command.stub');

    // A module ships no icon file: the tenant app icon is a heroicon (PHP-escaped title).
    expect($content)
        ->toContain("return 'heroicon:outline:cube';")
        ->toContain("return 'Zz Widget\\'s Shop';")
        ->toContain("return 'zz-widget';")
        ->toContain('{--scaffold')
        ->not->toContain('icons.app');
});

it('generates only a de.json translation with English-text keys', function (): void {
    File::ensureDirectoryExists("{$this->basePath}/resources/lang");

    $method = new ReflectionMethod($this->command, 'createTranslations');
    $method->invoke($this->command);

    expect(File::exists("{$this->basePath}/resources/lang/de.json"))->toBeTrue()
        ->and(File::exists("{$this->basePath}/resources/lang/en.json"))->toBeFalse();

    $translations = json_decode(File::get("{$this->basePath}/resources/lang/de.json"), true);

    expect($translations)->not->toBeEmpty()
        ->and(array_keys($translations))->each->not->toContain('_label_');
});

it('generates an update command that runs the idempotent module update', function (): void {
    $content = ($this->renderStub)('update-command.stub');

    expect($content)
        ->toContain('class ZzWidgetUpdateCommand extends ZzWidgetInstallCommand')
        ->toContain("'noerd:update-zz-widget")
        ->toContain('return $this->runModuleUpdate();')
        ->not->toContain('runModuleInstallation');
});

it('registers install and update command in the service provider', function (): void {
    $content = ($this->renderStub)('service-provider.stub');

    expect($content)
        ->toContain('ZzWidgetInstallCommand::class,')
        ->toContain('ZzWidgetUpdateCommand::class,');
});

it('generates a blade-renderable boost guideline for the module', function (): void {
    $content = ($this->renderStub)('boost-guideline.stub');

    expect($content)
        ->toStartWith('@verbatim')
        ->toContain('## ZzWidget Module')
        ->toContain('noerd:install-zz-widget')
        ->toContain('noerd:update-zz-widget')
        ->toContain('noerd:make-resource {Model} --app=zz-widget')
        ->toContain('zz-widget-dashboard.blade.php')
        ->toContain('`zz_widget_{entities}`')
        ->not->toContain('{{module-name}}');

    expect(\Illuminate\Support\Facades\Blade::render($content))
        ->toStartWith('## ZzWidget Module')
        ->not->toContain('@verbatim');
});

it('generates AGENTS.md and a CLAUDE.md importing it', function (): void {
    expect(($this->renderStub)('agents.stub'))
        ->toContain('# AGENTS.md — noerd/zz-widget')
        ->toContain('noerd:install-zz-widget')
        ->toContain('noerd:update-zz-widget')
        ->toContain('noerd:make-resource {Model} --app=zz-widget')
        ->not->toContain('{{ModuleName}}');

    expect(($this->renderStub)('claude.stub'))->toBe("@AGENTS.md\n");
});

it('adds the module to the main composer.json with plain json functions', function (): void {
    $tempBasePath = storage_path('framework/testing/zz-make-module-host');
    File::ensureDirectoryExists($tempBasePath);
    File::put($tempBasePath . '/composer.json', json_encode([
        'name' => 'zz/host',
        'require' => [
            'zz/aaa' => '*',
            'php' => '^8.3',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    $originalBasePath = $this->app->basePath();
    $this->app->setBasePath($tempBasePath);

    try {
        $method = new ReflectionMethod($this->command, 'updateMainComposerJson');
        $method->invoke($this->command);
        // A second run must be a no-op (the requirement already exists)
        $method->invoke($this->command);
    } finally {
        $this->app->setBasePath($originalBasePath);
    }

    $content = File::get($tempBasePath . '/composer.json');
    $definition = json_decode($content, true);

    expect($definition['require'])->toHaveKey('noerd/zz-widget', '*')
        ->and(array_keys($definition['require']))->toBe(['php', 'noerd/zz-widget', 'zz/aaa'])
        ->and($content)->toEndWith("\n");

    File::deleteDirectory($tempBasePath);
});

it('leaves no placeholder in a rendered stub', function (string $stub): void {
    $content = ($this->renderStub)($stub);

    // A placeholder is `{{token}}` without spaces — Blade echoes (`{{ __('…') }}`,
    // `{{ $attributes }}`) never look like that.
    expect($content)->not->toMatch('/\{\{[A-Za-z][A-Za-z0-9_-]*\}\}/')
        ->and($content)->not->toContain('_label_')
        ->and($content)->not->toBe('');
})->with(fn(): array => array_values(array_filter(
    array_map('basename', glob(dirname(__DIR__, 2) . '/src/Commands/stubs/module/*.stub') ?: []),
    // The tenant-app migration keeps its {{APP_*}} placeholders for the install command.
    fn(string $stub): bool => $stub !== 'tenant-app-migration.stub',
)));

it('generates YAML files that parse', function (string $stub): void {
    expect(Yaml::parse(($this->renderStub)($stub)))->toBeArray()->not->toBeEmpty();
})->with(['navigation.stub']);

it('generates a de.json translation file that parses', function (): void {
    expect(json_decode(($this->renderStub)('lang-de.stub'), true, 512, JSON_THROW_ON_ERROR))
        ->toBeArray()
        ->not->toBeEmpty();
});
