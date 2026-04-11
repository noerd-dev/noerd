<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific features of the Noerd package.
    |
    */
    'features' => [
        'multi_tenant' => env('NOERD_MULTI_TENANT', true),
        'roles' => env('NOERD_ROLE_FEATURE_ENABLED', true),
        'new_tenant' => env('NOERD_NEW_TENANT_FEATURE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Collection Definitions (shared by CMS and Setup)
    |--------------------------------------------------------------------------
    |
    | Controls where collection schemas are stored for BOTH the CMS module
    | and the Setup area.
    |
    | Supported modes:
    |   - "yaml":     Schemas live in the module's yaml_path directory.
    |                 The management UI is hidden. Changes must be deployed
    |                 via committed YAML files.
    |   - "database": Schemas live in the per-tenant collection_definitions
    |                 (CMS) and setup_collection_definitions (Setup) tables.
    |                 The management UI is enabled.
    |
    */
    'collections' => [
        'mode' => env('NOERD_COLLECTIONS_MODE', 'yaml'),
        'show_definitions_ui' => env('NOERD_COLLECTIONS_MODE', 'yaml') === 'database',
        'setup_yaml_path' => 'app-configs/setup/collections',
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | Configure your application's branding assets.
    |
    */
    'branding' => [
        'logo' => env('NOERD_LOGO', ''),
        'auth_background_image' => env('NOERD_AUTH_BACKGROUND_IMAGE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sidebar
    |--------------------------------------------------------------------------
    |
    | Configure the sidebar dimensions.
    |
    */
    'sidebar' => [
        'apps_width' => env('NOERD_SIDEBAR_APPS_WIDTH', '80px'),
        'navigation_width' => env('NOERD_SIDEBAR_NAVIGATION_WIDTH', '280px'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Keyboard Shortcuts
    |--------------------------------------------------------------------------
    |
    | Configure keyboard shortcuts for the application.
    | Format: 'modifier+key' (e.g., 'ctrl+f', 'shift+k', '/').
    | Supported modifiers: ctrl, shift, alt, meta.
    |
    */
    'keyboard_shortcuts' => [
        'search_focus' => 's',
        'new_entry' => 'n',
        'save' => 'ctrl+enter',
        'delete' => 'ctrl+backspace',
    ],

    /*
    |--------------------------------------------------------------------------
    | Generators
    |--------------------------------------------------------------------------
    |
    | Configuration for the noerd:make-* Artisan commands.
    |
    */
    'generators' => [
        /*
        | When enabled, the make commands (noerd:make-list, noerd:make-detail,
        | noerd:make-resource) will also search for models in app-modules.
        | This allows using short model names like "Customer" instead of
        | the full namespace "Noerd\Customer\Models\Customer".
        */
        'search_modules' => true,
        'modules_path' => 'app-modules',
    ],
];
