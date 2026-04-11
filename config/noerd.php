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
    'collections' => [
        'mode' => env('NOERD_COLLECTIONS_MODE', 'yaml'),
        'show_definitions_ui' => env('NOERD_COLLECTIONS_MODE', 'yaml') === 'database',
        'setup_yaml_path' => 'app-configs/setup/collections',
    ],
];
