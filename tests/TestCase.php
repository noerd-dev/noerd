<?php

declare(strict_types=1);

namespace Noerd\Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Noerd\Models\NoerdUser;
use Noerd\Providers\NoerdServiceProvider;
use NoerdModal\Providers\NoerdModalServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use PDO;
use Throwable;
use WireUi\Heroicons\HeroiconsServiceProvider;

// The global test helpers ship with the package but are NOT in the production
// autoload (a consumer app must never load test functions per request). Loading
// them here covers every context that runs noerd-based tests through this
// TestCase: the package's own suite, host-root runs and submodule testbench
// suites. Suites that bind a different TestCase call HelperLoader::load()
// themselves from their tests/Pest.php.
HelperLoader::load();

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabaseState is a process-global static shared with the host
     * application's suite. When host tests (MySQL) and these testbench tests
     * (sqlite :memory:) run in one PHPUnit process, whichever suite migrates
     * first flags the OTHER suite's database as already migrated —
     * RefreshDatabase then skips migrating an empty database ("no such table:
     * tenants"/"noerd_users"). Each suite therefore gets its own view of the
     * state: the testbench view is swapped in for the whole test (setUp through
     * tearDown — testbench resets the state again in its own tearDown, exactly
     * like a standalone run) and the host view is restored afterwards.
     */
    private static bool $testbenchMigrated = false;

    /** @var array<string, PDO> */
    private static array $testbenchInMemoryConnections = [];

    private static bool $hostMigrated = false;

    /** @var array<string, PDO> */
    private static array $hostInMemoryConnections = [];

    private static bool $refreshDatabaseStateSwapped = false;

    protected function setUp(): void
    {
        $this->swapInTestbenchRefreshDatabaseState();

        try {
            parent::setUp();
        } catch (Throwable $exception) {
            $this->swapOutTestbenchRefreshDatabaseState();

            throw $exception;
        }

        $this->linkModuleIntoSkeleton();
        $this->withoutVite();

        // Test-only Livewire components (synthetic layouts, theme probes) live
        // with the tests — never in the package's production namespace.
        Livewire::addNamespace('noerd-test', viewPath: __DIR__ . '/Feature/fixtures/components');
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            $this->swapOutTestbenchRefreshDatabaseState();
        }
    }
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            NoerdModalServiceProvider::class,
            HeroiconsServiceProvider::class,
            NoerdServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Livewire' => Livewire::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('auth.providers.users.model', NoerdUser::class);

        // Guard-less actingAs() calls resolve the default guard — point it at
        // noerd's own guard (registered by NoerdServiceProvider) so tests hit
        // the same guard the 'noerd' route middleware group authenticates.
        $app['config']->set('auth.defaults.guard', 'noerd');
        $app['config']->set('auth.defaults.passwords', 'noerd_users');

        // noerd:install points the Livewire page layout at the module's own
        // layout in real projects — mirror that here.
        $app['config']->set('livewire.component_layout', 'noerd::layouts.app');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('mail.default', 'array');

        // Translated output must not depend on the host application's
        // APP_LOCALE — a real project (and this repo's .env.testing) runs
        // German, which would break every assertion on an English source
        // string. Tests that exercise translation set their own locale
        // explicitly, e.g. FieldHelpTextTest.
        $app['config']->set('app.locale', 'en');
        $app['config']->set('app.fallback_locale', 'en');

        // Defaults to testbench's built-in sqlite :memory: connection. A
        // dedicated variable (not DB_CONNECTION) selects another database, so
        // a host application's CI environment never leaks into these tests.
        $app['config']->set('database.default', env('NOERD_TESTBENCH_DB', 'testing'));
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        // The skeleton's default migrations provide password_reset_tokens,
        // which the password broker needs. The module's own migrations load
        // through NoerdServiceProvider. Only registered for RefreshDatabase
        // tests — for plain tests testbench would migrate eagerly and roll
        // back in tearDown, which prompts once a test switches the
        // environment away from testing.
        if (static::usesRefreshDatabaseTestingConcern()) {
            $this->loadMigrationsFrom(\Orchestra\Testbench\default_migration_path());
        }
    }

    private function swapInTestbenchRefreshDatabaseState(): void
    {
        self::$hostMigrated = RefreshDatabaseState::$migrated;
        self::$hostInMemoryConnections = RefreshDatabaseState::$inMemoryConnections;

        RefreshDatabaseState::$migrated = self::$testbenchMigrated;
        RefreshDatabaseState::$inMemoryConnections = self::$testbenchInMemoryConnections;

        self::$refreshDatabaseStateSwapped = true;
    }

    private function swapOutTestbenchRefreshDatabaseState(): void
    {
        if (! self::$refreshDatabaseStateSwapped) {
            return;
        }

        self::$testbenchMigrated = RefreshDatabaseState::$migrated;
        self::$testbenchInMemoryConnections = RefreshDatabaseState::$inMemoryConnections;

        RefreshDatabaseState::$migrated = self::$hostMigrated;
        RefreshDatabaseState::$inMemoryConnections = self::$hostInMemoryConnections;

        self::$refreshDatabaseStateSwapped = false;
    }

    /**
     * StaticConfigHelper discovers module configs via base_path('app-modules')
     * and reads the installed navigation/app configs from base_path('app-configs').
     * Under testbench base_path() is the skeleton application, so this package
     * is linked in and its app-configs are published once — mirroring what
     * noerd:install does in a real project. No-op inside the host application,
     * where both paths really exist.
     */
    private function linkModuleIntoSkeleton(): void
    {
        $moduleTarget = base_path('app-modules/noerd');
        if (! file_exists($moduleTarget) && ! is_link($moduleTarget)) {
            File::ensureDirectoryExists(base_path('app-modules'));
            @symlink(dirname(__DIR__), $moduleTarget);
        }

        // Like the config below, the published app-configs are refreshed when the
        // skeleton copy no longer matches the package copy — copying only when
        // missing would pin the suite to stale YAMLs (e.g. old navigation route
        // names or field defaults) for the lifetime of the skeleton. Every file
        // of the package tree is compared by content: tests that back up and
        // restore a published file rewrite it, so mtimes carry no signal here.
        $configSource = dirname(__DIR__) . '/app-configs';
        $configTarget = base_path('app-configs');

        if (! $this->publishedConfigsMatch($configSource, $configTarget)) {
            File::copyDirectory($configSource, $configTarget);
        }

        // The install commands treat a published config/noerd.php as the
        // marker that noerd:install has been run. The copy is refreshed whenever the package
        // config is newer — copying only when missing would pin the suite to a stale config
        // for the lifetime of the skeleton, so a new key would never reach the tests.
        $configSource = dirname(__DIR__) . '/config/noerd.php';
        $configTarget = base_path('config/noerd.php');

        if (! file_exists($configTarget) || filemtime($configSource) > filemtime($configTarget)) {
            File::copy($configSource, $configTarget);
        }
    }

    /**
     * Whether every file under the package app-configs has an identical copy in
     * the skeleton. Extra files in the skeleton (test fixtures) are ignored.
     */
    private function publishedConfigsMatch(string $source, string $target): bool
    {
        foreach (File::allFiles($source) as $file) {
            $copy = $target . '/' . $file->getRelativePathname();

            if (! file_exists($copy) || file_get_contents($copy) !== $file->getContents()) {
                return false;
            }
        }

        return true;
    }
}
