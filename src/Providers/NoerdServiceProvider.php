<?php

namespace Noerd\Noerd\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;
use Noerd\Noerd\Commands\AddUsersToDefaultTenant;
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

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeUserAdmin::class,
                AddUsersToDefaultTenant::class,
                NoerdInstallCommand::class,
            ]);
        }
    }
}
