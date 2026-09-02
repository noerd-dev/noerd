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
 * The noerd:module scaffolder must generate the slim list/detail syntax
 * (reference: customers-list) — not the legacy with()/DETAIL_COMPONENT pattern.
 * The stubs are rendered directly so the tests never touch the real composer.json
 * or app-modules (the real command writes both); the composer.json update is
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
        'modelName' => 'widget',
        'modelNameStudly' => 'Widget',
        'modelNamePlural' => 'widgets',
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

it('generates a slim list component', function (): void {
    $content = ($this->renderStub)('list.stub');

    expect($content)
        ->toContain('public $listModel = Widget::class;')
        ->toContain("public ?string \$detailRoute = 'zz-widget.widget.detail';")
        ->toContain("public \$detailComponent = 'zz-widget::widget-detail';")
        ->toContain('<x-noerd::list/>')
        ->not->toContain('function listAction')
        ->not->toContain('DETAIL_COMPONENT')
        ->not->toContain('function with');
});

it('generates block-style YAML configs bound to detailData', function (): void {
    $listYamlSource = ($this->renderStub)('list-yaml.stub');
    $detailYamlSource = ($this->renderStub)('detail-yaml.stub');

    // Block style throughout — flow style (`{ key: value }`) is never written.
    expect($listYamlSource)->not->toContain('{ ')
        ->and($detailYamlSource)->not->toContain('{ ');

    $listYaml = Yaml::parse($listYamlSource);
    $detailYaml = Yaml::parse($detailYamlSource);

    // The generated list points its "New …" action at the generated detail route.
    expect($listYaml['actions'][0]['route'])->toBe('zz-widget.widget.detail');

    // Every detail field binds into $detailData — the values themselves are
    // reference configuration and may change with the stub.
    expect($detailYaml['fields'])->not->toBeEmpty();

    foreach ($detailYaml['fields'] as $field) {
        expect($field['name'])->toStartWith('detailData.');
    }
});

it('generates a tenant-scoped model', function (): void {
    File::ensureDirectoryExists("{$this->basePath}/src/Models");

    $method = new ReflectionMethod($this->command, 'createModel');
    $method->invoke($this->command);

    $model = File::get("{$this->basePath}/src/Models/Widget.php");

    expect($model)
        ->toContain('use Noerd\Traits\BelongsToTenant;')
        ->toContain('use BelongsToTenant;')
        ->toContain("protected \$table = 'zz_widget_widgets';")
        ->toContain('protected $guarded = [];');
});

it('generates a prefixed migration with a custom_attributes column', function (): void {
    File::ensureDirectoryExists("{$this->basePath}/database/migrations");

    $method = new ReflectionMethod($this->command, 'createMigration');
    $method->invoke($this->command);

    $migration = File::get(File::files("{$this->basePath}/database/migrations")[0]->getPathname());

    expect($migration)
        ->toContain("Schema::create('zz_widget_widgets'")
        ->toContain("\$table->json('custom_attributes')->nullable();");
});

it('generates guarded, namespaced routes and a matching navigation', function (): void {
    $routes = ($this->renderStub)('routes.stub');
    $navigation = ($this->renderStub)('navigation.stub');

    expect($routes)
        ->toContain("['noerd', 'app-access:zz-widget']")
        ->toContain("Route::livewire('zz-widget', 'zz-widget::widgets-list')->name('zz-widget');")
        ->toContain("Route::livewire('zz-widget/widget/{modelId}', 'zz-widget::widget-detail')->name('zz-widget.widget.detail');");

    expect($navigation)
        ->toContain('route: zz-widget.widgets')
        ->toContain('newRoute: zz-widget.widget.detail')
        ->toContain('newComponent: zz-widget::widget-detail');
});

it('generates a composer.json requiring the scaffolding core version', function (): void {
    $composer = json_decode(($this->renderStub)('composer.stub'), true, 512, JSON_THROW_ON_ERROR);

    expect($composer['require']['noerd/noerd'])->toMatch('/^\^\d+\.\d+$/')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Noerd\\ZzWidget\\Tests\\')
        ->and($composer)->not->toHaveKey('license');
});

it('generates the app icon the install command references', function (): void {
    File::ensureDirectoryExists("{$this->basePath}/resources/views/components/icons");

    $method = new ReflectionMethod($this->command, 'createAppIcon');
    $method->invoke($this->command);

    expect(File::exists("{$this->basePath}/resources/views/components/icons/app.blade.php"))->toBeTrue()
        ->and(($this->renderStub)('install-command.stub'))->toContain("return 'zz-widget::icons.app';");
});

it('generates only a de.json translation with English-text keys', function (): void {
    File::ensureDirectoryExists("{$this->basePath}/resources/lang");

    $method = new ReflectionMethod($this->command, 'createTranslations');
    $method->invoke($this->command);

    expect(File::exists("{$this->basePath}/resources/lang/de.json"))->toBeTrue()
        ->and(File::exists("{$this->basePath}/resources/lang/en.json"))->toBeFalse();

    $translations = json_decode(File::get("{$this->basePath}/resources/lang/de.json"), true);

    expect($translations)->toHaveKey('New Widget')
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
        ->toContain('widgets-list.blade.php')
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
})->with(fn(): array => array_map(
    'basename',
    glob(dirname(__DIR__, 2) . '/src/Commands/stubs/module/*.stub') ?: [],
));

it('generates blade files that compile', function (string $stub): void {
    expect(fn() => Blade::compileString(($this->renderStub)($stub)))->not->toThrow(Exception::class);
})->with(['list.stub', 'detail.stub', 'icon.stub']);

it('generates YAML files that parse', function (string $stub): void {
    expect(Yaml::parse(($this->renderStub)($stub)))->toBeArray()->not->toBeEmpty();
})->with(['list-yaml.stub', 'detail-yaml.stub', 'navigation.stub']);

it('generates a de.json translation file that parses', function (): void {
    expect(json_decode(($this->renderStub)('lang-de.stub'), true, 512, JSON_THROW_ON_ERROR))
        ->toBeArray()
        ->not->toBeEmpty();
});
