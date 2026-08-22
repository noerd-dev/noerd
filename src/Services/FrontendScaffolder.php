<?php

declare(strict_types=1);

namespace Noerd\Services;

use JsonException;

/**
 * Creates or patches the Vite/Tailwind frontend scaffold of a host application.
 *
 * noerd's layouts render `@vite(['resources/css/app.css', 'resources/js/app.js'])`, so a host
 * project needs a package.json, a vite.config.js and both entry points before it can build. A
 * project generated from a Laravel starter kit already ships them; an API-only application does
 * not. This service creates whatever is missing and patches whatever exists, without ever
 * overwriting a file the host owns.
 *
 * Every step reports one result row so the install command can print an auditable summary.
 */
final class FrontendScaffolder
{
    public const ACTION_CREATED = 'created';

    public const ACTION_PATCHED = 'patched';

    public const ACTION_SKIPPED = 'skipped';

    public const ACTION_WARNING = 'warning';

    /**
     * The two entry points every noerd layout references.
     */
    public const CSS_ENTRY = 'resources/css/app.css';

    public const JS_ENTRY = 'resources/js/app.js';

    /**
     * Build tooling emitted into a package.json that does not exist yet.
     *
     * laravel-vite-plugin 3.x peers vite ^8 and requires Node ^20.19 || >=22.12, so older Node
     * versions get the previous major pair instead (see LEGACY_BUILD_PACKAGES).
     *
     * @var array<string, string>
     */
    private const BUILD_PACKAGES = [
        'vite' => '^8.0',
        'laravel-vite-plugin' => '^3.0',
    ];

    /**
     * @var array<string, string>
     */
    private const LEGACY_BUILD_PACKAGES = [
        'vite' => '^7.0',
        'laravel-vite-plugin' => '^2.0',
    ];

    /**
     * Tailwind tooling, independent of the Vite major (@tailwindcss/vite peers vite ^5.2 - ^8).
     *
     * @var array<string, string>
     */
    private const TAILWIND_PACKAGES = [
        'tailwindcss' => '^4.1',
        '@tailwindcss/vite' => '^4.1',
        '@tailwindcss/forms' => '^0.5.11',
    ];

    /**
     * @var array<string, string>
     */
    private const SCRIPTS = [
        'dev' => 'vite',
        'build' => 'vite build',
    ];

    /**
     * Lines appended to resources/css/app.css, each one checked and added individually so a
     * partially patched file never gains a duplicate.
     *
     * @var array<int, string>
     */
    private const CSS_LINES = [
        "@plugin '@tailwindcss/forms';",
        "@source '../views';",
        "@source '../../vendor/noerd/modal/resources/views';",
        "@source '../../vendor/noerd/noerd/resources/views';",
    ];

    private const NOERD_CSS_IMPORT = "@import '../../vendor/noerd/noerd/resources/css/noerd.css';";

    private const TAILWIND_CSS_IMPORT = "@import 'tailwindcss';";

    /**
     * The Tailwind 3 config bridge noerd used to inject before the brand palette became CSS-first.
     */
    private const LEGACY_CONFIG_DIRECTIVE = "@config '../../tailwind.config.js';";

    /**
     * @var array<int, array{file: string, action: string, detail: string}>
     */
    private array $results = [];

    /**
     * @var array<int, string>
     */
    private array $missingNpmPackages = [];

    /**
     * @param  string  $basePath  Application root the scaffold is written into.
     * @param  string|null  $nodeVersion  Detected `node -v` output; null falls back to the current tooling.
     */
    public function __construct(
        private readonly string $basePath,
        private readonly ?string $nodeVersion = null,
    ) {}

    /**
     * Create every missing piece of the frontend scaffold and patch the pieces that exist.
     *
     * @return array<int, array{file: string, action: string, detail: string}>
     */
    public function scaffold(): array
    {
        $this->results = [];
        $this->missingNpmPackages = [];

        $this->ensurePackageJson();
        $this->ensureViteConfig();
        $this->ensureAppCss();
        $this->ensureAppJs();

        return $this->results;
    }

    /**
     * npm specs that scaffold() added to package.json and that still need to be installed.
     *
     * @return array<int, string>
     */
    public function missingNpmPackages(): array
    {
        return $this->missingNpmPackages;
    }

    /**
     * Whether a legacy noerd-generated tailwind.config.js is still bridged into app.css.
     *
     * Brand colors ship as `--color-brand-*` in the package's noerd.css since the palette became
     * CSS-first, so the generated config and its `@config` line are obsolete.
     */
    public function hasLegacyTailwindBridge(): bool
    {
        $css = $this->read(self::CSS_ENTRY);

        return $css !== null
            && $this->containsDirective($this->splitLines($css), self::LEGACY_CONFIG_DIRECTIVE)
            && file_exists($this->path('tailwind.config.js'));
    }

    /**
     * Whether the host still uses the tailwind.config.js exactly as noerd generated it.
     *
     * The `VITE_BRAND_PRIMARY` marker only ever came from noerd's own generator, so a config
     * without it was customised by the host and must never be removed automatically.
     */
    public function legacyTailwindConfigIsUnmodified(): bool
    {
        $config = $this->read('tailwind.config.js');

        return $config !== null && str_contains($config, 'VITE_BRAND_PRIMARY');
    }

    /**
     * Remove the obsolete tailwind.config.js bridge, keeping a .bak copy of the config.
     */
    public function removeLegacyTailwindBridge(): void
    {
        $configPath = $this->path('tailwind.config.js');

        if (file_exists($configPath)) {
            copy($configPath, $configPath . '.bak');
            unlink($configPath);
        }

        $css = $this->read(self::CSS_ENTRY);

        if ($css === null) {
            return;
        }

        // A project may carry the directive more than once — updateAppCss() used to append its
        // block at EOF regardless of an existing @config line — so every occurrence is dropped.
        $needle = $this->normalizeDirective(self::LEGACY_CONFIG_DIRECTIVE);
        $kept = array_filter(
            $this->splitLines($css),
            fn(string $line): bool => $this->normalizeDirective($line) !== $needle,
        );

        $this->write(self::CSS_ENTRY, implode(PHP_EOL, $this->trimTrailingBlankLines(array_values($kept))) . PHP_EOL);
    }

    /**
     * Create package.json, or add the missing scripts and build dependencies to an existing one.
     */
    private function ensurePackageJson(): void
    {
        $packages = array_merge($this->buildPackages(), self::TAILWIND_PACKAGES);
        $existing = $this->read('package.json');

        if ($existing === null) {
            $this->write('package.json', $this->renderJson([
                'private' => true,
                'type' => 'module',
                'scripts' => self::SCRIPTS,
                'devDependencies' => $this->sortByKey($packages),
            ]));

            $this->missingNpmPackages = $this->toNpmSpecs($packages);

            $this->record('package.json', self::ACTION_CREATED, $this->buildToolingDetail());

            return;
        }

        try {
            $manifest = json_decode($existing, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->record('package.json', self::ACTION_WARNING, 'Invalid JSON, left untouched: ' . $exception->getMessage());

            return;
        }

        if (! is_array($manifest)) {
            $this->record('package.json', self::ACTION_WARNING, 'Unexpected structure, left untouched.');

            return;
        }

        $added = [];

        foreach (self::SCRIPTS as $name => $command) {
            if (! isset($manifest['scripts'][$name])) {
                $manifest['scripts'][$name] = $command;
                $added[] = 'script ' . $name;
            }
        }

        foreach ($packages as $name => $constraint) {
            // A package already declared as a runtime dependency must not be duplicated into
            // devDependencies, and an existing version range is never changed.
            if (isset($manifest['dependencies'][$name]) || isset($manifest['devDependencies'][$name])) {
                continue;
            }

            $manifest['devDependencies'][$name] = $constraint;
            $this->missingNpmPackages[] = $name . '@' . $constraint;
            $added[] = $name;
        }

        if (isset($manifest['devDependencies'])) {
            $manifest['devDependencies'] = $this->sortByKey($manifest['devDependencies']);
        }

        if ($added === []) {
            $this->record('package.json', self::ACTION_SKIPPED, 'Scripts and build dependencies already present.');

            return;
        }

        $this->write('package.json', $this->renderJson($manifest));

        $this->record('package.json', self::ACTION_PATCHED, 'Added ' . implode(', ', $added) . '.');
    }

    /**
     * Create vite.config.js when missing; an existing config is only inspected, never rewritten.
     */
    private function ensureViteConfig(): void
    {
        $existing = $this->read('vite.config.js');

        if ($existing === null) {
            $this->write('vite.config.js', $this->renderViteConfig());

            $this->record('vite.config.js', self::ACTION_CREATED, 'Entry points: ' . self::CSS_ENTRY . ', ' . self::JS_ENTRY . '.');

            return;
        }

        $missing = [];

        foreach ([self::CSS_ENTRY, self::JS_ENTRY] as $entry) {
            if (! str_contains($existing, $entry)) {
                $missing[] = $entry;
            }
        }

        // Tailwind may also be wired through PostCSS instead of the Vite plugin — only report it
        // as missing when neither route is configured.
        if (! str_contains($existing, '@tailwindcss/vite') && ! $this->hasPostcssTailwind()) {
            $missing[] = '@tailwindcss/vite plugin';
        }

        if ($missing !== []) {
            $this->record(
                'vite.config.js',
                self::ACTION_WARNING,
                'Left untouched, but missing: ' . implode(', ', $missing) . '. noerd layouts need both entry points.',
            );

            return;
        }

        $this->record('vite.config.js', self::ACTION_SKIPPED, 'Already builds the noerd entry points.');
    }

    /**
     * Whether the host compiles Tailwind through PostCSS rather than the Vite plugin.
     */
    private function hasPostcssTailwind(): bool
    {
        foreach (['postcss.config.js', 'postcss.config.mjs', 'postcss.config.cjs', 'postcss.config.json'] as $candidate) {
            $contents = $this->read($candidate);

            if ($contents !== null && str_contains($contents, 'tailwindcss')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create the CSS entry point, or inject the noerd imports and source paths into an existing one.
     */
    private function ensureAppCss(): void
    {
        $existing = $this->read(self::CSS_ENTRY);

        if ($existing === null) {
            $this->write(self::CSS_ENTRY, $this->renderAppCss());

            $this->record(self::CSS_ENTRY, self::ACTION_CREATED, 'Tailwind entry with the noerd theme and source paths.');

            return;
        }

        $lines = $this->splitLines($existing);
        $added = [];

        // The noerd base theme must sit right after Tailwind so its values override the defaults.
        if (! $this->containsDirective($lines, self::NOERD_CSS_IMPORT)) {
            $anchor = $this->indexOfDirective($lines, self::TAILWIND_CSS_IMPORT);

            if ($anchor === null) {
                array_unshift($lines, self::NOERD_CSS_IMPORT);
            } else {
                array_splice($lines, $anchor + 1, 0, [self::NOERD_CSS_IMPORT]);
            }

            $added[] = 'noerd theme import';
        }

        // Checked directive by directive so a partially patched file never gains a duplicate.
        $appended = [];

        foreach (self::CSS_LINES as $line) {
            if (! $this->containsDirective($lines, $line)) {
                $appended[] = $line;
            }
        }

        if ($appended !== []) {
            $lines = array_merge($this->trimTrailingBlankLines($lines), [''], $appended);
            $added[] = count($appended) . ' source/plugin line(s)';
        }

        if ($added === []) {
            $this->record(self::CSS_ENTRY, self::ACTION_SKIPPED, 'noerd styles already present.');

            return;
        }

        $this->write(self::CSS_ENTRY, implode(PHP_EOL, $lines) . PHP_EOL);

        $this->record(self::CSS_ENTRY, self::ACTION_PATCHED, 'Added ' . implode(', ', $added) . '.');
    }

    /**
     * @return array<int, string>
     */
    private function splitLines(string $contents): array
    {
        $lines = preg_split('/\R/', mb_rtrim($contents, "\r\n"));

        return $lines === false ? [] : $lines;
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function trimTrailingBlankLines(array $lines): array
    {
        while ($lines !== [] && mb_trim(end($lines)) === '') {
            array_pop($lines);
        }

        return array_values($lines);
    }

    /**
     * Compare CSS directives independent of quote style and spacing, so a host that writes
     * `@source "../views";` is not given a single-quoted duplicate of the same line.
     */
    private function normalizeDirective(string $line): string
    {
        return (string) preg_replace('/\s+/', ' ', mb_trim(str_replace('"', "'", $line)));
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function containsDirective(array $lines, string $directive): bool
    {
        return $this->indexOfDirective($lines, $directive) !== null;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function indexOfDirective(array $lines, string $directive): ?int
    {
        $needle = $this->normalizeDirective($directive);

        foreach ($lines as $index => $line) {
            if ($this->normalizeDirective($line) === $needle) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Create the JS entry point when missing. An existing file is never touched.
     */
    private function ensureAppJs(): void
    {
        if ($this->read(self::JS_ENTRY) !== null) {
            $this->record(self::JS_ENTRY, self::ACTION_SKIPPED, 'Already present.');

            return;
        }

        $this->write(self::JS_ENTRY, $this->renderAppJs());

        $this->record(self::JS_ENTRY, self::ACTION_CREATED, 'Empty entry module; noerd ships its own bundle.');
    }

    private function renderViteConfig(): string
    {
        return <<<JS
        import { defineConfig } from 'vite';
        import laravel from 'laravel-vite-plugin';
        import tailwindcss from '@tailwindcss/vite';

        export default defineConfig({
            plugins: [
                laravel({
                    input: ['resources/css/app.css', 'resources/js/app.js'],
                    refresh: true,
                }),
                tailwindcss(),
            ],
        });

        JS;
    }

    private function renderAppCss(): string
    {
        $lines = implode(PHP_EOL, self::CSS_LINES);

        return self::TAILWIND_CSS_IMPORT . PHP_EOL
            . self::NOERD_CSS_IMPORT . PHP_EOL
            . PHP_EOL
            . $lines . PHP_EOL;
    }

    private function renderAppJs(): string
    {
        return <<<'JS'
        // Application entry point.
        //
        // Livewire ships its own runtime (including Alpine) and noerd loads its compiled frontend
        // bundle separately through <x-noerd::assets />, so nothing has to be imported here.
        // Add your application's own JavaScript below.

        JS;
    }

    /**
     * @return array<string, string>
     */
    private function buildPackages(): array
    {
        return $this->nodeSupportsCurrentTooling() ? self::BUILD_PACKAGES : self::LEGACY_BUILD_PACKAGES;
    }

    private function buildToolingDetail(): string
    {
        if ($this->nodeSupportsCurrentTooling()) {
            return 'vite ' . self::BUILD_PACKAGES['vite'] . ', laravel-vite-plugin ' . self::BUILD_PACKAGES['laravel-vite-plugin'] . '.';
        }

        return 'Node ' . ($this->nodeVersion ?? 'unknown') . ' is below ^20.19 || >=22.12, pinned vite '
            . self::LEGACY_BUILD_PACKAGES['vite'] . ' / laravel-vite-plugin ' . self::LEGACY_BUILD_PACKAGES['laravel-vite-plugin'] . '.';
    }

    /**
     * laravel-vite-plugin 3.x declares engines.node ^20.19.0 || >=22.12.0.
     */
    private function nodeSupportsCurrentTooling(): bool
    {
        if ($this->nodeVersion === null) {
            return true;
        }

        if (preg_match('/(\d+)\.(\d+)\.(\d+)/', $this->nodeVersion, $matches) !== 1) {
            return true;
        }

        $major = (int) $matches[1];
        $minor = (int) $matches[2];

        if ($major === 20) {
            return $minor >= 19;
        }

        if ($major === 21) {
            return false;
        }

        if ($major === 22) {
            return $minor >= 12;
        }

        return $major > 22;
    }

    /**
     * @param  array<string, string>  $packages
     * @return array<int, string>
     */
    private function toNpmSpecs(array $packages): array
    {
        $specs = [];

        foreach ($packages as $name => $constraint) {
            $specs[] = $name . '@' . $constraint;
        }

        return $specs;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sortByKey(array $values): array
    {
        ksort($values);

        return $values;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function renderJson(array $manifest): string
    {
        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }

    private function record(string $file, string $action, string $detail): void
    {
        $this->results[] = [
            'file' => $file,
            'action' => $action,
            'detail' => $detail,
        ];
    }

    private function path(string $relative): string
    {
        return mb_rtrim($this->basePath, '/') . '/' . $relative;
    }

    private function read(string $relative): ?string
    {
        $path = $this->path($relative);

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private function write(string $relative, string $contents): void
    {
        $path = $this->path($relative);
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $contents);
    }
}
