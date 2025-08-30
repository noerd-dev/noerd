<?php

namespace Noerd\Noerd\Helpers;

use Exception;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Yaml\Yaml;

class StaticConfigHelper
{
    public static function getComponentFields(string $component): array
    {
        $userGroup = 'admin'; // Auth::user()->user_group;

        if (file_exists(base_path('content/components/' . $userGroup . '/' . $component . '.yml'))) {
            $content = file_get_contents(base_path('content/components/' . $userGroup . '/' . $component . '.yml'));
            return Yaml::parse($content ?: '');
        }



        if (file_exists(base_path('content/components/default/' . $component . '.yml'))) {
            $content = file_get_contents(base_path('content/components/default/' . $component . '.yml'));
            return Yaml::parse($content ?: '');
        }



        throw new Exception('Component not found: ' . $component);
    }

    public static function getTableConfig(string $tableName): array
    {
        $yamlPath = base_path("content/lists/{$tableName}.yml");

        if (!file_exists($yamlPath)) {
            return [];
        }

        $content = file_get_contents($yamlPath);
        return Yaml::parse($content ?: '');
    }

    public static function getNavigationStructure(): ?array
    {
        $currentApp = session('currentApp');
        // TODO CHANGE
        if (!$currentApp) {
            $currentApp = 'delivery';
        }
        $currentApp = mb_strtolower($currentApp);

        $navigationStructure = null;

        // first check if app specific navigation exists
        if (file_exists(base_path('content/apps/' . $currentApp . '/navigation.yml'))) {
            $content = file_get_contents(base_path('content/apps/' . $currentApp . '/navigation.yml'));
            $navigationStructure = Yaml::parse($content ?: '');
        }

        // allow to ger a specific navigation for a user group in the future
        if (!$navigationStructure) {
            $profile = mb_strtolower(Auth::user()?->currentProfile() ?? 'default');
            if (file_exists(base_path('content/apps/' . $currentApp . '/' . $profile . '/navigation.yml'))) {
                $content = file_get_contents(base_path('content/apps/' . $currentApp . '/' . $profile . '/navigation.yml'));
                $navigationStructure = Yaml::parse($content ?: '');
            }
        }

        if (!$navigationStructure) {
            try {
                $content = file_get_contents(base_path('content/apps/' . $currentApp . '/default/navigation.yml'));
                $navigationStructure = Yaml::parse($content ?: '');
            } catch (Exception $e) {
                return null;
            }
        }

        // Process dynamic navigation blocks
        if ($navigationStructure) {
            $navigationStructure = self::processDynamicNavigation($navigationStructure);
        }

        return $navigationStructure;
    }

    /**
     * Build dynamic Collections navigation based on .yml files in /content/collections/
     */
    public static function collections(): array
    {
        $collectionsPath = base_path('content/collections');

        if (!is_dir($collectionsPath)) {
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
                        'link' => "/cms/collections?key={$collectionKey}",
                        'icon' => 'icons.list-bullet',
                    ];
                }
            } catch (Exception $e) {
                // Skip invalid YAML files
                continue;
            }
        }

        return $dynamicNavigations;
    }

    /**
     * Copy global components to their respective app modules
     */
    public static function copyComponentsToModules(): array
    {
        $results = [];
        $componentMapping = self::getComponentToModuleMapping();

        // Process default components
        $defaultComponentsPath = base_path('content/components/default');
        if (is_dir($defaultComponentsPath)) {
            $results['default'] = self::copyComponentsFromDirectory($defaultComponentsPath, $componentMapping, 'default');
        }

        // Process admin components
        $adminComponentsPath = base_path('content/components/admin');
        if (is_dir($adminComponentsPath)) {
            $results['admin'] = self::copyComponentsFromDirectory($adminComponentsPath, $componentMapping, 'admin');
        }

        return $results;
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
                    if (($menu['dynamic'] ?? null) === 'collections') {
                        $navigationStructure[$i]['block_menus'][$j]['navigations'] = self::collections();
                        unset($navigationStructure[$i]['block_menus'][$j]['dynamic']);
                    }
                }
            }
            return $navigationStructure;
        }

        $items = $navigationStructure['items'] ?? [];
        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (($item['type'] ?? null) === 'dynamic' && isset($item['collection'])) {
                    $children = $item['children'] ?? [];
                    if (!is_array($children)) {
                        $children = [];
                    }
                    $navigationStructure['items'][$index]['children'] = array_merge($children, self::collections());
                }
            }
        }

        return $navigationStructure;
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
     * Define mapping between component names and modules
     */
    private static function getComponentToModuleMapping(): array
    {
        return [
            // Product related
            'product' => 'product',
            'product-group' => 'product',

            // Customer related
            'customer' => 'customer',

            // Delivery/Liefertool related
            'deliverySlot' => 'liefertool',
            'deliveryBlock' => 'liefertool',
            'deliverytime' => 'liefertool',
            'deliveryarea' => 'liefertool',
            'vehicle-component' => 'liefertool',
            'vehicle-configuration-component' => 'liefertool',
            'vehicleAssembly' => 'liefertool',
            'area-component' => 'liefertool',

            // Order related
            'orderConfirmation' => 'order',

            // Voucher related
            'voucher' => 'voucher',

            // Shop related
            'shop-notification' => 'shop',
            'store' => 'shop',

            // Menu/Canteen related
            'menu' => 'canteen',

            // Content/CMS related
            'page' => 'content',
            'site' => 'content',
            'text-content-component' => 'content',
            'textDocument' => 'content',

            // Legal register related
            'law' => 'legal-register',
            'lawReadOnly' => 'legal-register',
            'duty' => 'legal-register',
            'dutyReadOnly' => 'legal-register',

            // Production planning related
            'assembly-component' => 'production-planning',
            'part-component' => 'production-planning',
            'selectPart' => 'production-planning',

            // Harvester/PDM related
            'project' => 'harvester-project',
            'project-booking' => 'harvester-project',
            'sawmill' => 'pdm',

            // UKI related
            'mode' => 'uki',
            'mode-exception' => 'uki',
            'times' => 'uki',

            // Settings related
            'setting' => 'settings',
            'globalParameter' => 'settings',
            'tenant' => 'settings',
            'user' => 'settings',
            'userRole' => 'settings',

            // Document analyzer related
            'ocr-scanner-component' => 'document-analyzer',

            // Media related
            'prompt' => 'media',
            'promptCreate' => 'media',

            // Additional fields - could be used by multiple modules, default to content
            'additionalField' => 'content',

            // Accounting related
            'accounting' => 'accounting',
        ];
    }
}
