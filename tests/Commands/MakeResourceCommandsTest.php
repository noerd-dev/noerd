<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Noerd\Models\Tenant;
use Noerd\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

uses(TestCase::class);
uses(RefreshDatabase::class);

/*
 | The noerd:make-* scaffolders write into the application root. They run here
 | against a THROWAWAY base path, so nothing lands in the testbench skeleton.
 | What is proven is the generator mechanic: the artefacts exist, no stub
 | placeholder survived, generated Blade compiles and generated YAML parses.
 | The generated CONTENT (titles, field lists) is reference configuration.
 */
beforeEach(function (): void {
    $this->originalBasePath = $this->app->basePath();
    $this->hostPath = storage_path('framework/testing/zz-make-resource');

    File::deleteDirectory($this->hostPath);

    // The pieces every scaffolder expects to find in an installed application.
    File::ensureDirectoryExists($this->hostPath . '/app-modules');
    File::ensureDirectoryExists($this->hostPath . '/resources/views/components');
    File::ensureDirectoryExists($this->hostPath . '/app-configs/zzapp');
    File::ensureDirectoryExists($this->hostPath . '/routes');
    File::put($this->hostPath . '/routes/web.php', "<?php\n");
    // Blade's component tag compiler reads the application's composer.json.
    File::put($this->hostPath . '/composer.json', json_encode([
        'autoload' => ['psr-4' => ['App\\' => 'app/']],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $this->app->setBasePath($this->hostPath);
});

afterEach(function (): void {
    $this->app->setBasePath($this->originalBasePath);
    File::deleteDirectory($this->hostPath);
});

it('scaffolds usable artefacts', function (array $case): void {
    if (($case['answers'] ?? []) === []) {
        // A non-interactive run answers every scaffolding confirmation with its default.
        expect(Artisan::call($case['command'], $case['parameters']))->toBe(0);
    } else {
        $pending = $this->artisan($case['command'], $case['parameters']);

        foreach ($case['answers'] as [$question, $answer]) {
            $pending->expectsQuestion($question, $answer);
        }

        // run() executes right here — a PendingCommand kept in a variable would
        // otherwise only run when it is destructed, after the assertions below.
        $pending->assertExitCode(0)->run();
    }

    foreach (array_merge($case['blade'], $case['yaml']) as $relative) {
        $path = $this->hostPath . '/' . $relative;

        expect(File::exists($path))->toBeTrue($relative . ' was not generated');

        // A placeholder is `{{token}}` without spaces — Blade echoes never look like that.
        expect(File::get($path))->not->toMatch('/\{\{[A-Za-z][A-Za-z0-9_-]*\}\}/');
    }

    foreach ($case['blade'] as $relative) {
        expect(fn() => Blade::compileString(File::get($this->hostPath . '/' . $relative)))
            ->not->toThrow(Exception::class);
    }

    foreach ($case['yaml'] as $relative) {
        expect(Yaml::parse(File::get($this->hostPath . '/' . $relative)))
            ->toBeArray()
            ->not->toBeEmpty();
    }
})->with([
    'noerd:make-list' => [[
        'command' => 'noerd:make-list',
        'parameters' => ['model' => Tenant::class, '--app' => 'zzapp', '--no-interaction' => true],
        'blade' => ['resources/views/components/tenants-list.blade.php'],
        'yaml' => ['app-configs/zzapp/lists/tenants-list.yml'],
    ]],
    'noerd:make-detail' => [[
        'command' => 'noerd:make-detail',
        'parameters' => ['model' => Tenant::class, '--app' => 'zzapp', '--no-interaction' => true],
        'blade' => ['resources/views/components/tenant-detail.blade.php'],
        'yaml' => ['app-configs/zzapp/details/tenant-detail.yml'],
    ]],
    'noerd:make-resource' => [[
        'command' => 'noerd:make-resource',
        'parameters' => ['model' => Tenant::class, '--app' => 'zzapp', '--no-interaction' => true],
        'blade' => [
            'resources/views/components/tenants-list.blade.php',
            'resources/views/components/tenant-detail.blade.php',
        ],
        'yaml' => [
            'app-configs/zzapp/lists/tenants-list.yml',
            'app-configs/zzapp/details/tenant-detail.yml',
        ],
    ]],
    'noerd:make-page' => [[
        'command' => 'noerd:make-page',
        'parameters' => ['name' => 'zz-panel', '--app' => 'zzapp', '--no-interaction' => true],
        'blade' => ['resources/views/components/zz-panel-page.blade.php'],
        'yaml' => [],
    ]],
    'noerd:make-dashboard' => [[
        'command' => 'noerd:make-dashboard',
        'parameters' => ['--app' => 'zzapp', '--no-interaction' => true],
        'blade' => ['resources/views/components/zzapp-dashboard.blade.php'],
        'yaml' => ['app-configs/zzapp/navigation.yml'],
    ]],
    'noerd:make-collection' => [[
        'command' => 'noerd:make-collection',
        'parameters' => ['name' => 'zz-gadgets', '--app' => 'zzapp'],
        'answers' => [
            ['Title (singular)', 'Zz Gadget'],
            ['Title list (plural)', 'Zz Gadgets'],
            ['Key (uppercase)', 'ZZ_GADGETS'],
            ['Button text (for "New Entry" button)', 'New Entry'],
            ['Description (optional)', ''],
            ['Field name', 'detailData.name'],
            ['Label (or translation key)', 'Name'],
            ['Field type', 'translatableText'],
            ['Colspan (1-12)', '6'],
            ['Add another field?', false],
        ],
        'blade' => [],
        'yaml' => ['app-configs/zzapp/collections/zz-gadgets.yml'],
    ]],
]);
