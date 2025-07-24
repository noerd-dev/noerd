<?php

namespace Nywerk\Noerd\Helpers;

use Exception;
use Illuminate\Support\Facades\Cache;
use Nywerk\Noerd\Models\Tenant;
use Symfony\Component\Yaml\Yaml;

class StaticConfigHelper
{
    public static function getComponentFields(string $component): array
    {
        $client = Tenant::select('id', 'module')->where('id', auth()->user()->selected_tenant_id)->first();
        $app = mb_strtolower($client->module);
        $userGroup = 'admin'; // auth()->user()->user_group;

        // first check if app specific component exists
        if (file_exists(base_path('content/apps/' . $app . '/components/' . $userGroup . '/' . $component . '.yml'))) {
            $content = file_get_contents(base_path('content/apps/' . $app . '/components/' . $userGroup . '/' . $component . '.yml'));
            return Yaml::parse($content ?: '');
        }
        if (file_exists(base_path('content/apps/' . $app . '/components/default/' . $component . '.yml'))) {
            $content = file_get_contents(base_path('content/apps/' . $app . '/components/default/' . $component . '.yml'));
            return Yaml::parse($content ?: '');
        }

        if (file_exists(base_path('content/components/' . $userGroup . '/' . $component . '.yml'))) {
            $content = file_get_contents(base_path('content/components/' . $userGroup . '/' . $component . '.yml'));
            return Yaml::parse($content ?: '');
        }

        $content = file_get_contents(base_path('content/components/default/' . $component . '.yml'));
        return Yaml::parse($content ?: '');
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
            'getNavigationStructure-' . auth()->user()->selected_tenant_id . $currentApp . auth()->user()->id,
            3600,
            function () use ($currentApp) {
                // first check if app specific navigation exists
                if (file_exists(base_path('content/apps/' . $currentApp . '/navigation.yml'))) {
                    $content = file_get_contents(base_path('content/apps/' . $currentApp . '/navigation.yml'));
                    return Yaml::parse($content ?: '');
                }

                // allow to ger a specific navigation for a user group in the future
                $profile = mb_strtolower(auth()->user()?->currentProfile() ?? 'default');
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
}
