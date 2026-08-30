<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Assert;

if (!function_exists('requiredLayoutFields')) {
    /**
     * Field names marked required in the component's pageLayout.
     *
     * Returns the layout keys (incl. the "detailData." prefix) so they line up
     * with the validation error keys asserted by assertHasErrors(). Mirrors
     * NoerdDetail::extractRulesFromFields() (recurse into type: block, collect
     * required: true) so validation tests assert against whatever the YAML
     * currently declares required instead of hard-coding a field name.
     *
     * @return array<int, string>
     */
    function requiredLayoutFields($component): array
    {
        $layout = $component->get('pageLayout') ?? [];

        return extractRequiredLayoutFields($layout['fields'] ?? []);
    }
}

if (!function_exists('extractRequiredLayoutFields')) {
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

if (!function_exists('validDetailPayload')) {
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

if (!function_exists('registerTestLivewireRoute')) {
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

if (!function_exists('assertElementHasClasses')) {
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

if (!function_exists('assertNoElementHasClasses')) {
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

if (!function_exists('assertModuleDependenciesDeclared')) {
    /**
     * Module-independence guard: every OTHER app module whose PSR-4 namespace
     * appears anywhere in this module (src, resources, routes, database,
     * config, app-configs and tests) must be declared in the module's
     * composer.json require or require-dev. The known module namespaces are
     * derived from the sibling app-modules' composer.json files, so the guard
     * needs no hand-maintained mapping and also catches test-only leaks.
     *
     * @param  string  $moduleDir  absolute path of the module under test
     * @param  array<int, string>  $allowedPackages  extra packages to tolerate
     */
    function assertModuleDependenciesDeclared(string $moduleDir, array $allowedPackages = []): void
    {
        $modulesRoot = dirname($moduleDir);
        $composer = json_decode((string) file_get_contents($moduleDir . '/composer.json'), true) ?? [];
        $declared = array_merge(
            array_keys($composer['require'] ?? []),
            array_keys($composer['require-dev'] ?? []),
            $allowedPackages,
            [$composer['name'] ?? ''],
        );

        // prefix (e.g. "Noerd\\Cms\\") => package name (e.g. "noerd/cms")
        $prefixes = [];
        foreach (glob($modulesRoot . '/*/composer.json') as $file) {
            $data = json_decode((string) file_get_contents($file), true) ?? [];
            foreach (array_keys($data['autoload']['psr-4'] ?? []) as $prefix) {
                // Sub-namespaces (Database\Factories, Tests, ...) map to the same package.
                $root = implode('\\', array_slice(explode('\\', mb_trim($prefix, '\\')), 0, 2)) . '\\';
                $prefixes[$root] ??= $data['name'] ?? '';
            }
        }

        $violations = [];
        foreach (['src', 'resources', 'routes', 'database', 'config', 'app-configs', 'tests'] as $dir) {
            $path = $moduleDir . '/' . $dir;
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!in_array($file->getExtension(), ['php', 'yml', 'yaml', 'json', 'stub'], true)) {
                    continue;
                }
                // The boundary tests themselves name foreign namespaces as needles.
                if (str_contains($file->getFilename(), 'ModuleBoundaryTest')) {
                    continue;
                }
                $content = (string) file_get_contents($file->getPathname());

                foreach ($prefixes as $prefix => $package) {
                    if ($package === '' || in_array($package, $declared, true)) {
                        continue;
                    }
                    if (str_contains($content, $prefix) || str_contains($content, str_replace('\\', '\\\\', $prefix))) {
                        $violations[$package][] = mb_substr($file->getPathname(), mb_strlen($moduleDir) + 1);
                    }
                }
            }
        }

        Assert::assertSame(
            [],
            $violations,
            'Undeclared module dependencies found — add them to composer.json (require or require-dev) or remove the usage: '
            . json_encode(array_map(fn(array $files) => array_values(array_unique($files)), $violations), JSON_PRETTY_PRINT),
        );
    }
}

if (!function_exists('assertModuleUpdateCommandPublishesConfigs')) {
    /**
     * Standard proof for a module's noerd:update-{module} command: publishes
     * every YAML the module ships into app-configs/{key} (self-heal branch),
     * exits cleanly, and --force restores a locally modified copy. The target
     * directory is snapshotted and restored exactly, so the host project's
     * installed configuration is never left altered.
     */
    function assertModuleUpdateCommandPublishesConfigs(string $command, string $moduleDir, string $moduleKey): void
    {
        $source = $moduleDir . '/app-configs/' . $moduleKey;
        $target = base_path('app-configs/' . $moduleKey);
        $scratch = storage_path('framework/testing/zz-update-cmd-' . $moduleKey . '-' . getmypid());

        Assert::assertDirectoryExists($source, "Module ships no app-configs/{$moduleKey} directory.");

        $existed = is_dir($target);
        if ($existed) {
            \Illuminate\Support\Facades\File::copyDirectory($target, $scratch);
        }

        try {
            \Illuminate\Support\Facades\File::deleteDirectory($target);

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
            \Illuminate\Support\Facades\File::deleteDirectory($target);
            if ($existed) {
                \Illuminate\Support\Facades\File::copyDirectory($scratch, $target);
                \Illuminate\Support\Facades\File::deleteDirectory($scratch);
            }
        }
    }
}

if (!function_exists('createNoerdUserWithProfile')) {
    /**
     * A user attached to a fresh tenant under the given profile, with that
     * tenant selected — the fixture for profile-baseline and permission tests.
     * Pass null to attach the user without any profile.
     */
    function createNoerdUserWithProfile(?\Noerd\Enums\Profile $profile): \Noerd\Models\NoerdUser
    {
        $tenant = \Noerd\Models\Tenant::factory()->create();

        $user = \Noerd\Models\NoerdUser::factory()->create();
        $user->tenants()->attach($tenant->id, ['profile_key' => $profile?->value]);

        \Noerd\Helpers\TenantHelper::setSelectedTenantId($tenant->id);

        return $user;
    }
}

if (!class_exists('ZzSettingsProfile')) {
    /**
     * Tenant-scoped fixture model for settings-page tests — a dedicated zz
     * table, so the tests depend on no real domain model.
     */
    class ZzSettingsProfile extends \Illuminate\Database\Eloquent\Model
    {
        protected $table = 'zz_settings_profiles';

        protected $guarded = [];
    }
}

if (!function_exists('ensureZzSettingsProfilesTable')) {
    function ensureZzSettingsProfilesTable(): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('zz_settings_profiles')) {
            return;
        }

        \Illuminate\Support\Facades\Schema::create('zz_settings_profiles', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('key')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }
}
