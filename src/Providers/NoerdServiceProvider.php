<?php

namespace Noerd\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;
use Noerd\Commands\AssignAppsToTenant;
use Noerd\Commands\CreateAdminCommand;
use Noerd\Commands\CreateTenantApp;
use Noerd\Commands\CreateTenantCommand;
use Noerd\Commands\MakeCollectionCommand;
use Noerd\Commands\MakeModuleCommand;
use Noerd\Commands\MakeUserAdmin;
use Noerd\Commands\NoerdInstallCommand;
use Noerd\Commands\NoerdUpdateCommand;
use Noerd\Listeners\InitializeTenantSession;
use Noerd\Middleware\AppAccessMiddleware;
use Noerd\Middleware\PublicAppMiddleware;
use Noerd\Middleware\SetupMiddleware;
use Noerd\Middleware\SetUserLocale;
use Noerd\Services\ListQueryContext;
use Noerd\View\Components\AppLayout;

class NoerdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ListQueryContext::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'noerd');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'noerd');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/noerd-routes.php');

        // Register event listeners
        Event::listen(Login::class, InitializeTenantSession::class);

        $router = $this->app['router'];
        $router->aliasMiddleware('setup', SetupMiddleware::class);
        $router->aliasMiddleware('app-access', AppAccessMiddleware::class);
        $router->aliasMiddleware('public-app', PublicAppMiddleware::class);
        $router->pushMiddlewareToGroup('web', SetUserLocale::class);

        Volt::mount(__DIR__ . '/../../resources/views/livewire');

        // Register Blade components
        Blade::component('app-layout', AppLayout::class);

        View::composer('noerd::components.layouts.app', function ($view): void {
            $view->with('showSidebar', ! session('hide_sidebar'));
        });

        config(['livewire.layout' => 'noerd::components.layouts.app']);

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
                MakeCollectionCommand::class,
                CreateAdminCommand::class,
                CreateTenantCommand::class,
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

        if (! File::exists($targetPath) && File::exists($sourcePath)) {
            File::ensureDirectoryExists(public_path('vendor/noerd'));
            File::copyDirectory(__DIR__ . '/../../dist/build', public_path('vendor/noerd'));
        }
    }
}
