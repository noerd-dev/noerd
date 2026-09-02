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

/*
 | An app scaffolded as a module owns its files: the generators write the Blade
 | components into the module (namespaced `{app}::`), the routes into the module
 | route file and every YAML into BOTH copies (module template + installed copy).
 */
describe('module target', function (): void {
    beforeEach(function (): void {
        $this->moduleDir = $this->hostPath . '/app-modules/zzmod';

        File::ensureDirectoryExists($this->moduleDir . '/app-configs/zzmod');
        File::ensureDirectoryExists($this->moduleDir . '/routes');
        File::ensureDirectoryExists($this->hostPath . '/app-configs/zzmod');
        // The module's PSR-4 namespace lets make-page resolve `tenant` to a model class.
        File::put($this->moduleDir . '/composer.json', json_encode([
            'name' => 'noerd/zzmod',
            'autoload' => ['psr-4' => ['Noerd\\' => 'src/']],
        ], JSON_UNESCAPED_SLASHES));
        File::put($this->moduleDir . '/routes/zzmod-routes.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");

        $navigation = implode("\n", [
            '- title: Zzmod',
            '  name: zzmod',
            '  route: zzmod',
            '  block_menus:',
            '    - title: Overview',
            '      navigations:',
            '        - title: Dashboard',
            '          route: zzmod',
            '          heroicon: home',
            '',
        ]);
        File::put($this->moduleDir . '/app-configs/zzmod/navigation.yml', $navigation);
        File::put($this->hostPath . '/app-configs/zzmod/navigation.yml', $navigation);
    });

    it('generates a resource into the module and both YAML copies', function (): void {
        expect(Artisan::call('noerd:make-resource', ['model' => Tenant::class, '--app' => 'zzmod', '--no-interaction' => true]))->toBe(0);

        $list = File::get($this->moduleDir . '/resources/views/components/tenants-list.blade.php');
        expect($list)
            ->toContain("public \$detailComponent = 'zzmod::tenant-detail';")
            ->toContain("public ?string \$detailRoute = 'zzmod.tenant.detail';");
        expect(File::exists($this->moduleDir . '/resources/views/components/tenant-detail.blade.php'))->toBeTrue();

        $routes = File::get($this->moduleDir . '/routes/zzmod-routes.php');
        expect($routes)
            ->toContain("Route::livewire('zzmod/tenants', 'zzmod::tenants-list')->name('zzmod.tenants');")
            ->toContain("Route::livewire('zzmod/tenant/{modelId}', 'zzmod::tenant-detail')->name('zzmod.tenant.detail');")
            ->toContain("['noerd', 'app-access:zzmod']");

        foreach (['app-modules/zzmod/app-configs/zzmod', 'app-configs/zzmod'] as $base) {
            expect(File::exists("{$this->hostPath}/{$base}/lists/tenants-list.yml"))->toBeTrue("{$base} list yaml")
                ->and(File::exists("{$this->hostPath}/{$base}/details/tenant-detail.yml"))->toBeTrue("{$base} detail yaml");

            $navigation = Yaml::parse(File::get("{$this->hostPath}/{$base}/navigation.yml"));
            $entries = $navigation[0]['block_menus'][0]['navigations'];
            expect(end($entries))->toMatchArray([
                'route' => 'zzmod.tenants',
                'newRoute' => 'zzmod.tenant.detail',
                'newComponent' => 'zzmod::tenant-detail',
            ]);
        }

        // Nothing lands in the project root.
        expect(File::glob($this->hostPath . '/resources/views/components/*'))->toBe([])
            ->and(File::get($this->hostPath . '/routes/web.php'))->toBe("<?php\n");
    });

    it('generates a page into the module with a namespaced detail reference', function (): void {
        expect(Artisan::call('noerd:make-page', ['name' => 'tenant', '--app' => 'zzmod', '--no-interaction' => true]))->toBe(0);

        expect(File::exists($this->moduleDir . '/resources/views/components/tenant-page.blade.php'))->toBeTrue()
            ->and(File::get($this->moduleDir . '/routes/zzmod-routes.php'))
            ->toContain("Route::livewire('zzmod/tenant', 'zzmod::tenant-page')->name('zzmod.tenant');");

        $pageYaml = Yaml::parse(File::get($this->moduleDir . '/app-configs/zzmod/pages/tenant-page.yml'));
        expect($pageYaml['detail'])->toBe('zzmod::tenant-detail')
            ->and(File::exists($this->hostPath . '/app-configs/zzmod/pages/tenant-page.yml'))->toBeTrue();
    });

    it('generates a dashboard into the module and links it in both navigation copies', function (): void {
        File::delete($this->moduleDir . '/app-configs/zzmod/navigation.yml');

        expect(Artisan::call('noerd:make-dashboard', ['--app' => 'zzmod', '--no-interaction' => true]))->toBe(0);

        expect(File::exists($this->moduleDir . '/resources/views/components/zzmod-dashboard.blade.php'))->toBeTrue()
            ->and(File::exists($this->hostPath . '/resources/views/components/zzmod-dashboard.blade.php'))->toBeFalse()
            ->and(File::get($this->moduleDir . '/routes/zzmod-routes.php'))
            ->toContain("Route::livewire('zzmod', 'zzmod::zzmod-dashboard')->name('zzmod.dashboard');");

        // The missing module copy is created, the existing project copy gets the entry inserted.
        expect(Yaml::parse(File::get($this->moduleDir . '/app-configs/zzmod/navigation.yml'))[0]['route'])->toBe('zzmod.dashboard');
        expect(File::get($this->hostPath . '/app-configs/zzmod/navigation.yml'))->toContain('route: zzmod.dashboard');
    });
});
