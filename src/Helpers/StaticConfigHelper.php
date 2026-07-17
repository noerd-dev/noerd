<?php

namespace Noerd\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Noerd\Contracts\LayoutOverrideResolver;
use Noerd\Models\TenantApp;
use Noerd\Services\DynamicNavigationRegistry;
use Symfony\Component\Yaml\Yaml;
use Throwable;

class StaticConfigHelper
{
    private static ?array $moduleSourceMappingCache = null;

    /**
     * @param  class-string|null  $modelClass  the model the detail renders, when known — forwarded to the
     *                                         override resolver, which cannot read it off the YAML.
     */
    public static function getComponentFields(string $component, ?string $modelClass = null): array
    {
        $subPath = self::componentToSubPath($component);
        $yamlPath = self::findConfigPath("details/{$subPath}.yml");

        if (! $yamlPath) {
            $currentApp = self::getCurrentApp();
            Log::warning("Config file not found: details/{$subPath}.yml (app: {$currentApp})");

            return [];
        }

        $content = file_get_contents($yamlPath);

        return self::applyOverrides('detail', self::stripComponentNamespace($component), Yaml::parse($content ?: '') ?: [], $modelClass);
    }

    /**
     * Like getComponentFields() but stays silent when the config is missing.
     * Used by generic list features that probe a paired detail config and must
     * not spam warnings for lists whose detail name does not match the convention.
     */
    public static function tryGetComponentFields(string $component): array
    {
        $subPath = self::componentToSubPath($component);
        $yamlPath = self::findConfigPath("details/{$subPath}.yml");

        if (! $yamlPath) {
            return [];
        }

        return Yaml::parse(file_get_contents($yamlPath) ?: '') ?: [];
    }

    /**
     * @param  class-string|null  $modelClass  the model the list renders, when known — forwarded to the
     *                                         override resolver, which cannot read it off the YAML.
     */
    public static function getListConfig(string $tableName, ?string $modelClass = null): array
    {
        $subPath = self::componentToListName($tableName);
        $yamlPath = self::findConfigPath("lists/{$subPath}.yml");

        if (! $yamlPath) {
            // A "{name}--{key}" view without a YAML file of its own is a
            // resolver-defined view: it materializes as the base config with the
            // override for the full suffixed name applied on top.
            if (str_contains($tableName, '--')) {
                $baseSubPath = Str::before($subPath, '--');
                $yamlPath = self::findConfigPath("lists/{$baseSubPath}.yml");
            }

            if (! $yamlPath) {
                $currentApp = self::getCurrentApp();
                Log::warning("Config file not found: lists/{$subPath}.yml (app: {$currentApp})");

                return [];
            }
        }

        $content = file_get_contents($yamlPath);

        return self::applyOverrides('list', self::componentToListName($tableName), Yaml::parse($content ?: '') ?: [], $modelClass);
    }

    /**
     * Like getListConfig() but resolves the YAML for an EXPLICIT app instead of the
     * session-driven current app. Backs list views selected from another app: the
     * session app stays unchanged while the list renders the foreign app's config.
     * Overrides still apply with the app-agnostic component key, matching getListConfig().
     *
     * @param  class-string|null  $modelClass
     */
    public static function getListConfigForApp(string $app, string $tableName, ?string $modelClass = null): array
    {
        $yamlPath = self::resolveConfigPath($app, 'list', $tableName);

        // A "{name}--{key}" view without a YAML file of its own is a
        // resolver-defined view: it materializes as the base config with the
        // override for the full suffixed name applied on top.
        if (! $yamlPath && str_contains($tableName, '--')) {
            $yamlPath = self::resolveConfigPath($app, 'list', Str::before($tableName, '--'));
        }

        if (! $yamlPath) {
            $subPath = self::componentToListName($tableName);
            Log::warning("Config file not found: lists/{$subPath}.yml (app: {$app})");

            return [];
        }

        $content = file_get_contents($yamlPath);

        return self::applyOverrides('list', self::componentToListName($tableName), Yaml::parse($content ?: '') ?: [], $modelClass);
    }

    /**
     * Resolve the on-disk path of a list/detail YAML for an EXPLICIT app, bypassing
     * the session-driven current-app context. Used by tooling that must read the raw,
     * un-overridden config of an arbitrary app while a different app is selected.
     */
    public static function resolveConfigPath(string $app, string $viewType, string $component): ?string
    {
        $dir = $viewType === 'detail' ? 'details' : 'lists';
        $subPath = $viewType === 'detail'
            ? self::componentToSubPath($component)
            : self::componentToListName($component);

        $primaryPath = base_path("app-configs/{$app}/{$dir}/{$subPath}.yml");
        if (file_exists($primaryPath)) {
            return $primaryPath;
        }

        $moduleSource = self::getModuleSourcePath($app);
        if ($moduleSource) {
            $sourcePath = $moduleSource.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.$subPath.'.yml';
            if (file_exists($sourcePath)) {
                return $sourcePath;
            }
        }

        return null;
    }

    /**
     * Public accessor for the module-source directory of an app-config key
     * (app-modules/{module}/app-configs/{app}). Returns null when no module ships
     * that app.
     */
    public static function moduleSourcePathForApp(string $app): ?string
    {
        return self::getModuleSourcePath($app);
    }

    public static function getNavigationStructure(): ?array
    {
        $currentApp = self::getCurrentApp();

        if (! $currentApp) {
            return null;
        }

        $yamlPath = base_path("app-configs/{$currentApp}/navigation.yml");

        if (! file_exists($yamlPath)) {
            return null;
        }

        $content = file_get_contents($yamlPath);
        $navigationStructure = Yaml::parse($content ?: '');

        // Process dynamic navigation blocks
        if ($navigationStructure) {
            $navigationStructure = self::processDynamicNavigation($navigationStructure);
        }

        return $navigationStructure;
    }

    public static function getCurrentApp(): ?string
    {
        $selectedApp = TenantHelper::getSelectedApp();

        return $selectedApp ? mb_strtolower($selectedApp) : null;
    }

    /**
     * Clear the module source mapping cache.
     * Call this after installing new modules.
     */
    public static function clearModuleSourceCache(): void
    {
        self::$moduleSourceMappingCache = null;
    }

    /**
     * Discover all views of a list config across EVERY allowed app: per app the base
     * YAML (key 'default') plus any "{name}--{key}.yml" sibling variants. Within one
     * app the project app-configs shadow the module source; different apps never
     * shadow each other — each contributes its own entries. Current-app entries use
     * plain keys ('default', 'vip'); other apps' entries use composite keys
     * ('{app}::{key}'). Views the override resolver defines in its own storage (no
     * YAML file) are merged into the current-app group, a file view winning a key
     * collision.
     *
     * Ordering: current app first, then the other allowed apps; 'default' leads each
     * app group, remaining variants alphabetical.
     *
     * @return array<string, array{key: string, app: string, appLabel: string, title: string}>
     */
    public static function getListViews(string $component): array
    {
        $subPath = self::componentToListName($component);
        $currentApp = self::getCurrentApp();

        $apps = $currentApp ? [$currentApp] : [];
        foreach (self::getAllowedAppFolders() as $folder) {
            if ($folder !== $currentApp) {
                $apps[] = $folder;
            }
        }

        // Without a session app the first searched folder used to win — keep its
        // entries on plain keys so the base view stays addressable as 'default'.
        $plainApp = $currentApp ?? ($apps[0] ?? null);

        $resolver = app(LayoutOverrideResolver::class);
        $appLabels = self::appLabels();

        $views = [];
        foreach ($apps as $app) {
            $roots = array_filter([
                base_path("app-configs/{$app}"),
                self::getModuleSourcePath($app),
            ]);

            $paths = [];
            foreach ($roots as $root) {
                $basePath = $root.DIRECTORY_SEPARATOR."lists/{$subPath}.yml";
                if (! isset($paths['default']) && file_exists($basePath)) {
                    $paths['default'] = $basePath;
                }

                $variantPaths = glob($root.DIRECTORY_SEPARATOR."lists/{$subPath}--*.yml") ?: [];
                foreach ($variantPaths as $variantPath) {
                    $key = Str::afterLast(basename($variantPath, '.yml'), '--');
                    if ($key === '' || isset($paths[$key])) {
                        continue;
                    }
                    $paths[$key] = $variantPath;
                }
            }

            $appViews = [];
            foreach ($paths as $key => $path) {
                try {
                    $config = Yaml::parse(file_get_contents($path) ?: '') ?: [];
                } catch (Throwable) {
                    // An unparseable variant is dropped; the base view must survive
                    // so the list keeps rendering with its config's own error handling.
                    if ($key !== 'default') {
                        continue;
                    }
                    $config = [];
                }
                $appViews[$key] = (string) ($config['title'] ?? $key);
            }

            if ($app === $plainApp) {
                $resolverViews = $resolver->listViews(self::componentToListName($component));
                unset($resolverViews['default']);
                $appViews += $resolverViews;
            }

            $defaultView = array_key_exists('default', $appViews) ? ['default' => $appViews['default']] : [];
            unset($appViews['default']);
            ksort($appViews);

            foreach ($defaultView + $appViews as $key => $title) {
                $views[self::composeListViewKey($app === $plainApp ? null : $app, $key)] = [
                    'key' => $key,
                    'app' => $app,
                    'appLabel' => $appLabels[$app] ?? Str::ucfirst($app),
                    'title' => $title,
                ];
            }
        }

        // Last word goes to the resolver: it may hide views (incl. 'default')
        // the current user is not allowed to see.
        return $resolver->filterListViews(self::componentToListName($component), $views);
    }

    /**
     * Split a list-view key into its source app and plain view key. Plain keys
     * ('default', 'vip') belong to the current app and yield a null app; composite
     * keys ('gastro::vip') name the app folder explicitly.
     *
     * @return array{0: ?string, 1: string} [appFolder|null, plainKey]
     */
    public static function parseListViewKey(string $key): array
    {
        if (! str_contains($key, '::')) {
            return [null, $key];
        }

        [$app, $viewKey] = explode('::', $key, 2);

        return [$app, $viewKey === '' ? 'default' : $viewKey];
    }

    /**
     * Inverse of parseListViewKey(): null app => plain key, null view key => 'default'.
     */
    public static function composeListViewKey(?string $app, ?string $viewKey): string
    {
        $viewKey = $viewKey === null || $viewKey === '' ? 'default' : $viewKey;

        return $app === null ? $viewKey : "{$app}::{$viewKey}";
    }

    /**
     * Display labels for the allowed app folders: folder (lowercase) => TenantApp title.
     * Folders without a TenantApp row (e.g. 'setup') fall back in getListViews().
     *
     * @return array<string, string>
     */
    private static function appLabels(): array
    {
        $labels = ['setup' => __('Setup')];

        $tenant = TenantHelper::getSelectedTenant();
        if ($tenant) {
            foreach ($tenant->tenantApps()->pluck('title', 'name') as $name => $title) {
                $labels[mb_strtolower((string) $name)] = (string) $title;
            }
        }

        return $labels;
    }

    /**
     * Apply registered layout overrides to a parsed config.
     * No-op by default; a module may rebind the resolver.
     *
     * $component must be the canonical key — the config's identity, namespace
     * stripped, as componentToSubPath() derives the file path from. Callers may hand
     * getListConfig()/getComponentFields() a namespaced livewire name instead
     * ('customer::customers-list'), which would key an override off the calling
     * component rather than off the config it renders.
     *
     * @param  array<string, mixed>  $config
     * @param  class-string|null  $modelClass
     * @return array<string, mixed>
     */
    private static function applyOverrides(string $viewType, string $component, array $config, ?string $modelClass = null): array
    {
        return app(LayoutOverrideResolver::class)
            ->apply($viewType, $component, $config, $modelClass);
    }

    private static function stripComponentNamespace(string $component): string
    {
        return str_contains($component, '::') ? explode('::', $component, 2)[1] : $component;
    }

    /**
     * Convert a component name (e.g. "booking::bookings.booking-detail") to a
     * filesystem sub-path relative to details/ (e.g. "bookings/booking-detail").
     * Dots in the component name map to directory separators to support subfolder
     * organization — DETAILS ONLY; list configs always resolve flat, see
     * componentToListName().
     */
    private static function componentToSubPath(string $component): string
    {
        $name = self::stripComponentNamespace($component);

        return str_replace('.', DIRECTORY_SEPARATOR, $name);
    }

    /**
     * Convert a component name to its flat list YAML name. List configs always
     * live directly in lists/ (never in subfolders), so the subfolder segments of
     * a nested component name are dropped:
     * "booking-members::stamp-cards.customer-stamp-cards-list" → "customer-stamp-cards-list".
     */
    private static function componentToListName(string $component): string
    {
        return Str::afterLast(self::stripComponentNamespace($component), '.');
    }

    /**
     * Get allowed app folders for the current tenant.
     * Convention: folder name = strtolower(TenantApp.name)
     */
    private static function getAllowedAppFolders(): array
    {
        $tenant = TenantHelper::getSelectedTenant();
        if (! $tenant) {
            return ['setup'];
        }

        $tenantAppNames = $tenant->tenantApps()->pluck('name')->toArray();

        $allowedFolders = ['setup'];
        foreach ($tenantAppNames as $appName) {
            $allowedFolders[] = mb_strtolower($appName);
        }

        return $allowedFolders;
    }

    /**
     * Find config path with fallback to other allowed apps and module sources.
     */
    private static function findConfigPath(string $subPath): ?string
    {
        foreach (self::configSearchRoots() as $root) {
            $path = $root.DIRECTORY_SEPARATOR.$subPath;
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Ordered base directories that findConfigPath() searches: current app
     * app-configs, other allowed apps' app-configs, current app's module source,
     * other allowed apps' module sources. Discovery features (getListViews) walk
     * the same roots so loading and discovery can never diverge.
     *
     * @return array<int, string>
     */
    private static function configSearchRoots(): array
    {
        $currentApp = self::getCurrentApp();
        $roots = [];

        // 1. First check current app
        if ($currentApp) {
            $roots[] = base_path("app-configs/{$currentApp}");
        }

        // 2. Fallback: Search all allowed app folders
        $allowedFolders = self::getAllowedAppFolders();

        $allAppFolders = TenantApp::where('is_active', true)
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower($name))
            ->toArray();

        $searchFolders = array_unique(array_merge($allAppFolders, $allowedFolders));

        foreach ($searchFolders as $folder) {
            if (! in_array($folder, $allowedFolders) || $folder === $currentApp) {
                continue;
            }

            $roots[] = base_path("app-configs/{$folder}");
        }

        // 3. Fallback: Search module source files (app-configs)
        if ($currentApp) {
            $moduleSourcePath = self::getModuleSourcePath($currentApp);
            if ($moduleSourcePath) {
                $roots[] = $moduleSourcePath;
            }
        }

        // 4. Fallback: Search other allowed apps' module sources
        foreach ($allowedFolders as $folder) {
            if ($folder === $currentApp) {
                continue;
            }

            $moduleSourcePath = self::getModuleSourcePath($folder);
            if ($moduleSourcePath) {
                $roots[] = $moduleSourcePath;
            }
        }

        return array_values(array_unique($roots));
    }

    /**
     * Process dynamic navigation blocks based on YAML configuration
     */
    private static function processDynamicNavigation(array $navigationStructure): array
    {
        $registry = app(DynamicNavigationRegistry::class);

        // Support legacy navigation structure with block_menus at top level
        if (isset($navigationStructure[0]['block_menus'])) {
            foreach ($navigationStructure as $i => $appBlock) {
                $blockMenus = $appBlock['block_menus'] ?? [];
                foreach ($blockMenus as $j => $menu) {
                    $dynamicType = $menu['dynamic'] ?? null;
                    if ($dynamicType) {
                        $provider = $registry->resolve($dynamicType);
                        if ($provider) {
                            $navigationStructure[$i]['block_menus'][$j]['navigations'] = $provider->items();
                        }
                        unset($navigationStructure[$i]['block_menus'][$j]['dynamic']);
                    }

                    // Filter navigation items by config attribute
                    $navigations = $navigationStructure[$i]['block_menus'][$j]['navigations'] ?? [];
                    $navigationStructure[$i]['block_menus'][$j]['navigations'] = self::filterNavigationByConfig($navigations);
                }
            }

            return $navigationStructure;
        }

        $items = $navigationStructure['items'] ?? [];
        if (is_array($items)) {
            foreach ($items as $index => $item) {
                $dynamicType = $item['dynamic'] ?? ($item['collection'] ?? null);
                if (($item['type'] ?? null) === 'dynamic' && $dynamicType) {
                    $provider = $registry->resolve($dynamicType);
                    if ($provider) {
                        $children = $item['children'] ?? [];
                        if (! is_array($children)) {
                            $children = [];
                        }
                        $navigationStructure['items'][$index]['children'] = array_merge($children, $provider->items());
                    }
                }
            }
        }

        return $navigationStructure;
    }

    /**
     * Filter navigation items based on the config and superAdmin attributes.
     * Items with a config attribute are only included if the config value is truthy.
     * Items with superAdmin: true are only included if the current user is a super admin.
     */
    private static function filterNavigationByConfig(array $navigations): array
    {
        return array_values(array_filter($navigations, function ($nav) {
            if (isset($nav['config']) && ! config($nav['config'])) {
                return false;
            }

            return ! (isset($nav['superAdmin']) && $nav['superAdmin'] && ! auth()->user()?->isSuperAdmin());
        }));
    }

    /**
     * Copy components from a specific directory to app-modules
     */
    private static function copyComponentsFromDirectory(string $sourceDir, array $componentMapping, string $userGroup): array
    {
        $results = [];
        $files = glob($sourceDir.'/*.yml');

        foreach ($files as $file) {
            $componentName = basename($file, '.yml');
            $module = $componentMapping[$componentName] ?? null;

            if ($module) {
                $success = self::copyComponentToModule($file, $module, $componentName);
                $results[] = [
                    'component' => $componentName,
                    'module' => $module,
                    'userGroup' => $userGroup,
                    'success' => $success,
                ];
            } else {
                $results[] = [
                    'component' => $componentName,
                    'module' => 'unknown',
                    'userGroup' => $userGroup,
                    'success' => false,
                    'reason' => 'No module mapping found',
                ];
            }
        }

        return $results;
    }

    /**
     * Copy a single component to its target app-module
     */
    private static function copyComponentToModule(string $sourceFile, string $module, string $componentName): bool
    {
        $targetDir = base_path("app-modules/{$module}/content/components");
        $targetFile = $targetDir."/{$componentName}.yml";

        // Create directory if it doesn't exist
        if (! is_dir($targetDir)) {
            if (! mkdir($targetDir, 0755, true)) {
                return false;
            }
        }

        // Copy the file
        return copy($sourceFile, $targetFile);
    }

    /**
     * Get module source path for a given app-config key.
     * Maps app-configs/{app-key} -> app-modules/{module}/app-configs/{app-key}
     */
    private static function getModuleSourcePath(string $appKey): ?string
    {
        $mapping = self::getModuleSourceMapping();

        if (! isset($mapping[$appKey])) {
            return null;
        }

        $module = $mapping[$appKey];
        $sourcePath = base_path("app-modules/{$module}/app-configs/{$appKey}");

        return is_dir($sourcePath) ? $sourcePath : null;
    }

    /**
     * Dynamically discover module-to-app-config mappings.
     * Scans app-modules/{module}/app-configs/{app-key} directories.
     *
     * @return array<string, string> Map of app-key => module-name
     */
    private static function getModuleSourceMapping(): array
    {
        if (self::$moduleSourceMappingCache !== null) {
            return self::$moduleSourceMappingCache;
        }

        $mappings = [];
        $appModulesPath = base_path('app-modules');

        if (! is_dir($appModulesPath)) {
            self::$moduleSourceMappingCache = $mappings;

            return $mappings;
        }

        $modules = scandir($appModulesPath);
        foreach ($modules as $module) {
            if ($module === '.' || $module === '..') {
                continue;
            }

            $appConfigsPath = $appModulesPath.DIRECTORY_SEPARATOR.$module.DIRECTORY_SEPARATOR.'app-configs';
            if (! is_dir($appConfigsPath)) {
                continue;
            }

            $appKeys = scandir($appConfigsPath);
            foreach ($appKeys as $appKey) {
                if ($appKey === '.' || $appKey === '..') {
                    continue;
                }

                $fullPath = $appConfigsPath.DIRECTORY_SEPARATOR.$appKey;
                if (is_dir($fullPath)) {
                    $mappings[$appKey] = $module;
                }
            }
        }

        self::$moduleSourceMappingCache = $mappings;

        return $mappings;
    }
}
