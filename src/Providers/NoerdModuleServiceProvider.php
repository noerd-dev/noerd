<?php

declare(strict_types=1);

namespace Noerd\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use LogicException;
use Noerd\Services\FieldTypeRegistry;
use Noerd\Services\PicklistRegistry;
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\FieldTypeDefinition;
use Noerd\Support\RelationFieldDefinition;
use ReflectionClass;

/**
 * The service provider of a noerd module. A module's plumbing follows one
 * layout — migrations, views, the Livewire namespace, translations, the route
 * file, config files, commands — so the provider derives all of it from the
 * module key and the module root instead of repeating the same seven lines.
 * What a module adds on top goes into the hooks: registerModule() for
 * bindings, bootModule() for everything else, and the three declarative
 * registries (relation field types, field types, picklist providers).
 *
 *     class HrServiceProvider extends NoerdModuleServiceProvider
 *     {
 *         protected string $moduleKey = 'hr';
 *         protected array $commands = [HrInstallCommand::class, HrUpdateCommand::class];
 *
 *         protected function relationFields(): array
 *         {
 *             return ['hrEmployeeRelation' => RelationFieldDefinition::model(...)];
 *         }
 *     }
 *
 * The Livewire namespace is the module key alone: a bare component name is
 * never registered (`Livewire::addLocation()`), so two modules can ship a
 * `settings-page` without colliding.
 */
abstract class NoerdModuleServiceProvider extends ServiceProvider
{
    /**
     * The module key: the view and Livewire namespace (`hr::employees-list`),
     * the route file (`routes/{key}-routes.php`) and the publish tag of the
     * config files (`{key}-config`).
     */
    protected string $moduleKey = '';

    /**
     * The module's artisan commands, registered on the console only — the
     * install and update command at least.
     *
     * @var list<class-string<\Illuminate\Console\Command>>
     */
    protected array $commands = [];

    /**
     * Config files below `config/` by name: `['media']` merges `config/media.php`
     * as `config('media')` and publishes it under the tag `{key}-config`.
     *
     * @var list<string>
     */
    protected array $configs = [];

    public function register(): void
    {
        foreach ($this->configs as $name) {
            $this->mergeConfigFrom($this->modulePath("config/{$name}.php"), $name);
        }

        $this->registerModule();
    }

    public function boot(): void
    {
        $key = $this->moduleKey();

        if (is_dir($migrations = $this->modulePath('database/migrations'))) {
            $this->loadMigrationsFrom($migrations);
        }

        if (is_dir($views = $this->modulePath('resources/views'))) {
            $this->loadViewsFrom($views, $key);
        }

        if (is_dir($components = $this->modulePath('resources/views/components'))) {
            Livewire::addNamespace($key, viewPath: $components);
        }

        if (is_dir($lang = $this->modulePath('resources/lang'))) {
            $this->loadJsonTranslationsFrom($lang);
            $this->loadTranslationsFrom($lang, $key);
        }

        foreach ($this->routeFiles() as $routeFile) {
            if (is_file($routeFile)) {
                $this->loadRoutesFrom($routeFile);
            }
        }

        if ($this->app->runningInConsole()) {
            $publishable = [];
            foreach ($this->configs as $name) {
                $publishable[$this->modulePath("config/{$name}.php")] = config_path("{$name}.php");
            }
            if ($publishable !== []) {
                $this->publishes($publishable, "{$key}-config");
            }

            if ($this->commands !== []) {
                $this->commands($this->commands);
            }
        }

        $relationFields = $this->relationFields();
        if ($relationFields !== []) {
            $registry = $this->app->make(RelationFieldRegistry::class);
            foreach ($relationFields as $type => $definition) {
                $registry->register($type, $definition);
            }
        }

        $fieldTypes = $this->fieldTypes();
        if ($fieldTypes !== []) {
            $registry = $this->app->make(FieldTypeRegistry::class);
            foreach ($fieldTypes as $type => $definition) {
                $registry->register($type, $definition);
            }
        }

        $picklists = $this->picklists();
        if ($picklists !== []) {
            $registry = $this->app->make(PicklistRegistry::class);
            foreach ($picklists as $name => $provider) {
                $registry->register($name, $provider);
            }
        }

        $this->bootModule();
    }

    /**
     * Container bindings of the module — runs inside register(), after the
     * config files were merged.
     */
    protected function registerModule(): void
    {
        // Intentionally empty.
    }

    /**
     * Everything the module boots on top of its plumbing: event listeners,
     * registrations on other registries, scheduled commands, runtime relations.
     */
    protected function bootModule(): void
    {
        // Intentionally empty.
    }

    /**
     * The relation field types the module contributes, `type => definition`
     * (`'customerRelation' => RelationFieldDefinition::model(...)`).
     *
     * @return array<string, RelationFieldDefinition>
     */
    protected function relationFields(): array
    {
        return [];
    }

    /**
     * The YAML field types the module contributes, `type => definition`.
     *
     * @return array<string, FieldTypeDefinition>
     */
    protected function fieldTypes(): array
    {
        return [];
    }

    /**
     * The picklist providers the module contributes, `name => callable`
     * (referenced as `optionsMethod:` / `picklistField:` in a YAML).
     *
     * @return array<string, callable>
     */
    protected function picklists(): array
    {
        return [];
    }

    /**
     * The route files to load; `routes/{key}-routes.php` unless the module
     * ships more or names them differently.
     *
     * @return list<string>
     */
    protected function routeFiles(): array
    {
        return [$this->modulePath("routes/{$this->moduleKey()}-routes.php")];
    }

    /**
     * The module root — the directory holding composer.json, derived from the
     * provider's location (`src/Providers/`). Override for a provider that
     * lives elsewhere.
     */
    protected function moduleRoot(): string
    {
        return dirname((string) (new ReflectionClass($this))->getFileName(), 3);
    }

    protected function modulePath(string $relative): string
    {
        return $this->moduleRoot() . '/' . mb_ltrim($relative, '/');
    }

    protected function moduleKey(): string
    {
        if ($this->moduleKey === '') {
            throw new LogicException(sprintf('%s must declare its $moduleKey.', static::class));
        }

        return $this->moduleKey;
    }
}
