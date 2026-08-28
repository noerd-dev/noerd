<?php

namespace Noerd\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\ComponentHookRegistry;
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
use Noerd\Commands\MakeModuleCommand;
use Noerd\Commands\MakePageCommand;
use Noerd\Commands\MakeResourceCommand;
use Noerd\Commands\MakeThemeCommand;
use Noerd\Commands\MakeUserAdmin;
use Noerd\Commands\NoerdDemoCommand;
use Noerd\Commands\NoerdInfoCommand;
use Noerd\Commands\NoerdInstallCommand;
use Noerd\Commands\NoerdUpdateAllCommand;
use Noerd\Commands\NoerdUpdateCommand;
use Noerd\Commands\PublishHomeCommand;
use Noerd\Contracts\MediaResolverContract;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Helpers\CurrencyHelper;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\TenantHelper;
use Noerd\Helpers\ThemeHelper;
use Noerd\Listeners\InitializeTenantSession;
use Noerd\Middleware\AppAccessMiddleware;
use Noerd\Middleware\EnsureSetupCollectionDefinitionsEnabled;
use Noerd\Middleware\NoerdAuthenticate;
use Noerd\Middleware\NoerdRedirectIfAuthenticated;
use Noerd\Middleware\PublicAppMiddleware;
use Noerd\Middleware\SetupMiddleware;
use Noerd\Middleware\SetUserLocale;
use Noerd\Models\SetupLanguage;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Noerd\Navigation\SetupCollectionsNavigationProvider;
use Noerd\Repositories\DatabaseSetupCollectionDefinitionRepository;
use Noerd\Repositories\YamlSetupCollectionDefinitionRepository;
use Noerd\Services\BrandService;
use Noerd\Services\DetailSlotsRegistry;
use Noerd\Services\DynamicNavigationRegistry;
use Noerd\Services\FieldTypeRegistry;
use Noerd\Services\HeaderActionsRegistry;
use Noerd\Services\NavigationService;
use Noerd\Services\NoerdManager;
use Noerd\Services\NullMediaResolver;
use Noerd\Services\PicklistRegistry;
use Noerd\Services\RelationBoxRegistry;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Services\RelationTitleResolver;
use Noerd\Services\ThemeRegistry;
use Noerd\Services\TopBarRegistry;
use Noerd\Support\ComponentAccessHook;
use Noerd\Support\DefaultCountries;
use Noerd\Support\FieldTypeDefinition;
use Noerd\Support\LockedPropertiesHook;
use Noerd\Support\FieldContext;
use Noerd\Support\QuickCreateExitHook;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Support\RelationFormPersistHook;
use Noerd\Support\SchemaColumnCache;
use Noerd\Support\ThemeContext;
use Noerd\Support\WriteGuardHook;
use Noerd\View\Components\AppLayout;

class NoerdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge module defaults so noerd.* keys resolve even when the project
        // root config/noerd.php is absent (e.g., module-only test boots).
        $this->mergeConfigFrom(__DIR__ . '/../../config/noerd.php', 'noerd');

        $this->registerNoerdGuard();

        // Register the quick-create exit hook in the register phase: Livewire wires
        // its ComponentHookRegistry during its own boot(), so a boot()-phase
        // registration here could land too late. The register phase of all providers
        // runs before any boot(), and the static call needs no container binding.
        ComponentHookRegistry::register(QuickCreateExitHook::class);
        ComponentHookRegistry::register(RelationFormPersistHook::class);
        ComponentHookRegistry::register(LockedPropertiesHook::class);
        ComponentHookRegistry::register(WriteGuardHook::class);
        ComponentHookRegistry::register(ComponentAccessHook::class);

        $this->app->singleton(DynamicNavigationRegistry::class);
        $this->app->singleton(TopBarRegistry::class);
        $this->app->singleton(HeaderActionsRegistry::class);
        $this->app->singleton(DetailSlotsRegistry::class);
        $this->app->singleton(RelationBoxRegistry::class);
        $this->app->singleton(FieldTypeRegistry::class);
        $this->app->singleton(ThemeRegistry::class);
        $this->app->singleton(NoerdManager::class);
        $this->app->singleton(RelationFieldRegistry::class, fn($app) => new RelationFieldRegistry(
            $app->make(FieldTypeRegistry::class),
        ));
        // Singleton so the per-request FK-title lookups are memoized.
        $this->app->singleton(RelationTitleResolver::class);
        $this->app->singleton(PicklistRegistry::class);
        $this->app->singleton(BrandService::class);
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
        $this->app->singleton(SetupCollectionHelper::class, fn($app) => new SetupCollectionHelper(
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
        });

        // Migrations change what the schema cache describes — never keep
        // serving the pre-migration column listing.
        Event::listen(\Illuminate\Database\Events\MigrationsEnded::class, fn() => SchemaColumnCache::clear());

        // Under Octane booted() fires once per WORKER, not per request — the
        // same flush must run before every request, or one user's memoized
        // tenant/config/theme state leaks into the next request the worker
        // serves. Guarded by class_exists so the listener only registers when
        // Octane is installed. The schema cache deliberately survives requests.
        if (class_exists(\Laravel\Octane\Events\RequestReceived::class)) {
            Event::listen(\Laravel\Octane\Events\RequestReceived::class, function (): void {
                $this->flushRequestState();
                // Stateful singletons are rebuilt per request: the title
                // resolver memoizes tenant-scoped DB values.
                $this->app->forgetInstance(RelationTitleResolver::class);
            });
        }

        // The navigation is injected by several layout views per page — a
        // singleton keeps it at one build per request (the structure itself is
        // additionally memoized in StaticConfigHelper's runtime cache).
        $this->app->singleton(NavigationService::class);
    }

    /**
     * Drop every request-scoped PHP static the package maintains. Runs on app
     * boot (fresh per test in one Pest process, per-request no-op under FPM)
     * and — via the Octane RequestReceived listener — before every Octane
     * request. The schema column listing is deliberately not part of this
     * flush: it describes the database structure, not request state (see the
     * boot hook and the MigrationsEnded listener).
     */
    private function flushRequestState(): void
    {
        StaticConfigHelper::flushRuntimeCaches();
        TenantHelper::clearCache();
        ThemeHelper::clearCache();
        ThemeContext::clear();
        FieldContext::clear();
        CurrencyHelper::clearCache();
        DatabaseSetupCollectionDefinitionRepository::resetCache();
        $this->app->make(ThemeRegistry::class)->clearCache();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'noerd');
        Blade::component('app-layout', AppLayout::class);
        Livewire::addNamespace('noerd', viewPath: __DIR__ . '/../../resources/views/components');
        // The un-namespaced location is load-bearing public API: the generic
        // relation field components resolve by their bare names
        // ('noerd-relation-field', 'noerd-polymorphic-relation-field' — the
        // RelationFieldRegistry defaults, documented in the guideline). Note it
        // also exposes every other noerd component un-namespaced; new code must
        // reference components via the 'noerd::' namespace.
        Livewire::addLocation(viewPath: __DIR__ . '/../../resources/views/components');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'noerd');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/noerd-routes.php');

        // Register event listeners
        Event::listen(Login::class, InitializeTenantSession::class);

        // Create default languages and setup collections when a new tenant is created
        Tenant::created(function (Tenant $tenant): void {
            SetupLanguage::ensureDefaultLanguagesForTenant($tenant->id);
            DefaultCountries::ensureForTenant($tenant->id);
        });

        // The config search roots memoise the active/allowed app folders — a
        // TenantApp mutation (install commands, setup screens) invalidates them.
        TenantApp::saved(fn() => StaticConfigHelper::flushRuntimeCaches());
        TenantApp::deleted(fn() => StaticConfigHelper::flushRuntimeCaches());

        $router = $this->app['router'];
        // Shared route middleware groups: every noerd-based module protects
        // its routes with the 'noerd' group instead of the bare 'auth' alias,
        // so authentication always runs against noerd's own guard. The noerd
        // subclasses pin the redirect targets to noerd's own routes — the
        // host's 'auth'/'guest' aliases and any redirectUsing() callbacks
        // (e.g. from a coexisting starter kit) never apply here. The
        // 'verified' middleware resolves $request->user() after auth's
        // shouldUse() call, so it is guard-correct (and currently inert —
        // NoerdUser does not implement MustVerifyEmail).
        $router->middlewareGroup('noerd', ['web', NoerdAuthenticate::class . ':' . NoerdAuth::guardName(), 'verified']);
        $router->middlewareGroup('noerd-guest', ['web', NoerdRedirectIfAuthenticated::class . ':' . NoerdAuth::guardName()]);
        // Livewire's default persistent-middleware list only knows the
        // framework's Authenticate class — the subclass must be added so
        // component updates stay auth-protected.
        Livewire::addPersistentMiddleware([NoerdAuthenticate::class]);
        $router->aliasMiddleware('setup', SetupMiddleware::class);
        $router->aliasMiddleware('app-access', AppAccessMiddleware::class);
        $router->aliasMiddleware('public-app', PublicAppMiddleware::class);
        $router->aliasMiddleware('setup.collections.ui', EnsureSetupCollectionDefinitionsEnabled::class);
        $router->pushMiddlewareToGroup('web', SetUserLocale::class);

        // Register the Setup collections dynamic navigation provider.
        $registry = $this->app->make(DynamicNavigationRegistry::class);
        $registry->register($this->app->make(SetupCollectionsNavigationProvider::class));

        // Theme folders: the project root wins over module themes, which win
        // over the noerd built-ins (default, compact, numbered).
        $themeRegistry = $this->app->make(ThemeRegistry::class);
        $themeRegistry->registerPath(resource_path('views/themes'), priority: 100);
        $themeRegistry->registerPath(__DIR__ . '/../../resources/views/themes', priority: 0);

        $fieldTypeRegistry = $this->app->make(FieldTypeRegistry::class);
        $relationFieldRegistry = $this->app->make(RelationFieldRegistry::class);
        $fieldTypeRegistry->register('select', FieldTypeDefinition::include(
            'noerd::components.forms.input-select',
            resolver: function (array $field, mixed $component, mixed $detailData, mixed $modelId): array {
                $optionsMethod = $field['optionsMethod'] ?? null;

                if ($optionsMethod && $component && method_exists($component, $optionsMethod)) {
                    $resolved = $component->{$optionsMethod}();
                    $options = [];

                    foreach ($resolved as $value => $label) {
                        $options[] = ['value' => $value, 'label' => $label];
                    }

                    $field['options'] = $options;
                }

                return ['field' => $field];
            },
        ));
        $fieldTypeRegistry->register('picklist', FieldTypeDefinition::include(
            'noerd::components.forms.picklist',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('setupCollectionSelect', FieldTypeDefinition::include(
            'noerd::components.forms.setup-collection-select',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('belongsToMany', FieldTypeDefinition::include(
            'noerd::components.forms.belongs-to-many',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('checkbox', FieldTypeDefinition::include(
            'noerd::components.forms.checkbox',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('image', FieldTypeDefinition::include(
            'noerd::components.forms.image',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => [
                'field' => $field,
                'detailData' => $detailData,
            ],
        ));
        $fieldTypeRegistry->register('richText', FieldTypeDefinition::include(
            'noerd::components.forms.rich-text',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('translatableRichText', FieldTypeDefinition::include(
            'noerd::components.forms.translatable-rich-text',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('translatableText', FieldTypeDefinition::include(
            'noerd::components.forms.translatable-text',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('translatableTextarea', FieldTypeDefinition::include(
            'noerd::components.forms.translatable-textarea',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('button', FieldTypeDefinition::include(
            'noerd::components.forms.button',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('colorHex', FieldTypeDefinition::include(
            'noerd::components.forms.color-hex',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('currency', FieldTypeDefinition::include(
            'noerd::components.forms.input-currency',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('textarea', FieldTypeDefinition::include(
            'noerd::components.forms.input-textarea',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('file', FieldTypeDefinition::include(
            'noerd::components.forms.file',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('spacer', FieldTypeDefinition::include(
            'noerd::components.forms.spacer',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('phone', FieldTypeDefinition::include(
            'noerd::components.forms.phone',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('email', FieldTypeDefinition::include(
            'noerd::components.forms.email',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
        ));
        $fieldTypeRegistry->register('icon', FieldTypeDefinition::include(
            'noerd::components.forms.icon',
            resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => [
                'field' => $field,
                'iconValue' => data_get($component?->detailData ?? $detailData ?? [], Str::after($field['name'] ?? '', 'detailData.')),
            ],
        ));

        // Some project-level app-configs reference legal-register even when the
        // module is not installed. Register the type so YAML stays explicit.
        $relationFieldRegistry->register('lawRelation', RelationFieldDefinition::model(
            listComponent: 'laws-list',
            detailComponent: 'law-detail',
            modelClass: 'Noerd\\LegalRegister\\Models\\Law',
            titleResolver: 'title',
        ));

        View::composer('noerd::layouts.app', function ($view): void {
            $view->with('showSidebar', !session('hide_sidebar'));
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
                NoerdInfoCommand::class,
                NoerdInstallCommand::class,
                NoerdUpdateCommand::class,
                NoerdUpdateAllCommand::class,
                CreateTenantApp::class,
                AssignAppsToTenant::class,
                MakeModuleCommand::class,
                MakeResourceCommand::class,
                MakeListCommand::class,
                MakeDetailCommand::class,
                MakePageCommand::class,
                MakeDashboardCommand::class,
                MakeCollectionCommand::class,
                MakeThemeCommand::class,
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
     * Register noerd's dedicated auth guard, user provider and password
     * broker at runtime. The host's config/auth.php is never written to,
     * and any key the host already defines wins over the injection.
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
                'model' => NoerdAuth::userModel(),
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

        // Escape hatch for hosts whose routes still use the bare 'auth'
        // middleware: make the noerd guard the application default.
        if (config('noerd.auth.set_as_default')) {
            config([
                'auth.defaults.guard' => $guard,
                'auth.defaults.passwords' => $broker,
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

        if (!File::exists($targetPath) && File::exists($sourcePath)) {
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

        if (!File::exists($sourcePath)) {
            return;
        }

        $shouldPublish = !File::exists($targetPath)
            || File::lastModified($sourcePath) > File::lastModified($targetPath);

        if ($shouldPublish) {
            File::ensureDirectoryExists(public_path('vendor/noerd'));
            File::copyDirectory(__DIR__ . '/../../dist/build', public_path('vendor/noerd'));
        }
    }
}
