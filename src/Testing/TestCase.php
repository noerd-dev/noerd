<?php

declare(strict_types=1);

namespace Noerd\Testing;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\File;
use Livewire\ComponentHookRegistry;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Models\NoerdUser;
use Noerd\Providers\NoerdServiceProvider;
use Noerd\Support\ComponentAccessGuard;
use NoerdModal\Providers\NoerdModalServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use PDO;
use Throwable;
use WireUi\Heroicons\HeroiconsServiceProvider;

// The global test helpers ship with the package but are NOT in the production
// autoload (a consumer app must never load test functions per request). Loading
// them here covers every context that runs noerd-based tests through this
// TestCase: the package's own suite, host-root runs and module testbench
// suites. Suites that bind a different TestCase call HelperLoader::load()
// themselves from their tests/Pest.php.
HelperLoader::load();

/**
 * The standalone Testbench test case of noerd (Orchestra Testbench, sqlite
 * :memory:): the core providers, the noerd guard as default guard, an
 * environment independent of the host's .env, the RefreshDatabaseState swap
 * for mixed host/testbench runs and the package linked into the skeleton.
 *
 * Shipped in `src/Testing` so a module's own Testbench suite can build on it
 * (through ModuleTestCase). It needs `orchestra/testbench`, which the package
 * only suggests — a module running such a suite requires it itself.
 */
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
        self::flushGuardableColumnsCache();
        self::flushLivewireComponentHooks();
        self::flushRegisteredAdminComponents();
        $this->swapInTestbenchRefreshDatabaseState();

        try {
            parent::setUp();
        } catch (Throwable $exception) {
            $this->swapOutTestbenchRefreshDatabaseState();

            throw $exception;
        }

        $this->linkModuleIntoSkeleton(self::packageRoot(), 'noerd');
        $this->publishPackageIntoSkeleton();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            $this->swapOutTestbenchRefreshDatabaseState();
            self::flushGuardableColumnsCache();
        }
    }

    /**
     * The root directory of the noerd package — `vendor/noerd/noerd` or, in a
     * monorepo, `app-modules/noerd`.
     */
    protected static function packageRoot(): string
    {
        return dirname(__DIR__, 2);
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
        // Read from the environment directly: a test application never runs on
        // a cached config.
        $app['config']->set('database.default', Env::get('NOERD_TESTBENCH_DB', 'testing'));
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

    /**
     * Link a module into the skeleton's app-modules/. StaticConfigHelper
     * discovers module YAML sources via base_path('app-modules'); under
     * testbench base_path() is the skeleton application, so every module the
     * suite boots is linked in — mirroring what noerd:install{-module} does in
     * a real project. The link is named after the module directory unless a
     * name is given. No-op when the directory already exists (inside the host
     * application, where the module really lives there).
     */
    protected function linkModuleIntoSkeleton(string $moduleRoot, ?string $name = null): void
    {
        $target = base_path('app-modules/' . ($name ?? basename($moduleRoot)));

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

    /**
     * Eloquent memoizes every model's guardable COLUMN LIST in a process-global
     * static, filled from the schema of whichever connection first mass-assigns
     * that model. The testbench app (the package migrations alone) and a host
     * application (its own migrations on top) disagree about those columns —
     * `tenants.email`, for one, exists only in the host — so without this flush
     * the suite that runs first teaches Eloquent a column list the other suite
     * then filters its own writes through, and `update(['email' => null])`
     * becomes a silent no-op instead of an error. Flushed around every test so
     * each suite reads its own schema.
     */
    private static function flushGuardableColumnsCache(): void
    {
        Closure::bind(static function (): void {
            Model::$guardableColumns = [];
        }, null, Model::class)();
    }

    /**
     * Livewire keeps the registered component hooks in a process-global static
     * list that survives app boots: every hook a provider ever registered stays
     * on the list, and the next Livewire boot wires ALL of them into the new
     * app. A host-application suite that ran earlier in the same PHPUnit
     * process therefore leaks the hooks of every host package (e.g. the
     * custom-attribute hooks of an extension) into this testbench app — which
     * never booted those packages and has none of their tables. Flushed before the app is
     * created, so only the hooks of the providers this app actually boots are
     * attached; a host suite running afterwards re-registers its own on boot.
     */
    private static function flushLivewireComponentHooks(): void
    {
        Closure::bind(static function (): void {
            ComponentHookRegistry::$componentHooks = [];
        }, null, ComponentHookRegistry::class)();
    }

    /**
     * The admin allow-list modules contribute through
     * ComponentAccessGuard::registerAdminComponents() is process-global too, so
     * a host suite that ran earlier leaks the registrations of packages this
     * testbench app never boots. Flushed before the app is created; the
     * providers this app does boot register their own again.
     */
    private static function flushRegisteredAdminComponents(): void
    {
        ComponentAccessGuard::flushRegisteredComponents();
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
     * The installed navigation/app configs are read from base_path('app-configs')
     * and the install commands treat a published config/noerd.php as the marker
     * that noerd:install has been run — both are published into the skeleton
     * the way noerd:install does it. No-op inside the host application, where
     * both copies already match.
     */
    private function publishPackageIntoSkeleton(): void
    {
        // Like the config below, the published app-configs are refreshed when the
        // skeleton copy no longer matches the package copy — copying only when
        // missing would pin the suite to stale YAMLs (e.g. old navigation route
        // names or field defaults) for the lifetime of the skeleton. Every file
        // of the package tree is compared by content: tests that back up and
        // restore a published file rewrite it, so mtimes carry no signal here.
        $configSource = self::packageRoot() . '/app-configs';
        $configTarget = base_path('app-configs');

        if (! $this->publishedConfigsMatch($configSource, $configTarget)) {
            File::copyDirectory($configSource, $configTarget);
        }

        // The copy is refreshed whenever the package config is newer — copying
        // only when missing would pin the suite to a stale config for the
        // lifetime of the skeleton, so a new key would never reach the tests.
        $configSource = self::packageRoot() . '/config/noerd.php';
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
