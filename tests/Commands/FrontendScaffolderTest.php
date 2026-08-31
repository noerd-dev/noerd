<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Noerd\Services\FrontendScaffolder;
use Noerd\Tests\TestCase;

uses(TestCase::class);

/**
 * Scratch application root. Never the real base path — the scaffolder writes package.json,
 * vite.config.js and both entry points.
 */
function scaffoldPath(string $relative = ''): string
{
    $base = storage_path('framework/testing/frontend-scaffold');

    return $relative === '' ? $base : $base . '/' . $relative;
}

function writeScaffoldFile(string $relative, string $contents): void
{
    File::ensureDirectoryExists(dirname(scaffoldPath($relative)));
    File::put(scaffoldPath($relative), $contents);
}

/**
 * @param  array<int, array{file: string, action: string, detail: string}>  $results
 */
function actionFor(array $results, string $file): ?string
{
    foreach ($results as $result) {
        if ($result['file'] === $file) {
            return $result['action'];
        }
    }

    return null;
}

beforeEach(function (): void {
    File::deleteDirectory(scaffoldPath());
    File::ensureDirectoryExists(scaffoldPath());
});

afterEach(function (): void {
    File::deleteDirectory(scaffoldPath());
});

describe('bare project', function (): void {
    beforeEach(function (): void {
        // An API-only Laravel app: resources/ holds nothing but lang/.
        File::ensureDirectoryExists(scaffoldPath('resources/lang'));

        $this->scaffolder = new FrontendScaffolder(scaffoldPath(), 'v22.22.1');
        $this->results = $this->scaffolder->scaffold();
    });

    it('creates every missing piece of the frontend scaffold', function (): void {
        expect(actionFor($this->results, 'package.json'))->toBe(FrontendScaffolder::ACTION_CREATED)
            ->and(actionFor($this->results, 'vite.config.js'))->toBe(FrontendScaffolder::ACTION_CREATED)
            ->and(actionFor($this->results, 'resources/css/app.css'))->toBe(FrontendScaffolder::ACTION_CREATED)
            ->and(actionFor($this->results, 'resources/js/app.js'))->toBe(FrontendScaffolder::ACTION_CREATED);

        foreach (['package.json', 'vite.config.js', 'resources/css/app.css', 'resources/js/app.js'] as $file) {
            expect(File::exists(scaffoldPath($file)))->toBeTrue($file . ' was not created');
        }
    });

    it('writes a package.json that can build the noerd entry points', function (): void {
        $manifest = json_decode(File::get(scaffoldPath('package.json')), true);

        expect($manifest['private'])->toBeTrue()
            ->and($manifest['type'])->toBe('module')
            ->and($manifest['scripts']['dev'])->toBe('vite')
            ->and($manifest['scripts']['build'])->toBe('vite build')
            ->and($manifest['devDependencies'])->toHaveKeys([
                'vite',
                'laravel-vite-plugin',
                'tailwindcss',
                '@tailwindcss/vite',
                '@tailwindcss/forms',
            ]);
    });

    it('writes a vite config with both entry points and the tailwind plugin', function (): void {
        $config = File::get(scaffoldPath('vite.config.js'));

        expect($config)->toContain("import laravel from 'laravel-vite-plugin';")
            ->toContain("import tailwindcss from '@tailwindcss/vite';")
            ->toContain("'resources/css/app.css'")
            ->toContain("'resources/js/app.js'")
            ->toContain('refresh: true')
            ->toContain('tailwindcss(),');
    });

    it('writes a css entry that imports tailwind, the noerd theme and the noerd sources', function (): void {
        $css = File::get(scaffoldPath('resources/css/app.css'));

        expect($css)->toContain("@import 'tailwindcss';")
            ->toContain("@import '../../vendor/noerd/noerd/resources/css/noerd.css';")
            ->toContain("@plugin '@tailwindcss/forms';")
            ->toContain("@source '../views';")
            ->toContain("@source '../../vendor/noerd/modal/resources/views';")
            ->toContain("@source '../../vendor/noerd/noerd/resources/views';");

        // The noerd theme must override Tailwind's defaults, so it follows the tailwind import.
        expect(mb_strpos($css, '/noerd.css'))->toBeGreaterThan(mb_strpos($css, "@import 'tailwindcss';"));
    });

    it('reports every written package as still to be installed', function (): void {
        expect($this->scaffolder->missingNpmPackages())->toContain(
            'vite@^8.0',
            'laravel-vite-plugin@^3.0',
            'tailwindcss@^4.1',
            '@tailwindcss/vite@^4.1',
            '@tailwindcss/forms@^0.5.11',
        );
    });

    it('does not generate a tailwind config or a @config bridge', function (): void {
        expect(File::exists(scaffoldPath('tailwind.config.js')))->toBeFalse()
            ->and(File::get(scaffoldPath('resources/css/app.css')))->not->toContain('@config');
    });
});

describe('existing project', function (): void {
    beforeEach(function (): void {
        writeScaffoldFile('package.json', json_encode([
            'private' => true,
            'type' => 'module',
            'scripts' => [
                'build' => 'vite build --mode production',
            ],
            'dependencies' => [
                'vite' => '6.3.4',
                'laravel-vite-plugin' => '^1.0',
            ],
            'devDependencies' => [
                'prettier' => '^3.9.6',
            ],
        ], JSON_PRETTY_PRINT) . PHP_EOL);

        writeScaffoldFile('vite.config.js', <<<'JS'
        import { defineConfig } from 'vite';
        import laravel from 'laravel-vite-plugin';
        import tailwindcss from '@tailwindcss/vite';

        export default defineConfig({
            plugins: [
                laravel({
                    input: [
                        'resources/css/app.css',
                        'resources/js/app.js',
                        'app-modules/shop/resources/css/shop.css',
                    ],
                    refresh: [`resources/views/**/*`],
                }),
                tailwindcss(),
            ],
            server: { cors: true },
        });
        JS);

        writeScaffoldFile('resources/css/app.css', "@import 'tailwindcss';\n\n.custom-rule {\n    color: red;\n}\n");
        writeScaffoldFile('resources/js/app.js', "import './bootstrap';\n");

        $this->before = [
            'vite.config.js' => File::get(scaffoldPath('vite.config.js')),
            'resources/js/app.js' => File::get(scaffoldPath('resources/js/app.js')),
        ];

        $this->results = (new FrontendScaffolder(scaffoldPath(), 'v22.22.1'))->scaffold();
    });

    it('never rewrites an existing vite config or js entry point', function (): void {
        expect(File::get(scaffoldPath('vite.config.js')))->toBe($this->before['vite.config.js'])
            ->and(File::get(scaffoldPath('resources/js/app.js')))->toBe($this->before['resources/js/app.js'])
            ->and(actionFor($this->results, 'vite.config.js'))->toBe(FrontendScaffolder::ACTION_SKIPPED)
            ->and(actionFor($this->results, 'resources/js/app.js'))->toBe(FrontendScaffolder::ACTION_SKIPPED);
    });

    it('patches app.css without touching the existing rules', function (): void {
        $css = File::get(scaffoldPath('resources/css/app.css'));

        expect(actionFor($this->results, 'resources/css/app.css'))->toBe(FrontendScaffolder::ACTION_PATCHED)
            ->and($css)->toContain('.custom-rule')
            ->and($css)->toContain("@import '../../vendor/noerd/noerd/resources/css/noerd.css';")
            ->and($css)->toContain("@plugin '@tailwindcss/forms';");

        // The noerd theme import is placed directly after the tailwind import, not at the end.
        expect($css)->toStartWith("@import 'tailwindcss';\n@import '../../vendor/noerd/noerd/resources/css/noerd.css';");
    });

    it('keeps a package already declared as a runtime dependency out of devDependencies', function (): void {
        $manifest = json_decode(File::get(scaffoldPath('package.json')), true);

        expect($manifest['dependencies']['vite'])->toBe('6.3.4')
            ->and($manifest['dependencies']['laravel-vite-plugin'])->toBe('^1.0')
            ->and($manifest['devDependencies'])->not->toHaveKey('vite')
            ->and($manifest['devDependencies'])->not->toHaveKey('laravel-vite-plugin');
    });

    it('adds only the missing scripts and dependencies', function (): void {
        $manifest = json_decode(File::get(scaffoldPath('package.json')), true);

        expect($manifest['scripts']['build'])->toBe('vite build --mode production')
            ->and($manifest['scripts']['dev'])->toBe('vite')
            ->and($manifest['devDependencies'])->toHaveKeys(['prettier', 'tailwindcss', '@tailwindcss/vite', '@tailwindcss/forms'])
            ->and($manifest['devDependencies']['prettier'])->toBe('^3.9.6');
    });

    it('recognises directives the host wrote with double quotes', function (): void {
        writeScaffoldFile('resources/css/app.css', implode(PHP_EOL, [
            '@import "tailwindcss";',
            '@import "../../vendor/noerd/noerd/resources/css/noerd.css";',
            '@plugin "@tailwindcss/forms";',
            '@source "../views";',
            '@source "../../vendor/noerd/modal/resources/views";',
            '@source "../../vendor/noerd/noerd/resources/views";',
        ]) . PHP_EOL);

        $results = (new FrontendScaffolder(scaffoldPath(), 'v22.22.1'))->scaffold();
        $css = File::get(scaffoldPath('resources/css/app.css'));

        expect(actionFor($results, 'resources/css/app.css'))->toBe(FrontendScaffolder::ACTION_SKIPPED)
            ->and(mb_substr_count($css, '@source'))->toBe(3)
            ->and(mb_substr_count($css, '@plugin'))->toBe(1)
            ->and($css)->not->toContain("'");
    });

    it('leaves an invalid package.json untouched and warns', function (): void {
        writeScaffoldFile('package.json', '{ not json');

        $results = (new FrontendScaffolder(scaffoldPath(), 'v22.22.1'))->scaffold();

        expect(actionFor($results, 'package.json'))->toBe(FrontendScaffolder::ACTION_WARNING)
            ->and(File::get(scaffoldPath('package.json')))->toBe('{ not json');
    });
});

it('is idempotent across repeated runs', function (): void {
    File::ensureDirectoryExists(scaffoldPath('resources/lang'));

    (new FrontendScaffolder(scaffoldPath(), 'v22.22.1'))->scaffold();

    $files = ['package.json', 'vite.config.js', 'resources/css/app.css', 'resources/js/app.js'];
    $before = [];

    foreach ($files as $file) {
        $before[$file] = File::get(scaffoldPath($file));
    }

    $second = (new FrontendScaffolder(scaffoldPath(), 'v22.22.1'))->scaffold();

    foreach ($files as $file) {
        expect(File::get(scaffoldPath($file)))->toBe($before[$file], $file . ' changed on the second run');
        expect(actionFor($second, $file))->toBe(FrontendScaffolder::ACTION_SKIPPED);
    }
});

it('warns when an existing vite config does not build the noerd entry points', function (): void {
    $config = <<<'JS'
    import { defineConfig } from 'vite';

    export default defineConfig({
        build: { lib: { entry: 'src/index.js' } },
    });
    JS;

    writeScaffoldFile('vite.config.js', $config);

    $results = (new FrontendScaffolder(scaffoldPath(), 'v22.22.1'))->scaffold();

    expect(actionFor($results, 'vite.config.js'))->toBe(FrontendScaffolder::ACTION_WARNING)
        ->and(File::get(scaffoldPath('vite.config.js')))->toBe($config);

    $detail = collect($results)->firstWhere('file', 'vite.config.js')['detail'];

    expect($detail)->toContain('resources/css/app.css')
        ->toContain('resources/js/app.js')
        ->toContain('@tailwindcss/vite');
});

it('does not report the tailwind plugin as missing when postcss compiles tailwind', function (): void {
    writeScaffoldFile('vite.config.js', <<<'JS'
    import { defineConfig } from 'vite';
    import laravel from 'laravel-vite-plugin';

    export default defineConfig({
        plugins: [laravel({ input: ['resources/css/app.css', 'resources/js/app.js'] })],
    });
    JS);
    writeScaffoldFile('postcss.config.js', "export default { plugins: { '@tailwindcss/postcss': {} } };\n");

    $results = (new FrontendScaffolder(scaffoldPath(), 'v22.22.1'))->scaffold();

    expect(actionFor($results, 'vite.config.js'))->toBe(FrontendScaffolder::ACTION_SKIPPED);
});

describe('node compatibility', function (): void {
    it('pins the current build tooling on a supported node version', function (string $version): void {
        File::deleteDirectory(scaffoldPath());
        File::ensureDirectoryExists(scaffoldPath());

        (new FrontendScaffolder(scaffoldPath(), $version))->scaffold();

        $manifest = json_decode(File::get(scaffoldPath('package.json')), true);

        expect($manifest['devDependencies']['vite'])->toBe('^8.0')
            ->and($manifest['devDependencies']['laravel-vite-plugin'])->toBe('^3.0');
    })->with(['v20.19.0', 'v22.12.0', 'v22.22.1', 'v24.0.0']);

    it('falls back to the previous major pair on an unsupported node version', function (string $version): void {
        File::deleteDirectory(scaffoldPath());
        File::ensureDirectoryExists(scaffoldPath());

        $results = (new FrontendScaffolder(scaffoldPath(), $version))->scaffold();

        $manifest = json_decode(File::get(scaffoldPath('package.json')), true);

        expect($manifest['devDependencies']['vite'])->toBe('^7.0')
            ->and($manifest['devDependencies']['laravel-vite-plugin'])->toBe('^2.0');

        expect(collect($results)->firstWhere('file', 'package.json')['detail'])->toContain($version);
    })->with(['v18.20.0', 'v20.9.0', 'v21.7.3', 'v22.11.0']);

    it('assumes the current tooling when node cannot be detected', function (): void {
        (new FrontendScaffolder(scaffoldPath(), null))->scaffold();

        $manifest = json_decode(File::get(scaffoldPath('package.json')), true);

        expect($manifest['devDependencies']['vite'])->toBe('^8.0');
    });
});

describe('legacy tailwind config migration', function (): void {
    beforeEach(function (): void {
        writeScaffoldFile(
            'resources/css/app.css',
            "@import 'tailwindcss';\n\n@source '../views';\n@config '../../tailwind.config.js';\n",
        );

        $this->scaffolder = new FrontendScaffolder(scaffoldPath(), 'v22.22.1');
    });

    it('detects a noerd-generated config as removable', function (): void {
        writeScaffoldFile('tailwind.config.js', "export default {\n    theme: { extend: { colors: { 'brand-primary': process.env.VITE_BRAND_PRIMARY || '#000' } } },\n}\n");

        expect($this->scaffolder->hasLegacyTailwindBridge())->toBeTrue()
            ->and($this->scaffolder->legacyTailwindConfigIsUnmodified())->toBeTrue();
    });

    it('treats a customised config as host-owned', function (): void {
        writeScaffoldFile('tailwind.config.js', "export default {\n    plugins: [],\n}\n");

        expect($this->scaffolder->hasLegacyTailwindBridge())->toBeTrue()
            ->and($this->scaffolder->legacyTailwindConfigIsUnmodified())->toBeFalse();
    });

    it('removes every @config occurrence without leaving a backup', function (): void {
        // The old updateAppCss() appended its block at EOF regardless of an existing @config line,
        // so long-lived projects carry the directive twice — and possibly double-quoted.
        writeScaffoldFile('resources/css/app.css', implode(PHP_EOL, [
            "@import 'tailwindcss';",
            '@config "../../tailwind.config.js";',
            '',
            "@source '../views';",
            "@config '../../tailwind.config.js';",
        ]) . PHP_EOL);
        writeScaffoldFile('tailwind.config.js', "export default { /* VITE_BRAND_PRIMARY */ }\n");

        $this->scaffolder->removeLegacyTailwindBridge();

        expect(File::exists(scaffoldPath('tailwind.config.js')))->toBeFalse()
            ->and(File::exists(scaffoldPath('tailwind.config.js.bak')))->toBeFalse();

        $css = File::get(scaffoldPath('resources/css/app.css'));

        expect($css)->not->toContain('@config')
            ->and($css)->toContain("@source '../views';")
            ->and($css)->toContain("@import 'tailwindcss';");
    });

    it('reports no bridge when the @config line is absent', function (): void {
        writeScaffoldFile('resources/css/app.css', "@import 'tailwindcss';\n");
        writeScaffoldFile('tailwind.config.js', "export default { /* VITE_BRAND_PRIMARY */ }\n");

        expect((new FrontendScaffolder(scaffoldPath(), 'v22.22.1'))->hasLegacyTailwindBridge())->toBeFalse();
    });
});

it('registers a css variable for every color of the brand preset', function (): void {
    // Guards against a palette key that exists in the config but never becomes a Tailwind color:
    // the utility would then silently produce nothing.
    $css = File::get(dirname(__DIR__, 2) . '/resources/css/noerd.css');
    $keys = array_keys(config('noerd.brand.presets.default', []));

    expect($keys)->not->toBeEmpty();

    foreach ($keys as $key) {
        expect($css)->toContain('--color-' . $key . ':');
    }
});
