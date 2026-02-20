<?php

namespace Noerd\Helpers;

use Exception;
use Illuminate\Support\Facades\Log;
use Noerd\Models\TenantApp;
use Symfony\Component\Yaml\Yaml;

class StaticConfigHelper
{
    private static ?array $moduleSourceMappingCache = null;

    public static function getComponentFields(string $component): array
    {
        $yamlPath = self::findConfigPath("details/{$component}.yml");

        if (! $yamlPath) {
            $currentApp = self::getCurrentApp();
            Log::warning("Config file not found: details/{$component}.yml (app: {$currentApp})");

            return [];
        }

        $content = file_get_contents($yamlPath);

        return Yaml::parse($content ?: '');
    }

    public static function getListConfig(string $tableName): array
    {
        $yamlPath = self::findConfigPath("lists/{$tableName}.yml");

        if (! $yamlPath) {
            $currentApp = self::getCurrentApp();
            Log::warning("Config file not found: lists/{$tableName}.yml (app: {$currentApp})");

            return [];
        }

        $content = file_get_contents($yamlPath);

        return Yaml::parse($content ?: '');
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
     * Build dynamic Collections navigation based on .yml files in /content/collections/
     * Only includes collections with hasPage: false
     */
    public static function collections(): array
    {
        return self::getCollectionsByHasPage(hasPage: false);
    }

    /**
     * Build dynamic Page Collections navigation based on .yml files in /content/collections/
     * Only includes collections with hasPage: true
     */
    public static function pageCollections(): array
    {
        return self::getCollectionsByHasPage(hasPage: true);
    }

    /**
     * Build dynamic Setup Collections navigation based on .yml files in /app-configs/setup/collections/
     */
    public static function setupCollections(): array
    {
        $collectionsPath = base_path('app-configs/setup/collections');

        if (! is_dir($collectionsPath)) {
            return [];
        }

        $collectionFiles = glob($collectionsPath . '/*.yml');
        $dynamicNavigations = [];

        foreach ($collectionFiles as $file) {
            $collectionKey = basename($file, '.yml');

            try {
                $content = file_get_contents($file);
                $collectionData = Yaml::parse($content ?: '');

                if ($collectionData && isset($collectionData['titleList'])) {
                    $dynamicNavigations[] = [
                        'title' => $collectionData['titleList'],
                        'link' => "/setup-collections?key={$collectionKey}",
                        'heroicon' => 'archive-box',
                    ];
                }
            } catch (Exception) {
                // Skip invalid YAML files
                continue;
            }
        }

        return $dynamicNavigations;
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
     * Get collections filtered by hasPage property
     */
    private static function getCollectionsByHasPage(bool $hasPage): array
    {
        $collectionsPath = base_path('app-configs/cms/collections');

        if (! is_dir($collectionsPath)) {
            return [];
        }

        $collectionFiles = glob($collectionsPath . '/*.yml');
        $dynamicNavigations = [];

        foreach ($collectionFiles as $file) {
            $collectionKey = basename($file, '.yml');

            try {
                $content = file_get_contents($file);
                $collectionData = Yaml::parse($content ?: '');

                if ($collectionData && isset($collectionData['titleList'])) {
                    $collectionHasPage = $collectionData['hasPage'] ?? false;

                    if ($collectionHasPage === $hasPage) {
                        $dynamicNavigations[] = [
                            'title' => $collectionData['titleList'],
                            'link' => "/cms/collections?key={$collectionKey}",
                            'icon' => 'icons.list-bullet',
                        ];
                    }
                }
            } catch (Exception) {
                // Skip invalid YAML files
                continue;
            }
        }

        return $dynamicNavigations;
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
        $currentApp = self::getCurrentApp();

        // 1. First check current app
        if ($currentApp) {
            $primaryPath = base_path("app-configs/{$currentApp}/{$subPath}");
            if (file_exists($primaryPath)) {
                return $primaryPath;
            }
        }

        // 2. Fallback: Search all allowed app folders
        $allowedFolders = self::getAllowedAppFolders();

        $allAppFolders = TenantApp::where('is_active', true)
            ->pluck('name')
            ->map(fn($name) => mb_strtolower($name))
            ->toArray();

        foreach ($allAppFolders as $folder) {
            if (! in_array($folder, $allowedFolders)) {
                continue;
            }

            if ($folder === $currentApp) {
                continue;
            }

            $fallbackPath = base_path("app-configs/{$folder}/{$subPath}");
            if (file_exists($fallbackPath)) {

                return $fallbackPath;
            }
        }

        // 3. Fallback: Search module source files (app-contents)
        if ($currentApp) {
            $moduleSourcePath = self::getModuleSourcePath($currentApp);
            if ($moduleSourcePath) {
                $sourceFallbackPath = $moduleSourcePath . DIRECTORY_SEPARATOR . $subPath;
                if (file_exists($sourceFallbackPath)) {
                    return $sourceFallbackPath;
                }
            }
        }

        // 4. Fallback: Search other allowed apps' module sources
        foreach ($allowedFolders as $folder) {
            if ($folder === $currentApp) {
                continue;
            }

            $moduleSourcePath = self::getModuleSourcePath($folder);
            if ($moduleSourcePath) {
                $sourceFallbackPath = $moduleSourcePath . DIRECTORY_SEPARATOR . $subPath;
                if (file_exists($sourceFallbackPath)) {
                    return $sourceFallbackPath;
                }
            }
        }

        return null;
    }

    /**
     * Process dynamic navigation blocks based on YAML configuration
     */
    private static function processDynamicNavigation(array $navigationStructure): array
    {
        // Support legacy navigation structure with block_menus at top level
        if (isset($navigationStructure[0]['block_menus'])) {
            foreach ($navigationStructure as $i => $appBlock) {
                $blockMenus = $appBlock['block_menus'] ?? [];
                foreach ($blockMenus as $j => $menu) {
                    $dynamicType = $menu['dynamic'] ?? null;
                    if ($dynamicType === 'collections') {
                        $navigationStructure[$i]['block_menus'][$j]['navigations'] = self::collections();
                        unset($navigationStructure[$i]['block_menus'][$j]['dynamic']);
                    } elseif ($dynamicType === 'page-collections') {
                        $navigationStructure[$i]['block_menus'][$j]['navigations'] = self::pageCollections();
                        unset($navigationStructure[$i]['block_menus'][$j]['dynamic']);
                    } elseif ($dynamicType === 'setup-collections') {
                        $navigationStructure[$i]['block_menus'][$j]['navigations'] = self::setupCollections();
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
                if (($item['type'] ?? null) === 'dynamic' && isset($item['collection'])) {
                    $children = $item['children'] ?? [];
                    if (! is_array($children)) {
                        $children = [];
                    }
                    $navigationStructure['items'][$index]['children'] = array_merge($children, self::collections());
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

            if (isset($nav['superAdmin']) && $nav['superAdmin'] && ! auth()->user()?->isSuperAdmin()) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Copy components from a specific directory to app-modules
     */
    private static function copyComponentsFromDirectory(string $sourceDir, array $componentMapping, string $userGroup): array
    {
        $results = [];
        $files = glob($sourceDir . '/*.yml');

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
        $targetFile = $targetDir . "/{$componentName}.yml";

        // Create directory if it doesn't exist
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                return false;
            }
        }

        // Copy the file
        return copy($sourceFile, $targetFile);
    }

    /**
     * Get module source path for a given app-config key.
     * Maps app-configs/{app-key} -> app-modules/{module}/app-contents/{app-key}
     */
    private static function getModuleSourcePath(string $appKey): ?string
    {
        $mapping = self::getModuleSourceMapping();

        if (! isset($mapping[$appKey])) {
            return null;
        }

        $module = $mapping[$appKey];
        $sourcePath = base_path("app-modules/{$module}/app-contents/{$appKey}");

        return is_dir($sourcePath) ? $sourcePath : null;
    }

    /**
     * Dynamically discover module-to-app-config mappings.
     * Scans app-modules/{module}/app-contents/{app-key} directories.
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

            $appContentsPath = $appModulesPath . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'app-contents';
            if (! is_dir($appContentsPath)) {
                continue;
            }

            $appKeys = scandir($appContentsPath);
            foreach ($appKeys as $appKey) {
                if ($appKey === '.' || $appKey === '..') {
                    continue;
                }

                $fullPath = $appContentsPath . DIRECTORY_SEPARATOR . $appKey;
                if (is_dir($fullPath)) {
                    $mappings[$appKey] = $module;
                }
            }
        }

        self::$moduleSourceMappingCache = $mappings;

        return $mappings;
    }
}
