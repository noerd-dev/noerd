<?php

declare(strict_types=1);

namespace Noerd\Tests;

use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Noerd\Models\NoerdUser;
use Noerd\Providers\NoerdServiceProvider;
use Noerd\Services\ListQueryContext;
use NoerdModal\Providers\NoerdModalServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use WireUi\Heroicons\HeroiconsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->linkModuleIntoSkeleton();
        $this->withoutVite();

        app(ListQueryContext::class)->reset();
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

        // noerd:install points the Livewire page layout at the module's own
        // layout in real projects — mirror that here.
        $app['config']->set('livewire.component_layout', 'noerd::layouts.app');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('mail.default', 'array');

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

        if (! file_exists(base_path('app-configs/setup/navigation.yml'))) {
            File::copyDirectory(dirname(__DIR__) . '/app-configs', base_path('app-configs'));
        }

        // The install commands treat a published config/noerd.php as the
        // marker that noerd:install has been run.
        if (! file_exists(base_path('config/noerd.php'))) {
            File::copy(dirname(__DIR__) . '/config/noerd.php', base_path('config/noerd.php'));
        }
    }
}
