<?php

namespace Nywerk\Noerd\Helpers;

use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Nywerk\Noerd\Models\Tenant;
use Symfony\Component\Yaml\Yaml;

class StaticConfigHelper
{
    public static function getComponentFields(string $component): array
    {
        $client = Tenant::select('id', 'module')->where('id', Auth::user()->selected_tenant_id)->first();
        $app = mb_strtolower($client->module);
        $userGroup = 'admin'; // Auth::user()->user_group;

        // Priority order for component loading:
        // 1. Module-specific component (in app-modules)
        // 2. App-specific user group component (in content/apps)
        // 3. App-specific default component (in content/apps)
        // 4. Global user group component (in content/components)
        // 5. Global default component (in content/components)

        // 1. Check for module-specific component in app-modules
        $moduleComponentPath = base_path("app-modules/{$app}/content/components/{$component}.yml");
        if (file_exists($moduleComponentPath)) {
            $content = file_get_contents($moduleComponentPath);
            return Yaml::parse($content ?: '');
        }

        // 2. Check app-specific user group component in content/apps
        if (file_exists(base_path('content/apps/' . $app . '/components/' . $userGroup . '/' . $component . '.yml'))) {
            $content = file_get_contents(base_path('content/apps/' . $app . '/components/' . $userGroup . '/' . $component . '.yml'));
            return Yaml::parse($content ?: '');
        }
        
        // 3. Check app-specific default component in content/apps
        if (file_exists(base_path('content/apps/' . $app . '/components/default/' . $component . '.yml'))) {
            $content = file_get_contents(base_path('content/apps/' . $app . '/components/default/' . $component . '.yml'));
            return Yaml::parse($content ?: '');
        }

        // 4. Check global user group component
        if (file_exists(base_path('content/components/' . $userGroup . '/' . $component . '.yml'))) {
            $content = file_get_contents(base_path('content/components/' . $userGroup . '/' . $component . '.yml'));
            return Yaml::parse($content ?: '');
        }

        // 5. Fallback to global default component
        if (file_exists(base_path('content/components/default/' . $component . '.yml'))) {
            $content = file_get_contents(base_path('content/components/default/' . $component . '.yml'));
            return Yaml::parse($content ?: '');
        }

        // Return empty array if component not found
        return [];
    }

    public static function getNavigationStructure(): ?array
    {
        $currentApp = session('currentApp');
        // TODO CHANGE
        if (! $currentApp) {
            $currentApp = 'delivery';
        }
        $currentApp = mb_strtolower($currentApp);

        return Cache::remember(
            'getNavigationStructure-' . Auth::user()->selected_tenant_id . $currentApp . Auth::user()->id,
            3600,
            function () use ($currentApp) {
                // first check if app specific navigation exists
                if (file_exists(base_path('content/apps/' . $currentApp . '/navigation.yml'))) {
                    $content = file_get_contents(base_path('content/apps/' . $currentApp . '/navigation.yml'));
                    return Yaml::parse($content ?: '');
                }

                // allow to ger a specific navigation for a user group in the future
                $profile = mb_strtolower(Auth::user()?->currentProfile() ?? 'default');
                if (file_exists(base_path('content/apps/' . $currentApp . '/' . $profile . '/navigation.yml'))) {
                    $content = file_get_contents(base_path('content/apps/' . $currentApp . '/' . $profile . '/navigation.yml'));
                    return Yaml::parse($content ?: '');
                }

                try {
                    $content = file_get_contents(base_path('content/apps/' . $currentApp . '/default/navigation.yml'));
                    return Yaml::parse($content ?: '');
                } catch (Exception $e) {
                    return null;
                }
            },
        );
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
                    'success' => $success
                ];
            } else {
                $results[] = [
                    'component' => $componentName,
                    'module' => 'unknown',
                    'userGroup' => $userGroup,
                    'success' => false,
                    'reason' => 'No module mapping found'
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
