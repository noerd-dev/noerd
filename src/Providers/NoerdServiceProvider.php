<?php

namespace Noerd\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Noerd\Commands\AssignAppsToTenant;
use Noerd\Commands\CreateAdminCommand;
use Noerd\Commands\CreateTenantApp;
use Noerd\Commands\CreateTenantCommand;
use Noerd\Commands\ExportSetupCollectionDefinitionsCommand;
use Noerd\Commands\ImportSetupCollectionDefinitionsCommand;
use Noerd\Commands\MakeCollectionCommand;
use Noerd\Commands\MakeDashboardCommand;
use Noerd\Commands\MakeDetailCommand;
use Noerd\Commands\MakeListCommand;
use Noerd\Commands\MakePageCommand;
use Noerd\Commands\MakeModuleCommand;
use Noerd\Commands\MakeResourceCommand;
use Noerd\Commands\MakeUserAdmin;
use Noerd\Commands\NoerdDemoCommand;
use Noerd\Commands\NoerdInstallCommand;
use Noerd\Commands\PublishHomeCommand;
use Noerd\Commands\NoerdUpdateCommand;
use Noerd\Listeners\InitializeTenantSession;
use Noerd\Middleware\AppAccessMiddleware;
use Noerd\Middleware\EnsureSetupCollectionDefinitionsEnabled;
use Noerd\Middleware\PublicAppMiddleware;
use Noerd\Middleware\SetupMiddleware;
use Noerd\Middleware\SetUserLocale;
use Noerd\Navigation\SetupCollectionsNavigationProvider;
use Noerd\Contracts\MediaResolverContract;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Repositories\DatabaseSetupCollectionDefinitionRepository;
use Noerd\Repositories\YamlSetupCollectionDefinitionRepository;
use Noerd\Services\DynamicNavigationRegistry;
use Noerd\Services\ListQueryContext;
use Noerd\Services\NullMediaResolver;
use Noerd\Services\PicklistRegistry;

class NoerdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge module defaults so noerd.* keys resolve even when the project
        // root config/noerd.php is absent (e.g., module-only test boots).
        $this->mergeConfigFrom(__DIR__ . '/../../config/noerd.php', 'noerd');

        $this->app->singleton(ListQueryContext::class);
        $this->app->singleton(DynamicNavigationRegistry::class);
        $this->app->singleton(PicklistRegistry::class);
        $this->app->singletonIf(MediaResolverContract::class, NullMediaResolver::class);

        // Bind the Setup collection definition repository based on the shared mode toggle.
        $this->app->singleton(SetupCollectionDefinitionRepositoryContract::class, function ($app) {
            $mode = config('noerd.collections.mode', 'yaml');

            return match ($mode) {
                'database' => new DatabaseSetupCollectionDefinitionRepository(),
                default => new YamlSetupCollectionDefinitionRepository(
                    base_path(config('noerd.collections.setup_yaml_path', 'app-configs/setup/collections')),
                ),
            };
        });

        // Register SetupCollectionHelper as singleton so static proxies resolve
        // the container-bound repository and tests can replace it.
        $this->app->singleton(SetupCollectionHelper::class, fn ($app) => new SetupCollectionHelper(
            $app->make(SetupCollectionDefinitionRepositoryContract::class)
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'noerd');
        Blade::component('app-layout', \Noerd\View\Components\AppLayout::class);
        Livewire::addLocation(viewPath: __DIR__ . '/../../resources/views/components');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'noerd');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/noerd-routes.php');

        // Register event listeners
        Event::listen(Login::class, InitializeTenantSession::class);

        $router = $this->app['router'];
        $router->aliasMiddleware('setup', SetupMiddleware::class);
        $router->aliasMiddleware('app-access', AppAccessMiddleware::class);
        $router->aliasMiddleware('public-app', PublicAppMiddleware::class);
        $router->aliasMiddleware('setup.collections.ui', EnsureSetupCollectionDefinitionsEnabled::class);
        $router->pushMiddlewareToGroup('web', SetUserLocale::class);

        // Register the Setup collections dynamic navigation provider.
        $registry = $this->app->make(DynamicNavigationRegistry::class);
        $registry->register($this->app->make(SetupCollectionsNavigationProvider::class));

        View::composer('noerd::layouts.app', function ($view): void {
            $view->with('showSidebar', ! session('hide_sidebar'));
        });

        // Publish public assets (fonts + built Vite assets)
        $this->publishes([
            __DIR__ . '/../../public' => public_path('vendor/noerd'),
            __DIR__ . '/../../dist/build' => public_path('vendor/noerd'),
        ], 'noerd-assets');

        // Auto-publish fonts if not exists (for development convenience)
        $this->publishFontsIfNotExists();

        // Auto-publish built assets if not exists
        $this->publishBuiltAssetsIfNotExist();

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeUserAdmin::class,
                NoerdInstallCommand::class,
                NoerdUpdateCommand::class,
                CreateTenantApp::class,
                AssignAppsToTenant::class,
                MakeModuleCommand::class,
                MakeResourceCommand::class,
                MakeListCommand::class,
                MakeDetailCommand::class,
                MakePageCommand::class,
                MakeDashboardCommand::class,
                MakeCollectionCommand::class,
                CreateAdminCommand::class,
                CreateTenantCommand::class,
                NoerdDemoCommand::class,
                PublishHomeCommand::class,
                ImportSetupCollectionDefinitionsCommand::class,
                ExportSetupCollectionDefinitionsCommand::class,
            ]);
        }
    }

    /**
     * Automatically copy fonts to public directory if they don't exist.
     */
    private function publishFontsIfNotExists(): void
    {
        $targetPath = public_path('vendor/noerd/fonts');
        $sourcePath = __DIR__ . '/../../public/fonts';

        if (! File::exists($targetPath) && File::exists($sourcePath)) {
            File::ensureDirectoryExists(dirname($targetPath));
            File::copyDirectory($sourcePath, $targetPath);
        }
    }

    /**
     * Automatically copy built Vite assets to public directory if they don't exist.
     */
    private function publishBuiltAssetsIfNotExist(): void
    {
        $targetPath = public_path('vendor/noerd/manifest.json');
        $sourcePath = __DIR__ . '/../../dist/build/manifest.json';

        if (! File::exists($sourcePath)) {
            return;
        }

        $shouldPublish = ! File::exists($targetPath)
            || File::lastModified($sourcePath) > File::lastModified($targetPath);

        if ($shouldPublish) {
            File::ensureDirectoryExists(public_path('vendor/noerd'));
            File::copyDirectory(__DIR__ . '/../../dist/build', public_path('vendor/noerd'));
        }
    }
}
