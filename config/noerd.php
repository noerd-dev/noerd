<?php

declare(strict_types=1);

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
    'routes' => [
        // URL prefix for the noerd core routes (login, apps, user, ...).
        // Route NAMES are unaffected — only the URLs carry the prefix.
        'prefix' => env('NOERD_ROUTE_PREFIX', 'noerd'),
    ],

    'features' => [
        'multi_tenant' => env('NOERD_MULTI_TENANT', true),
        'new_tenant' => env('NOERD_NEW_TENANT_FEATURE_ENABLED', true),
        'currency' => env('NOERD_CURRENCY_ENABLED', true),
    ],

    'collections' => [
        // 'yaml' or 'database'. The definitions UI flag that the setup
        // navigation gates on is DERIVED from this in NoerdServiceProvider —
        // never configure it separately.
        'mode' => env('NOERD_COLLECTIONS_MODE', 'yaml'),
        'setup_yaml_path' => 'app-configs/setup/collections',
    ],

    /*
     * The installation-wide default currency (ISO 4217, one of
     * Noerd\Helpers\CurrencyHelper::CURRENCIES). A tenant overrides it in
     * Setup → System Settings; every amount in the system is an amount in
     * the tenant currency.
     */
    'currency' => [
        'default' => env('NOERD_CURRENCY', 'EUR'),
    ],

    /*
     * Number, date and currency FORMATTING (ICU). The locale is resolved per
     * reader: the user's profile locale → the tenant locale (Setup → System
     * Settings, also used for every document) → `locale` below → the default
     * locale of the interface language (Noerd\Support\Locales). The remaining
     * keys pin a format for the whole installation and win over the locale.
     */
    'format' => [
        'locale' => env('NOERD_FORMAT_LOCALE'),
        'date' => env('NOERD_FORMAT_DATE'),
        'datetime' => env('NOERD_FORMAT_DATETIME'),
        'decimal_separator' => env('NOERD_FORMAT_DECIMAL_SEPARATOR'),
        'thousands_separator' => env('NOERD_FORMAT_THOUSANDS_SEPARATOR'),
        'csv_delimiter' => env('NOERD_CSV_DELIMITER', ';'),
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
