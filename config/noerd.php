<?php

/*
|--------------------------------------------------------------------------
| Noerd module defaults
|--------------------------------------------------------------------------
|
| These defaults are merged into the noerd config namespace. The project
| root config/noerd.php overrides these values via Laravel's normal merge
| order — this file only guarantees resolvable keys when the noerd module
| runs standalone (e.g., module-only test boots).
|
*/

return [
    'auth' => [
        // Guard noerd registers and authenticates against. Set to 'web' to
        // restore the legacy behavior (host default guard).
        'guard' => env('NOERD_AUTH_GUARD', 'noerd'),

        // Authenticatable model backing the noerd user provider.
        'model' => env('NOERD_AUTH_MODEL', Noerd\Models\NoerdUser::class),

        // Provider / password-broker names registered into auth.providers
        // and auth.passwords at runtime (skipped when the host defines them).
        'provider' => 'noerd_users',
        'passwords' => 'noerd_users',

        // When true, noerd also becomes the app's DEFAULT guard at runtime —
        // escape hatch for hosts with unmigrated bare-'auth' routes.
        'set_as_default' => env('NOERD_AUTH_DEFAULT', false),
    ],

    'features' => [
        'multi_tenant' => env('NOERD_MULTI_TENANT', true),
        'new_tenant' => env('NOERD_NEW_TENANT_FEATURE_ENABLED', true),
        'currency' => env('NOERD_CURRENCY_ENABLED', true),
    ],

    'collections' => [
        'mode' => env('NOERD_COLLECTIONS_MODE', 'yaml'),
        'show_definitions_ui' => env('NOERD_COLLECTIONS_MODE', 'yaml') === 'database',
        'setup_yaml_path' => 'app-configs/setup/collections',
    ],

    'currency' => [
        'symbol' => '€',
        'decimal_separator' => ',',
        'thousands_separator' => '.',
        'symbol_position' => 'after',
    ],

    'branding' => [
        'logo' => env('NOERD_LOGO', ''),
        'favicon' => env('NOERD_FAVICON', ''),
        'auth_background_image' => env('NOERD_AUTH_BACKGROUND_IMAGE', ''),
    ],

    'sidebar' => [
        'apps_width' => env('NOERD_SIDEBAR_APPS_WIDTH', '80px'),
        'navigation_width' => env('NOERD_SIDEBAR_NAVIGATION_WIDTH', '280px'),
    ],

    'generators' => [
        'search_modules' => true,
        'modules_path' => 'app-modules',
    ],

    'cache' => [
        'object_catalog' => env('NOERD_OBJECT_CATALOG_CACHE', true),
    ],

    'theme' => [
        'default' => env('NOERD_THEME', 'default'),
        'enforced' => env('NOERD_THEME_ENFORCED', false),
    ],

    'keyboard_shortcuts' => [
        'search_focus' => 's',
        'new_entry' => 'n',
        'save' => 'ctrl+enter',
        'delete' => 'ctrl+backspace',
    ],

    'brand' => [
        'active' => env('NOERD_BRAND', 'default'),

        'presets' => [
            'default' => [
                'brand-bg' => '#f9f9f9',
                'brand-navi' => '#fafafa',
                'brand-navi-hover' => '#f5f5f5',
                'brand-primary' => '#000',
                'brand-primary-text' => '#fff',
                'brand-secondary' => '#ffffff',
                'brand-secondary-text' => '#374151',
                'brand-danger' => '#fecaca',
                'brand-danger-text' => '#374151',
                'brand-border' => '#000',
            ],
            'sand' => [
                'brand-bg' => '#faf8f4',
                'brand-navi' => '#f5f0e8',
                'brand-navi-hover' => '#ede5d8',
                'brand-primary' => '#000',
                'brand-primary-text' => '#fff',
                'brand-secondary' => '#ffffff',
                'brand-secondary-text' => '#374151',
                'brand-danger' => '#fecaca',
                'brand-danger-text' => '#374151',
                'brand-border' => '#000',
            ],
            'white' => [
                'brand-bg' => '#ffffff',
                'brand-navi' => '#ffffff',
                'brand-navi-hover' => '#f5f5f5',
                'brand-primary' => '#000',
                'brand-primary-text' => '#fff',
                'brand-secondary' => '#ffffff',
                'brand-secondary-text' => '#374151',
                'brand-danger' => '#fecaca',
                'brand-danger-text' => '#374151',
                'brand-border' => '#000',
            ],
        ],

        'overrides' => [
            'brand-bg' => env('NOERD_COLOR_BRAND_BG'),
            'brand-navi' => env('NOERD_COLOR_BRAND_NAVI'),
            'brand-navi-hover' => env('NOERD_COLOR_BRAND_NAVI_HOVER'),
            'brand-primary' => env('NOERD_COLOR_BRAND_PRIMARY'),
            'brand-primary-text' => env('NOERD_COLOR_BRAND_PRIMARY_TEXT'),
            'brand-secondary' => env('NOERD_COLOR_BRAND_SECONDARY'),
            'brand-secondary-text' => env('NOERD_COLOR_BRAND_SECONDARY_TEXT'),
            'brand-danger' => env('NOERD_COLOR_BRAND_DANGER'),
            'brand-danger-text' => env('NOERD_COLOR_BRAND_DANGER_TEXT'),
            'brand-border' => env('NOERD_COLOR_BRAND_BORDER'),
        ],
    ],
];
