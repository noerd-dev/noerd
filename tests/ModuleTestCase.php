<?php

declare(strict_types=1);

namespace Noerd\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Noerd\Helpers\StaticConfigHelper;
use ReflectionClass;

/**
 * The test case of a module with a standalone suite (Orchestra Testbench,
 * sqlite :memory:): everything Noerd\Tests\TestCase provides — the core
 * providers, the noerd guard, the RefreshDatabaseState swap for mixed
 * host/testbench runs, the package linked into the skeleton — plus the
 * module's own providers, each module linked into the skeleton's app-modules/
 * so StaticConfigHelper finds its YAML the way it does after
 * noerd:install-{module}.
 *
 *     abstract class TestCase extends \Noerd\Tests\ModuleTestCase
 *     {
 *         protected array $moduleProviders = [HrServiceProvider::class];
 *     }
 *
 * A module that builds on other modules lists their providers as well (their
 * modules are linked too). Environment overrides go into defineEnvironment()
 * after parent::defineEnvironment($app). Every test file still binds the
 * module's test case itself with uses() — a host-root run loads the host's
 * Pest.php instead of the module's.
 */
abstract class ModuleTestCase extends TestCase
{
    /** @var list<class-string<ServiceProvider>> */
    protected array $moduleProviders = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->moduleRoots() as $root) {
            $this->linkModuleIntoSkeleton($root);
        }
    }

    /**
     * StaticConfigHelper's module-source mapping is a process-global static
     * that is NOT flushed on app boot. This suite primes it against the
     * testbench skeleton's base_path — a host-application suite running later
     * in the same PHPUnit process would then resolve its module YAMLs against
     * the skeleton and silently lose them. Cleared so the next suite rebuilds
     * its own view.
     */
    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            StaticConfigHelper::clearModuleSourceCache();
        }
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ...$this->moduleProviders()];
    }

    /**
     * The module's providers, booted after the core providers. Override when
     * the list depends on more than a property default can express.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function moduleProviders(): array
    {
        return $this->moduleProviders;
    }

    /**
     * The root directory of every module whose provider is listed. A provider
     * lives in {root}/src/Providers/ — the convention
     * NoerdModuleServiceProvider::moduleRoot() derives the root from as well;
     * a provider placed elsewhere (no composer.json three levels up) is booted
     * but links nothing.
     *
     * @return list<string>
     */
    protected function moduleRoots(): array
    {
        $roots = [];

        foreach ($this->moduleProviders() as $provider) {
            $file = (new ReflectionClass($provider))->getFileName();
            if ($file === false) {
                continue;
            }

            $root = dirname($file, 3);
            if (is_file($root . '/composer.json')) {
                $roots[] = realpath($root) ?: $root;
            }
        }

        return array_values(array_unique($roots));
    }

    /**
     * StaticConfigHelper discovers module YAML sources via base_path('app-modules').
     * Under testbench base_path() is the skeleton application, so the module is
     * linked in — mirroring what noerd:install-{module} does in a real project.
     * No-op when the directory already exists.
     */
    protected function linkModuleIntoSkeleton(string $moduleRoot): void
    {
        $target = base_path('app-modules/' . basename($moduleRoot));

        // The skeleton outlives checkouts: a link left behind by a moved or
        // deleted project dangles, or points at a foreign tree whose YAMLs and
        // commands the suite would then silently read. Replace it instead of
        // trusting is_link().
        if (is_link($target) && realpath($target) !== realpath($moduleRoot)) {
            @unlink($target);
        }

        if (file_exists($target) || is_link($target)) {
            return;
        }

        File::ensureDirectoryExists(base_path('app-modules'));
        @symlink($moduleRoot, $target);

        StaticConfigHelper::clearModuleSourceCache();
        StaticConfigHelper::flushRuntimeCaches();
    }
}
