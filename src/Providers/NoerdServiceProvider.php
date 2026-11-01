<?php

declare(strict_types=1);

namespace Noerd\Providers;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Livewire\Livewire;
use Noerd\Commands\AssignAppsToTenantCommand;
use Noerd\Commands\ExportSetupCollectionDefinitionsCommand;
use Noerd\Commands\ImportSetupCollectionDefinitionsCommand;
use Noerd\Commands\LintCommand;
use Noerd\Commands\MakeAdminUserCommand;
use Noerd\Commands\MakeAppCommand;
use Noerd\Commands\MakeCollectionCommand;
use Noerd\Commands\MakeDashboardCommand;
use Noerd\Commands\MakeModuleCommand;
use Noerd\Commands\MakePageCommand;
use Noerd\Commands\MakeResourceCommand;
use Noerd\Commands\MakeTenantCommand;
use Noerd\Commands\MakeThemeCommand;
use Noerd\Commands\NoerdDemoCommand;
use Noerd\Commands\NoerdInfoCommand;
use Noerd\Commands\NoerdInstallCommand;
use Noerd\Commands\NoerdUpdateAllCommand;
use Noerd\Commands\NoerdUpdateCommand;
use Noerd\Commands\PromoteAdminCommand;
use Noerd\Commands\SuperAdminCommand;
use Noerd\Contracts\MediaResolverContract;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Listeners\InitializeTenantSession;
use Noerd\Listeners\RecordLogin;
use Noerd\Livewire\Hooks\NoerdHooks;
use Noerd\Middleware\ActionPermissionMiddleware;
use Noerd\Middleware\AppAccessMiddleware;
use Noerd\Middleware\EnsureSetupCollectionDefinitionsEnabled;
use Noerd\Middleware\EnsureTenantMembership;
use Noerd\Middleware\NoerdAuthenticate;
use Noerd\Middleware\NoerdRedirectIfAuthenticated;
use Noerd\Middleware\ProfileMiddleware;
use Noerd\Middleware\SetupMiddleware;
use Noerd\Middleware\SetUserLocale;
use Noerd\Models\NoerdUser;
use Noerd\Navigation\SetupCollectionsNavigationProvider;
use Noerd\Repositories\DatabaseSetupCollectionDefinitionRepository;
use Noerd\Repositories\YamlSetupCollectionDefinitionRepository;
use Noerd\Services\ActionPermissionRegistry;
use Noerd\Services\BrandService;
use Noerd\Services\ComponentSlotsRegistry;
use Noerd\Services\ComputedColumnRegistry;
use Noerd\Services\DynamicNavigationRegistry;
use Noerd\Services\FieldTypeRegistry;
use Noerd\Services\ListCellRendererRegistry;
use Noerd\Services\NavigationService;
use Noerd\Services\NoerdManager;
use Noerd\Services\NullMediaResolver;
use Noerd\Services\PicklistRegistry;
use Noerd\Services\PositionTableRegistry;
use Noerd\Services\ProfileRegistry;
use Noerd\Services\RelationBoxRegistry;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Services\RelationTitleResolver;
use Noerd\Services\ThemeRegistry;
use Noerd\Support\BuiltInFieldTypes;
use Noerd\Support\FieldContext;
use Noerd\Support\ListColumnIntrospection;
use Noerd\Support\ModelMethodColumns;
use Noerd\Support\RequestState;
use Noerd\Support\SchemaColumnCache;
use Noerd\Support\ShellState;
use Noerd\Support\TenantContext;
use Noerd\Support\ThemeContext;

class NoerdServiceProvider extends ServiceProvider
{
    /** Payload key that carries the dispatching tenant into a queued job. */
    public const JOB_TENANT_KEY = 'noerd_tenant_id';

    /**
     * The registries and services of the package — one instance per container,
     * registered by the framework right after register(). The per-request memos
     * (scoped) and the conditional media binding stay in register().
     *
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        ActionPermissionRegistry::class => ActionPermissionRegistry::class,
        ProfileRegistry::class => ProfileRegistry::class,
        DynamicNavigationRegistry::class => DynamicNavigationRegistry::class,
        ComponentSlotsRegistry::class => ComponentSlotsRegistry::class,
        RelationBoxRegistry::class => RelationBoxRegistry::class,
        FieldTypeRegistry::class => FieldTypeRegistry::class,
        // Autowired: its only dependency is the FieldTypeRegistry singleton.
        RelationFieldRegistry::class => RelationFieldRegistry::class,
        ListCellRendererRegistry::class => ListCellRendererRegistry::class,
        ThemeRegistry::class => ThemeRegistry::class,
        PicklistRegistry::class => PicklistRegistry::class,
        PositionTableRegistry::class => PositionTableRegistry::class,
        ModelMethodColumns::class => ModelMethodColumns::class,
        NoerdManager::class => NoerdManager::class,
        BrandService::class => BrandService::class,
    ];

    public function register(): void
    {
        // Merge module defaults so noerd.* keys resolve even when the project
        // root config/noerd.php is absent (e.g., module-only test boots).
        $this->mergeConfigFrom(__DIR__ . '/../../config/noerd.php', 'noerd');

        // Derived, never configured: the definitions UI belongs to the database
        // mode. Read from a config key of its own it silently drifted apart from
        // the mode (published config edited without the env var), leaving the
        // routes open while the navigation entry stayed hidden.
        config(['noerd.collections.show_definitions_ui' => config('noerd.collections.mode') === 'database']);

        $this->registerNoerdGuard();

        // The component hooks are registered in the register phase: Livewire wires
        // its ComponentHookRegistry during its own boot(), so a boot()-phase
        // registration here could land too late.
        NoerdHooks::register();

        // Request-scoped memos (FK titles, the request state, the tenant
        // context): rebuilt for every request and queued job.
        $this->app->scoped(RelationTitleResolver::class);
        $this->app->scoped(RequestState::class);
        $this->app->scoped(TenantContext::class);
        $this->app->singletonIf(MediaResolverContract::class, NullMediaResolver::class);
        // Providers of list columns / detail fields without a stored value; the
        // core computes `method:` itself, packages register further providers.
        $this->app->singleton(ComputedColumnRegistry::class, function (): ComputedColumnRegistry {
            $registry = new ComputedColumnRegistry();
            $registry->register(ModelMethodColumns::class);

            return $registry;
        });

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
        $this->app->scoped(SetupCollectionHelper::class, fn($app) => new SetupCollectionHelper(
            $app->make(SetupCollectionDefinitionRepositoryContract::class),
        ));

        // StaticConfigHelper's memos are PHP statics, which outlive the app
        // instance inside one Pest process — flush them whenever a fresh app
        // boots (a per-request no-op under FPM, where statics die anyway).
        $this->app->booted(function (): void {
            $this->flushRequestState();
            // The schema cache survives ordinary boots (it describes database
            // structure, not request state) — but a fresh app in a test process
            // may point at a DIFFERENT database (testbench sqlite vs. host
            // MySQL) whose tables share names, so it must reset here too.
            SchemaColumnCache::clear();
            ListColumnIntrospection::clear();
        });

        // Migrations change what the schema cache describes — never keep
        // serving the pre-migration column listing.
        Event::listen(MigrationsEnded::class, function (): void {
            SchemaColumnCache::clear();
            ListColumnIntrospection::clear();
        });

        // Request state lives in SCOPED services (RequestState, the title
        // resolver, the navigation, the setup collection helper): Octane and the
        // queue worker drop them before every request and every job. Only the
        // render-time contexts below are PHP statics; under Octane booted()
        // fires once per WORKER, so they are reset before every request as well.
        // The schema cache deliberately survives requests.
        if (class_exists(RequestReceived::class)) {
            Event::listen(RequestReceived::class, fn() => $this->flushRequestState());
        }

        // The navigation is injected by several layout views per page — scoped
        // keeps it at one build per request (the structure itself is
        // additionally memoized in StaticConfigHelper's runtime cache).
        $this->app->scoped(NavigationService::class);
    }

    public function boot(): void
    {
        $this->bootResources();
        $this->bootEventListeners();
        $this->bootRouting();
        $this->bootRegistries();
        BuiltInFieldTypes::register($this->app->make(FieldTypeRegistry::class));

        View::composer('noerd::layouts.app', function ($view): void {
            $view->with('showSidebar', ShellState::sidebarVisible());
        });

        $this->bootConsole();
    }

    /**
     * Everything the package loads into the framework: migrations, views,
     * Livewire namespaces, translations and routes.
     */
    private function bootResources(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'noerd');
        Livewire::addNamespace('noerd', viewPath: __DIR__ . '/../../resources/views/components');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'noerd');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/noerd-routes.php');
    }

    /**
     * Auth events (tenant session, login log) and the tenant context of
     * requests and queued jobs.
     */
    private function bootEventListeners(): void
    {
        Event::listen(Login::class, InitializeTenantSession::class);
        Event::listen(Authenticated::class, InitializeTenantSession::class);
        Event::listen(Login::class, RecordLogin::class);

        // A request's tenant context ends with the request (frontend middleware
        // sets it; a test process serves many requests from one container).
        $this->app->terminating(fn() => TenantContext::reset());

        // A queued job works in the tenant of the request or job that dispatched it.
        Queue::createPayloadUsing(function (): array {
            $tenantId = TenantHelper::currentTenantId();

            return $tenantId === null ? [] : [self::JOB_TENANT_KEY => $tenantId];
        });
        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $tenantId = $event->job->payload()[self::JOB_TENANT_KEY] ?? null;
            TenantContext::enterJob(is_numeric($tenantId) ? (int) $tenantId : null);
        });
        Event::listen([JobProcessed::class, JobFailed::class, JobExceptionOccurred::class], fn() => TenantContext::leaveJob());
    }

    /**
     * The shared middleware groups and route middleware aliases every
     * noerd-based module protects its routes with.
     */
    private function bootRouting(): void
    {
        $router = $this->app['router'];
        // Shared route middleware groups: every noerd-based module protects
        // its routes with the 'noerd' group instead of the bare 'auth' alias,
        // so authentication always runs against noerd's own guard. The noerd
        // subclasses pin the redirect targets to noerd's own routes — the
        // host's 'auth'/'guest' aliases and any redirectUsing() callbacks
        // (e.g. from a coexisting starter kit) never apply here.
        $router->middlewareGroup('noerd', ['web', NoerdAuthenticate::class . ':' . NoerdAuth::guardName(), EnsureTenantMembership::class]);
        $router->middlewareGroup('noerd-guest', ['web', NoerdRedirectIfAuthenticated::class . ':' . NoerdAuth::guardName()]);
        // Livewire's default persistent-middleware list only knows the
        // framework's Authenticate class — the subclass must be added so
        // component updates stay auth-protected.
        Livewire::addPersistentMiddleware([NoerdAuthenticate::class]);
        $router->aliasMiddleware('setup', SetupMiddleware::class);
        $router->aliasMiddleware('profile', ProfileMiddleware::class);
        $router->aliasMiddleware('app-access', AppAccessMiddleware::class);
        $router->aliasMiddleware('action-permission', ActionPermissionMiddleware::class);
        $router->aliasMiddleware('setup.collections.ui', EnsureSetupCollectionDefinitionsEnabled::class);
        $router->pushMiddlewareToGroup('web', SetUserLocale::class);
    }

    /**
     * The package's own entries in the extension registries: the Setup
     * collections navigation provider and the theme folder roots.
     */
    private function bootRegistries(): void
    {
        $this->app->make(DynamicNavigationRegistry::class)
            ->register($this->app->make(SetupCollectionsNavigationProvider::class));

        // Theme folders: the project root wins over module themes, which win
        // over the noerd built-ins (default, compact, numbered).
        $themeRegistry = $this->app->make(ThemeRegistry::class);
        $themeRegistry->registerPath(resource_path('views/themes'), priority: 100);
        $themeRegistry->registerPath(__DIR__ . '/../../resources/views/themes', priority: 0);
    }

    /**
     * Publishable assets/config and the artisan commands — console only.
     */
    private function bootConsole(): void
    {
        // Publishing runs ONLY in console: a live web request must never write
        // to public/ (concurrent requests race on the copy, and a production
        // public/ is typically not writable by the PHP user). noerd:install and
        // noerd:update publish the assets explicitly — the provider itself never
        // writes into the host, not even in a local checkout.
        if ($this->app->runningInConsole()) {
            // Publish public assets (fonts + built Vite assets)
            $this->publishes([
                __DIR__ . '/../../public' => public_path('vendor/noerd'),
                __DIR__ . '/../../dist/build' => public_path('vendor/noerd'),
            ], 'noerd-assets');

            // The host config is normally published by noerd:install; the tag
            // exists for hosts that publish everything through vendor:publish.
            $this->publishes([
                __DIR__ . '/../../stubs/noerd.php.stub' => config_path('noerd.php'),
            ], 'noerd-config');

            $this->commands([
                PromoteAdminCommand::class,
                SuperAdminCommand::class,
                NoerdInfoCommand::class,
                LintCommand::class,
                NoerdInstallCommand::class,
                NoerdUpdateCommand::class,
                NoerdUpdateAllCommand::class,
                MakeAppCommand::class,
                AssignAppsToTenantCommand::class,
                MakeModuleCommand::class,
                MakeResourceCommand::class,
                MakePageCommand::class,
                MakeDashboardCommand::class,
                MakeCollectionCommand::class,
                MakeThemeCommand::class,
                MakeAdminUserCommand::class,
                MakeTenantCommand::class,
                NoerdDemoCommand::class,
                ImportSetupCollectionDefinitionsCommand::class,
                ExportSetupCollectionDefinitionsCommand::class,
            ]);
        }
    }

    /**
     * Drop the PHP statics the package still keeps: the parsed-YAML cache, the
     * render-time theme/field contexts, the ICU currency formats and the theme
     * discovery. Every request memo lives in the scoped RequestState instead,
     * which the container drops per Octane request and queued job by itself.
     * Runs on app boot (fresh per test in one Pest process) and before every
     * Octane request. The schema column listing is deliberately not part of this
     * flush: it describes the database structure (see the boot hook and the
     * MigrationsEnded listener).
     */
    private function flushRequestState(): void
    {
        StaticConfigHelper::flushRuntimeCaches();
        ThemeContext::clear();
        FieldContext::clear();
        CurrencyHelper::clearCache();
        $this->app->make(ThemeRegistry::class)->clearCache();
    }

    /**
     * Register noerd's dedicated auth guard, user provider and password
     * broker at runtime. The host's config/auth.php is never written to,
     * and any key the host already defines wins over the injection. The
     * application's DEFAULT guard is never touched: noerd routes select the
     * noerd guard per request (NoerdAuthenticate), everything else keeps
     * resolving the host's guard.
     */
    private function registerNoerdGuard(): void
    {
        $guard = NoerdAuth::guardName();
        $provider = NoerdAuth::providerName();
        $broker = NoerdAuth::brokerName();

        if (config("auth.guards.{$guard}") === null) {
            config(["auth.guards.{$guard}" => [
                'driver' => 'session',
                'provider' => $provider,
            ]]);
        }

        if (config("auth.providers.{$provider}") === null) {
            config(["auth.providers.{$provider}" => [
                'driver' => 'eloquent',
                'model' => NoerdUser::class,
            ]]);
        }

        if (config("auth.passwords.{$broker}") === null) {
            config(["auth.passwords.{$broker}" => [
                'provider' => $provider,
                'table' => 'password_reset_tokens',
                'expire' => 60,
                'throttle' => 60,
            ]]);
        }
    }
}
