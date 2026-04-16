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
    'features' => [
        'multi_tenant' => env('NOERD_MULTI_TENANT', true),
        'roles' => env('NOERD_ROLE_FEATURE_ENABLED', true),
        'new_tenant' => env('NOERD_NEW_TENANT_FEATURE_ENABLED', true),
        'currency' => env('NOERD_CURRENCY_ENABLED', true),
    ],

    'collections' => [
        'mode' => env('NOERD_COLLECTIONS_MODE', 'yaml'),
        'show_definitions_ui' => env('NOERD_COLLECTIONS_MODE', 'yaml') === 'database',
        'setup_yaml_path' => 'app-configs/setup/collections',
    ],

    'theme' => [
        'active' => env('NOERD_THEME', 'default'),

        'presets' => [
            'default' => [
                'brand-bg'             => '#f9f9f9',
                'brand-navi'           => '#fafafa',
                'brand-navi-hover'     => '#f5f5f5',
                'brand-topbar'         => '#ffffff',
                'brand-primary'        => '#000',
                'brand-primary-text'   => '#fff',
                'brand-secondary'      => '#ffffff',
                'brand-secondary-text' => '#374151',
                'brand-danger'         => '#fecaca',
                'brand-danger-text'    => '#374151',
                'brand-border'         => '#000',
            ],
            'sand' => [
                'brand-bg'             => '#faf8f4',
                'brand-navi'           => '#f5f0e8',
                'brand-navi-hover'     => '#ede5d8',
                'brand-topbar'         => '#faf6ef',
                'brand-primary'        => '#000',
                'brand-primary-text'   => '#fff',
                'brand-secondary'      => '#ffffff',
                'brand-secondary-text' => '#374151',
                'brand-danger'         => '#fecaca',
                'brand-danger-text'    => '#374151',
                'brand-border'         => '#000',
            ],
            'white' => [
                'brand-bg'             => '#ffffff',
                'brand-navi'           => '#ffffff',
                'brand-navi-hover'     => '#f5f5f5',
                'brand-topbar'         => '#ffffff',
                'brand-primary'        => '#000',
                'brand-primary-text'   => '#fff',
                'brand-secondary'      => '#ffffff',
                'brand-secondary-text' => '#374151',
                'brand-danger'         => '#fecaca',
                'brand-danger-text'    => '#374151',
                'brand-border'         => '#000',
            ],
        ],

        'overrides' => [
            'brand-bg'             => env('NOERD_COLOR_BRAND_BG'),
            'brand-navi'           => env('NOERD_COLOR_BRAND_NAVI'),
            'brand-navi-hover'     => env('NOERD_COLOR_BRAND_NAVI_HOVER'),
            'brand-topbar'         => env('NOERD_COLOR_BRAND_TOPBAR'),
            'brand-primary'        => env('NOERD_COLOR_BRAND_PRIMARY'),
            'brand-primary-text'   => env('NOERD_COLOR_BRAND_PRIMARY_TEXT'),
            'brand-secondary'      => env('NOERD_COLOR_BRAND_SECONDARY'),
            'brand-secondary-text' => env('NOERD_COLOR_BRAND_SECONDARY_TEXT'),
            'brand-danger'         => env('NOERD_COLOR_BRAND_DANGER'),
            'brand-danger-text'    => env('NOERD_COLOR_BRAND_DANGER_TEXT'),
            'brand-border'         => env('NOERD_COLOR_BRAND_BORDER'),
        ],
    ],
];
