<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Assert;

if (! function_exists('requiredLayoutFields')) {
    /**
     * Field names marked required in the component's pageLayout.
     *
     * Returns the layout keys (incl. the "detailData." prefix) so they line up
     * with the validation error keys asserted by assertHasErrors(). Mirrors
     * NoerdDetail::extractRulesFromFields() (recurse into type: block, collect
     * required: true) so validation tests assert against whatever the YAML
     * currently declares required instead of hard-coding a field name.
     *
     * Fields the layout always fills (an explicit `default:`, or the first
     * option of a select whose options are in the YAML) are left out: the trait
     * seeds them before every render, so they can never reach store() empty and
     * would never produce a required error.
     *
     * @return array<int, string>
     */
    function requiredLayoutFields($component): array
    {
        $layout = $component->get('pageLayout') ?? [];
        $fields = $layout['fields'] ?? [];

        $defaulted = array_map(
            static fn(string $key): string => 'detailData.' . $key,
            array_keys(\Noerd\Support\LayoutDefaults::resolve($fields)),
        );

        return array_values(array_diff(extractRequiredLayoutFields($fields), $defaulted));
    }
}

if (! function_exists('extractRequiredLayoutFields')) {
    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, string>
     */
    function extractRequiredLayoutFields(array $fields): array
    {
        $required = [];

        foreach ($fields as $field) {
            if (($field['type'] ?? null) === 'block') {
                $required = array_merge(
                    $required,
                    extractRequiredLayoutFields($field['fields'] ?? []),
                );

                continue;
            }

            if (($field['required'] ?? false) && isset($field['name'])) {
                $required[] = $field['name'];
            }
        }

        return $required;
    }
}

if (! function_exists('validDetailPayload')) {
    /**
     * Valid detailData array sourced from the model factory, merged with overrides.
     *
     * Keys are un-prefixed field names (tenant_id, name, zipcode, …), exactly as
     * expected by ->set('detailData', …). make($overrides) avoids spinning up
     * relation factories for overridden foreign keys, and id/timestamps are
     * stripped so no existing record is implied.
     *
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function validDetailPayload(string $modelClass, array $overrides = []): array
    {
        $attributes = $modelClass::factory()->make($overrides)->toArray();

        return array_merge(
            Arr::except($attributes, ['id', 'created_at', 'updated_at', 'deleted_at']),
            $overrides,
        );
    }
}

if (! function_exists('registerTestLivewireRoute')) {
    /**
     * Register a named Route::livewire() route from inside a test.
     *
     * RouteServiceProvider refreshes the collection's name lookups once on boot,
     * so a route added afterwards is invisible to Route::has()/getByName() until
     * the lookups are refreshed again.
     */
    function registerTestLivewireRoute(string $uri, string $component, string $name): void
    {
        Route::livewire($uri, $component)->name($name);
        Route::getRoutes()->refreshNameLookups();
    }
}

if (! function_exists('ensureTenantApp')) {
    /**
     * The tenant_apps row of a module key in its canonical shape — name =
     * UPPERCASE key, route = lowercase key — created when missing. A real
     * installation gets this row from the module's install command; a test
     * creates it here and assigns it with Tenant::assignApp().
     *
     * @param  array<string, mixed>  $attributes  overrides for a NEW row (title, icon, route, is_active)
     */
    function ensureTenantApp(string $appKey, array $attributes = []): \Noerd\Models\TenantApp
    {
        return \Noerd\Models\TenantApp::query()->firstOrCreate(
            ['name' => mb_strtoupper($appKey)],
            array_merge([
                'title' => \Illuminate\Support\Str::headline(mb_strtolower($appKey)),
                'icon' => 'heroicon:outline:rectangle-stack',
                'route' => mb_strtolower($appKey),
                'is_active' => true,
            ], $attributes),
        );
    }
}

if (! function_exists('createNoerdUserForApp')) {
    /**
     * The standard module fixture: a user on a fresh tenant that runs the app,
     * with that tenant and app selected — what every module test starts from.
     *
     * The tenant_apps row is created in its canonical shape when missing and
     * assigned through Tenant::assignApp(), so the TenantAppAssigned listeners
     * of the installed modules run exactly as they do when an installation
     * assigns the app. The user carries the tenant as selected_tenant_id and
     * is attached under the given profile — a core Profile case, the key of a
     * profile a module registers, or none. Nothing is authenticated;
     * actingAsNoerdUserForApp() adds that.
     *
     * @param  array<string, mixed>  $tenantAttributes  overrides for the Tenant factory
     * @param  array<string, mixed>  $appAttributes  overrides for a NEW tenant_apps row
     */
    function createNoerdUserForApp(
        string $appKey,
        array $tenantAttributes = [],
        BackedEnum|string|null $profile = null,
        array $appAttributes = [],
    ): \Noerd\Models\NoerdUser {
        $tenant = \Noerd\Models\Tenant::factory()->create($tenantAttributes);
        $app = ensureTenantApp($appKey, $appAttributes);
        $tenant->assignApp($app);

        $user = \Noerd\Models\NoerdUser::factory()->create(['selected_tenant_id' => $tenant->id]);
        $user->tenants()->attach($tenant->id, [
            'profile_key' => $profile instanceof BackedEnum ? $profile->value : $profile,
        ]);

        \Noerd\Helpers\TenantHelper::setSelectedTenantId($tenant->id);
        \Noerd\Helpers\TenantHelper::setSelectedApp((string) $app->name);

        return $user;
    }
}

if (! function_exists('actingAsNoerdUserForApp')) {
    /**
     * createNoerdUserForApp() plus authentication on the noerd guard — the
     * one-line beforeEach of a module test. The tenant is the user's
     * selected_tenant_id.
     *
     * @param  array<string, mixed>  $tenantAttributes  overrides for the Tenant factory
     * @param  array<string, mixed>  $appAttributes  overrides for a NEW tenant_apps row
     */
    function actingAsNoerdUserForApp(
        string $appKey,
        array $tenantAttributes = [],
        BackedEnum|string|null $profile = null,
        array $appAttributes = [],
    ): \Noerd\Models\NoerdUser {
        $user = createNoerdUserForApp($appKey, $tenantAttributes, $profile, $appAttributes);

        // The same steps as TestCase::actingAs($user, NoerdAuth::GUARD), without
        // needing the test case instance.
        $user->wasRecentlyCreated = false;
        $auth = app('auth');
        $auth->guard(\Noerd\Helpers\NoerdAuth::GUARD)->setUser($user);
        $auth->shouldUse(\Noerd\Helpers\NoerdAuth::GUARD);

        return $user;
    }
}

if (! function_exists('assertElementHasClasses')) {
    /**
     * Assert that ONE element in the markup carries all given CSS classes.
     *
     * Matching a multi-class string as one substring (e.g. assertSeeHtml('h-7 px-2'))
     * breaks the moment a Tailwind class sorter reorders the attribute, even though
     * the markup is unchanged. This checks each class as a whole token within a
     * single class attribute, so order and neighbouring classes are irrelevant while
     * the "same element" guarantee is kept.
     *
     * @param  array<int, string>  $classes
     */
    function assertElementHasClasses(string $html, array $classes, string $message = ''): void
    {
        preg_match_all('/class="([^"]*)"/', $html, $matches);

        foreach ($matches[1] as $classAttribute) {
            $present = preg_split('/\s+/', mb_trim($classAttribute)) ?: [];

            if (array_diff($classes, $present) === []) {
                Assert::assertTrue(true);

                return;
            }
        }

        Assert::fail(
            $message !== ''
                ? $message
                : 'No element carries all of these classes: ' . implode(' ', $classes),
        );
    }
}

if (! function_exists('assertNoElementHasClasses')) {
    /**
     * The negative counterpart of assertElementHasClasses().
     *
     * @param  array<int, string>  $classes
     */
    function assertNoElementHasClasses(string $html, array $classes, string $message = ''): void
    {
        preg_match_all('/class="([^"]*)"/', $html, $matches);

        foreach ($matches[1] as $classAttribute) {
            $present = preg_split('/\s+/', mb_trim($classAttribute)) ?: [];

            if (array_diff($classes, $present) === []) {
                Assert::fail(
                    $message !== ''
                        ? $message
                        : 'An element unexpectedly carries all of these classes: ' . implode(' ', $classes),
                );
            }
        }

        Assert::assertTrue(true);
    }
}

if (! function_exists('noerdModuleSourceFiles')) {
    /**
     * Every file the module boundary guards read: php/yml/yaml/json/stub files
     * under src, resources, routes, database, config, app-configs and tests —
     * without the boundary tests themselves (they name foreign namespaces as
     * needles) and without the agent documentation under resources/boost/,
     * which is prose that states the boundary rule and has to name what it
     * forbids.
     *
     * @internal shared by assertModuleDependenciesDeclared() and assertModuleDoesNotReference()
     *
     * @return Generator<int, SplFileInfo>
     */
    function noerdModuleSourceFiles(string $moduleDir): Generator
    {
        foreach (['src', 'resources', 'routes', 'database', 'config', 'app-configs', 'tests'] as $dir) {
            $path = $moduleDir . '/' . $dir;
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (! in_array($file->getExtension(), ['php', 'yml', 'yaml', 'json', 'stub'], true)) {
                    continue;
                }
                if (str_contains($file->getFilename(), 'ModuleBoundaryTest')) {
                    continue;
                }
                if (str_contains(str_replace('\\', '/', $file->getPathname()), '/resources/boost/')) {
                    continue;
                }

                yield $file;
            }
        }
    }
}

if (! function_exists('noerdSiblingModulePackages')) {
    /**
     * PSR-4 root namespace => package name of every module next to the given
     * one ("Noerd\\Cms\\" => "noerd/cms"), read from the sibling composer.json
     * files; sub-namespaces (Database\Factories, Tests, …) map to the same
     * package. Empty for a module checked out on its own.
     *
     * @internal shared by assertModuleDependenciesDeclared() and assertModuleDoesNotReference()
     *
     * @return array<string, string>
     */
    function noerdSiblingModulePackages(string $moduleDir): array
    {
        $prefixes = [];

        foreach (glob(dirname($moduleDir) . '/*/composer.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true) ?? [];
            foreach (array_keys($data['autoload']['psr-4'] ?? []) as $prefix) {
                $root = implode('\\', array_slice(explode('\\', mb_trim($prefix, '\\')), 0, 2)) . '\\';
                $prefixes[$root] ??= (string) ($data['name'] ?? '');
            }
        }

        return array_filter($prefixes, static fn(string $package): bool => $package !== '');
    }
}

if (! function_exists('assertModuleDependenciesDeclared')) {
    /**
     * Module-independence guard: every OTHER app module whose PSR-4 namespace
     * appears anywhere in this module (src, resources, routes, database,
     * config, app-configs and tests) must be declared in the module's
     * composer.json require or require-dev. The known module namespaces are
     * derived from the sibling app-modules' composer.json files, so the guard
     * needs no hand-maintained mapping and also catches test-only leaks.
     * assertModuleBoundary() reads the allowed packages from composer.json.
     *
     * @param  string  $moduleDir  absolute path of the module under test
     * @param  array<int, string>  $allowedPackages  extra packages to tolerate
     */
    function assertModuleDependenciesDeclared(string $moduleDir, array $allowedPackages = []): void
    {
        $composer = json_decode((string) file_get_contents($moduleDir . '/composer.json'), true) ?? [];
        $declared = array_merge(
            array_keys($composer['require'] ?? []),
            array_keys($composer['require-dev'] ?? []),
            $allowedPackages,
            [$composer['name'] ?? ''],
        );

        $violations = [];
        foreach (noerdModuleSourceFiles($moduleDir) as $file) {
            $content = (string) file_get_contents($file->getPathname());

            foreach (noerdSiblingModulePackages($moduleDir) as $prefix => $package) {
                if (in_array($package, $declared, true)) {
                    continue;
                }
                if (str_contains($content, $prefix) || str_contains($content, str_replace('\\', '\\\\', $prefix))) {
                    $violations[$package][] = mb_substr($file->getPathname(), mb_strlen($moduleDir) + 1);
                }
            }
        }

        Assert::assertSame(
            [],
            $violations,
            'Undeclared module dependencies found — add them to composer.json (require or require-dev) or remove the usage: '
            . json_encode(array_map(fn(array $files) => array_values(array_unique($files)), $violations), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}

if (! function_exists('assertModuleDoesNotReference')) {
    /**
     * The hard boundary between modules that must never meet (customer and
     * party, booking-members and accounting): none of the given namespaces may
     * appear in the module's files, and composer.json may not require the
     * package that ships one — the sibling modules' composer.json files map
     * namespace to package, so one namespace covers both checks. An entry
     * written as a package name ("noerd/party") is checked against
     * composer.json only.
     *
     * @param  array<int, string>  $forbidden  namespaces ("Noerd\\Party\\") or package names
     */
    function assertModuleDoesNotReference(string $moduleDir, array $forbidden): void
    {
        $composer = json_decode((string) file_get_contents($moduleDir . '/composer.json'), true) ?? [];
        $required = array_merge(array_keys($composer['require'] ?? []), array_keys($composer['require-dev'] ?? []));

        $packages = array_values(array_filter($forbidden, static fn(string $entry): bool => str_contains($entry, '/')));
        $namespaces = array_map(
            static fn(string $entry): string => mb_rtrim($entry, '\\') . '\\',
            array_values(array_filter($forbidden, static fn(string $entry): bool => ! str_contains($entry, '/'))),
        );

        foreach (noerdSiblingModulePackages($moduleDir) as $prefix => $package) {
            foreach ($namespaces as $namespace) {
                if (str_starts_with($prefix, $namespace)) {
                    $packages[] = $package;
                }
            }
        }

        $violations = [];
        foreach (array_unique($packages) as $package) {
            if (in_array($package, $required, true)) {
                $violations[] = 'composer.json requires ' . $package;
            }
        }

        foreach (noerdModuleSourceFiles($moduleDir) as $file) {
            $content = (string) file_get_contents($file->getPathname());

            foreach ($namespaces as $namespace) {
                if (str_contains($content, $namespace) || str_contains($content, str_replace('\\', '\\\\', $namespace))) {
                    $violations[] = mb_substr($file->getPathname(), mb_strlen($moduleDir) + 1) . ' references ' . $namespace;
                }
            }
        }

        Assert::assertSame(
            [],
            $violations,
            'The module crosses a boundary it must never cross: ' . json_encode($violations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}

if (! function_exists('assertModuleBoundary')) {
    /**
     * The one boundary test of every module, driven by its composer.json:
     *
     *     "extra": {
     *         "noerd": {
     *             "boundary": {
     *                 "forbidden": ["Noerd\\Party\\"],
     *                 "allowed": {"nywerk/liefertool-shop": "shop-only blades, class_exists-guarded"}
     *             }
     *         }
     *     }
     *
     * `forbidden` names what the module must never reference
     * (assertModuleDoesNotReference()), `allowed` the packages it may reference
     * without declaring them — a map with the reason as value, or a plain list
     * (assertModuleDependenciesDeclared()). The forbidden check runs first;
     * without the block only the dependency guard runs.
     */
    function assertModuleBoundary(string $moduleDir): void
    {
        $composer = json_decode((string) file_get_contents($moduleDir . '/composer.json'), true) ?? [];
        $boundary = $composer['extra']['noerd']['boundary'] ?? [];

        // The hard boundary first: a reference to a module that must never be
        // used is reported as such, not as a merely undeclared dependency.
        $forbidden = array_values($boundary['forbidden'] ?? []);
        if ($forbidden !== []) {
            assertModuleDoesNotReference($moduleDir, $forbidden);
        }

        $allowed = $boundary['allowed'] ?? [];
        $allowed = array_is_list($allowed) ? $allowed : array_keys($allowed);

        assertModuleDependenciesDeclared($moduleDir, array_map('strval', $allowed));
    }
}

if (! function_exists('assertModuleUpdateCommandPublishesConfigs')) {
    /**
     * Standard proof for a module's noerd:update-{module} command: publishes
     * every YAML the module ships into app-configs/{key} (self-heal branch),
     * exits cleanly, and --force restores a locally modified copy.
     *
     * The command runs against a THROWAWAY base path, because an update command
     * writes into the host tree (app-configs/, config/{module}.php, boost.json,
     * the setup navigation). Writing into the real project would leave the
     * checkout altered and — under `php artisan test --parallel` — let a second
     * worker `require` a config file while it is only half written.
     */
    function assertModuleUpdateCommandPublishesConfigs(string $command, string $moduleDir, string $moduleKey): void
    {
        $source = $moduleDir . '/app-configs/' . $moduleKey;

        Assert::assertDirectoryExists($source, "Module ships no app-configs/{$moduleKey} directory.");

        $app = app();
        $originalBasePath = $app->basePath();
        // Resolved before the base path moves: storage_path() follows it.
        $host = storage_path('framework/testing/zz-update-cmd-' . $moduleKey . '-' . getmypid());

        \Illuminate\Support\Facades\File::deleteDirectory($host);
        \Illuminate\Support\Facades\File::ensureDirectoryExists($host . '/config');
        // RequiresNoerdInstallation only checks that the host published the config.
        \Illuminate\Support\Facades\File::put($host . '/config/noerd.php', "<?php\n\nreturn [];\n");

        $app->setBasePath($host);

        try {
            $target = $host . '/app-configs/' . $moduleKey;

            $exit = \Illuminate\Support\Facades\Artisan::call($command, ['--no-interaction' => true]);
            Assert::assertSame(0, $exit, "{$command} did not exit cleanly: " . \Illuminate\Support\Facades\Artisan::output());

            // The update contract publishes the config SUBDIRECTORIES plus
            // navigation.yml — extra root-level files are module-specific and
            // outside the generic mechanism.
            $published = null;
            $shipped = array_merge(
                glob($source . '/*/*.yml') ?: [],
                glob($source . '/*/*/*.yml') ?: [],
                array_filter([is_file($source . '/navigation.yml') ? $source . '/navigation.yml' : null]),
            );
            foreach ($shipped as $file) {
                $relative = mb_substr($file, mb_strlen($source) + 1);
                Assert::assertFileExists($target . '/' . $relative, "update did not publish {$relative}");
                $published ??= $target . '/' . $relative;
            }

            Assert::assertNotNull($published, 'module ships no yml files to publish');

            // --force refreshes a locally modified copy back to the shipped content.
            file_put_contents($published, "title: Zz Local Change\n");
            $exit = \Illuminate\Support\Facades\Artisan::call($command, ['--force' => true, '--no-interaction' => true]);
            Assert::assertSame(0, $exit, "{$command} --force did not exit cleanly");
            Assert::assertStringNotContainsString('Zz Local Change', (string) file_get_contents($published));
        } finally {
            $app->setBasePath($originalBasePath);
            \Illuminate\Support\Facades\File::deleteDirectory($host);
        }
    }
}

if (! function_exists('zzNormalizeSpaces')) {
    /**
     * ICU separates symbols and groups with U+00A0 / U+202F: collapse every
     * kind of space to a plain one so assertions read like the screen.
     */
    function zzNormalizeSpaces(string $value): string
    {
        return (string) preg_replace('/[\s\x{00a0}\x{202f}]+/u', ' ', $value);
    }
}
