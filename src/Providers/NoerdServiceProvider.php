<?php

namespace Noerd\Noerd\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;
use Noerd\Noerd\Commands\AddUsersToDefaultTenant;
use Noerd\Noerd\Commands\AssignAppsToTenant;
use Noerd\Noerd\Commands\CreateTenantApp;
use Noerd\Noerd\Commands\MakeModuleCommand;
use Noerd\Noerd\Commands\MakeUserAdmin;
use Noerd\Noerd\Commands\NoerdInstallCommand;
use Noerd\Noerd\Middleware\SetupMiddleware;
use Noerd\Noerd\View\Components\AppLayout;

class NoerdServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'noerd');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'noerd');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/noerd-routes.php');

        $router = $this->app['router'];
        $router->aliasMiddleware('setup', SetupMiddleware::class);

        Volt::mount(__DIR__ . '/../../resources/views/livewire');

        // Register Blade components
        Blade::component('app-layout', AppLayout::class);

        View::composer('noerd::components.layouts.app', function ($view): void {
            $view->with('showSidebar', ! session('hide_sidebar'));
        });

        config(['livewire.layout' => 'noerd::components.layouts.app']);

        // Publish public assets (fonts)
        $this->publishes([
            __DIR__ . '/../../public' => public_path('vendor/noerd'),
        ], 'noerd-assets');

        // Auto-publish fonts if not exists (for development convenience)
        $this->publishFontsIfNotExists();

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeUserAdmin::class,
                AddUsersToDefaultTenant::class,
                NoerdInstallCommand::class,
                CreateTenantApp::class,
                AssignAppsToTenant::class,
                MakeModuleCommand::class,
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
}
