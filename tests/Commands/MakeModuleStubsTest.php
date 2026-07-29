<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Noerd\Commands\MakeModuleCommand;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The noerd:module scaffolder must generate the slim list/detail syntax
 * (reference: customers-list) — not the legacy with()/DETAIL_COMPONENT pattern.
 * The stubs are rendered directly so the test never touches composer.json or
 * app-modules (the real command writes both).
 */
beforeEach(function (): void {
    $this->basePath = storage_path('framework/testing/zz-make-module');
    File::deleteDirectory($this->basePath);

    $this->command = app(MakeModuleCommand::class);
    $this->command->setOutput(new Illuminate\Console\OutputStyle(
        new Symfony\Component\Console\Input\ArrayInput([]),
        new Symfony\Component\Console\Output\NullOutput(),
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
        ->toContain("public \$listModel = Widget::class;")
        ->toContain("public \$detailComponent = 'zz-widget::widget-detail';")
        ->toContain('<x-noerd::list/>')
        ->not->toContain('function listAction')
        ->not->toContain('DETAIL_COMPONENT')
        ->not->toContain('function with');
});

it('generates block-style YAML configs bound to detailData', function (): void {
    $listYaml = ($this->renderStub)('list-yaml.stub');
    $detailYaml = ($this->renderStub)('detail-yaml.stub');

    expect($listYaml)
        ->toContain('title: Widgets')
        ->toContain('action: listAction')
        ->not->toContain('newLabel')
        ->not->toContain('_label_');

    expect($detailYaml)
        ->toContain('name: detailData.name')
        ->toContain('name: detailData.is_active')
        ->not->toContain('{ ')
        ->not->toContain('_label_');
});

it('generates a tenant-scoped model', function (): void {
    File::ensureDirectoryExists("{$this->basePath}/src/Models");

    $method = new ReflectionMethod($this->command, 'createModel');
    $method->invoke($this->command);

    $model = File::get("{$this->basePath}/src/Models/Widget.php");

    expect($model)
        ->toContain('use Noerd\Traits\BelongsToTenant;')
        ->toContain('use BelongsToTenant;');
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
