<?php

namespace Noerd\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Noerd\Models\TenantApp;

class MakeResourceCommand extends Command
{
    private const EXCLUDED_COLUMNS = [
        'id',
        'tenant_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    private const TYPE_MAP_DETAIL = [
        'varchar' => 'text',
        'string' => 'text',
        'char' => 'text',
        'text' => 'textarea',
        'longtext' => 'textarea',
        'mediumtext' => 'textarea',
        'tinyint' => 'checkbox',
        'boolean' => 'checkbox',
        'integer' => 'number',
        'bigint' => 'number',
        'smallint' => 'number',
        'decimal' => 'number',
        'float' => 'number',
        'double' => 'number',
        'date' => 'date',
        'datetime' => 'datetime',
        'timestamp' => 'datetime',
        'json' => 'textarea',
    ];

    private const TYPE_MAP_LIST = [
        'varchar' => 'text',
        'string' => 'text',
        'char' => 'text',
        'text' => 'text',
        'longtext' => 'text',
        'mediumtext' => 'text',
        'tinyint' => 'boolean',
        'boolean' => 'boolean',
        'integer' => 'number',
        'bigint' => 'number',
        'smallint' => 'number',
        'decimal' => 'number',
        'float' => 'number',
        'double' => 'number',
        'date' => 'date',
        'datetime' => 'datetime',
        'timestamp' => 'datetime',
    ];

    private const MAX_LIST_COLUMNS = 8;
    protected $signature = 'noerd:make-resource {model : Full model class path}';

    protected $description = 'Generate list/detail Blade and YAML files from an existing Eloquent model';

    protected Filesystem $filesystem;

    protected string $stubPath;

    protected string $modelClass;

    protected string $modelBaseName;

    protected string $entity;

    protected string $entities;

    protected string $entityCamel;

    protected ?string $appName = null;

    protected string $appConfigName;

    protected string $tableName;

    protected array $columns = [];

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->filesystem = $filesystem;
        $this->stubPath = __DIR__ . '/stubs/resource';
    }

    public function handle(): int
    {
        $this->modelClass = $this->argument('model');

        if (! str_contains($this->modelClass, '\\')) {
            $this->modelClass = 'App\\Models\\' . $this->modelClass;
        }

        if (! class_exists($this->modelClass)) {
            $this->error("Class {$this->modelClass} does not exist.");

            return 1;
        }

        $instance = new $this->modelClass();

        if (! $instance instanceof Model) {
            $this->error("{$this->modelClass} is not an Eloquent Model.");

            return 1;
        }

        $this->modelBaseName = class_basename($this->modelClass);
        $this->entity = Str::kebab($this->modelBaseName);
        $this->entities = Str::plural($this->entity);
        $this->entityCamel = Str::camel($this->modelBaseName);
        $this->tableName = $instance->getTable();

        // Derive app name from module directory
        $this->appName = $this->detectModuleName();

        // App selection via tenant_apps
        $apps = TenantApp::where('is_active', true)->pluck('title', 'name')->toArray();

        if (empty($apps)) {
            $this->error('No active apps found in tenant_apps.');

            return 1;
        }

        $appChoices = array_values($apps);
        $selectedTitle = $this->choice('Which app should this resource belong to?', $appChoices);
        $selectedName = array_search($selectedTitle, $apps);
        $this->appConfigName = Str::lower($selectedName);

        if (! $this->appName) {
            $this->appName = $this->appConfigName;
        }

        // Read columns from database
        try {
            $allColumns = Schema::getColumns($this->tableName);
        } catch (Exception $e) {
            $this->error("Could not read columns for table '{$this->tableName}': " . $e->getMessage());

            return 1;
        }

        $this->columns = array_filter(
            $allColumns,
            fn(array $col) => ! in_array($col['name'], self::EXCLUDED_COLUMNS),
        );

        if (empty($this->columns)) {
            $this->error('No columns found after filtering.');

            return 1;
        }

        // Define target paths (always in project, not in module)
        $bladeBase = base_path('resources/views/components');
        $yamlBase = base_path("app-configs/{$this->appConfigName}");

        $files = [
            'list-blade' => "{$bladeBase}/{$this->entities}-list.blade.php",
            'detail-blade' => "{$bladeBase}/{$this->entity}-detail.blade.php",
            'list-yaml' => "{$yamlBase}/lists/{$this->entities}-list.yml",
            'detail-yaml' => "{$yamlBase}/details/{$this->entity}-detail.yml",
        ];

        // Check for existing files
        foreach ($files as $label => $path) {
            if ($this->filesystem->exists($path)) {
                $this->error("File already exists: {$path}");

                return 1;
            }
        }

        try {
            // Ensure directories exist
            foreach ($files as $path) {
                $this->filesystem->ensureDirectoryExists(dirname($path));
            }

            // Generate blade files from stubs
            $this->filesystem->put($files['list-blade'], $this->processStub('list.blade.stub'));
            $this->line("<info>Created:</info> {$files['list-blade']}");

            $this->filesystem->put($files['detail-blade'], $this->processStub('detail.blade.stub'));
            $this->line("<info>Created:</info> {$files['detail-blade']}");

            // Generate YAML files dynamically
            $this->filesystem->put($files['list-yaml'], $this->generateListYaml());
            $this->line("<info>Created:</info> {$files['list-yaml']}");

            $this->filesystem->put($files['detail-yaml'], $this->generateDetailYaml());
            $this->line("<info>Created:</info> {$files['detail-yaml']}");

            // Add routes to module route file
            $this->addRoutes();

            // Add navigation entry
            $this->addNavigation();

            $this->line('');
            $this->info('Resource files created successfully!');

            return 0;
        } catch (Exception $e) {
            $this->error('Error creating resource: ' . $e->getMessage());

            return 1;
        }
    }

    private function addRoutes(): void
    {
        $routeFile = base_path('routes/web.php');
        $content = $this->filesystem->get($routeFile);

        $listRouteName = "{$this->appName}.{$this->entities}";

        if (str_contains($content, "'{$listRouteName}'")) {
            $this->warn("Route '{$listRouteName}' already exists in routes/web.php — skipping.");

            return;
        }

        $listRoute = "Route::livewire('{$this->appName}/{$this->entities}', '{$this->entities}-list')->name('{$listRouteName}');";
        $detailRoute = "Route::livewire('{$this->appName}/{$this->entity}/{modelId}', '{$this->entity}-detail')->name('{$this->appName}.{$this->entity}.detail');";

        $this->filesystem->append($routeFile, "\n{$listRoute}\n{$detailRoute}\n");

        $this->line("<info>Route added:</info> {$listRoute}");
        $this->line("<info>Route added:</info> {$detailRoute}");
    }

    private function addNavigation(): void
    {
        $navPaths = [
            base_path("app-configs/{$this->appConfigName}/navigation.yml"),
        ];

        foreach ($navPaths as $navPath) {
            if (! $this->filesystem->exists($navPath)) {
                $this->warn("Navigation file not found: {$navPath} — skipping.");

                continue;
            }

            $content = $this->filesystem->get($navPath);

            if (str_contains($content, "route: {$this->appName}.{$this->entities}")) {
                $this->warn("Navigation entry for '{$this->appName}.{$this->entities}' already exists in {$navPath} — skipping.");

                continue;
            }

            $navEntry = "        - title: {$this->appName}_nav_{$this->entities}\n"
                . "          route: {$this->appName}.{$this->entities}\n"
                . "          heroicon: rectangle-stack\n"
                . "          newComponent: {$this->entity}-detail";

            // Find the settings block to insert before it
            $settingsPos = mb_strpos($content, '_nav_settings');
            if ($settingsPos !== false) {
                // Find the beginning of the settings block_menus entry (the "    - title:" line before it)
                $beforeSettings = mb_substr($content, 0, $settingsPos);
                $lastTitlePos = mb_strrpos($beforeSettings, '    - title:');
                if ($lastTitlePos !== false) {
                    $newContent = mb_substr($content, 0, $lastTitlePos)
                        . $navEntry . "\n"
                        . mb_substr($content, $lastTitlePos);
                    $this->filesystem->put($navPath, $newContent);
                    $this->line("<info>Navigation added to:</info> {$navPath}");

                    continue;
                }
            }

            // No settings block found — append to the last navigations block
            $content = mb_rtrim($content) . "\n" . $navEntry . "\n";
            $this->filesystem->put($navPath, $content);
            $this->line("<info>Navigation added to:</info> {$navPath}");
        }
    }

    private function detectModuleName(): ?string
    {
        $moduleDirs = $this->filesystem->directories(base_path('app-modules'));

        foreach ($moduleDirs as $moduleDir) {
            $composerPath = "{$moduleDir}/composer.json";
            if (! $this->filesystem->exists($composerPath)) {
                continue;
            }

            $composer = json_decode($this->filesystem->get($composerPath), true);
            $autoload = $composer['autoload']['psr-4'] ?? [];

            foreach ($autoload as $ns => $path) {
                if (str_starts_with($this->modelClass, $ns)) {
                    return basename($moduleDir);
                }
            }
        }

        return null;
    }

    private function processStub(string $stubName): string
    {
        $path = "{$this->stubPath}/{$stubName}";

        if (! $this->filesystem->exists($path)) {
            throw new Exception("Stub not found: {$stubName}");
        }

        $content = $this->filesystem->get($path);

        return str_replace(
            [
                '{{ModelClass}}',
                '{{ModelBaseName}}',
                '{{entity}}',
                '{{entities}}',
                '{{entityCamel}}',
            ],
            [
                $this->modelClass,
                $this->modelBaseName,
                $this->entity,
                $this->entities,
                $this->entityCamel,
            ],
            $content,
        );
    }

    private function generateListYaml(): string
    {
        $lines = [];
        $lines[] = "title: {$this->appName}_{$this->entities}";
        $lines[] = "newLabel: {$this->appName}_new_{$this->entity}";
        $lines[] = "component: {$this->entity}-detail";
        $lines[] = 'columns:';

        $listColumns = array_filter(
            $this->columns,
            fn(array $col) => $this->getListType($col['type_name']) !== null,
        );

        $listColumns = array_slice(array_values($listColumns), 0, self::MAX_LIST_COLUMNS);

        foreach ($listColumns as $col) {
            $field = $col['name'];
            $type = $this->getListType($col['type_name']);
            $label = Str::headline($field);
            $width = $this->guessColumnWidth($type);

            $lines[] = "  - field: {$field}";
            $lines[] = "    label: {$label}";
            $lines[] = "    width: {$width}";

            if ($type !== 'text') {
                $lines[] = "    type: {$type}";
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function generateDetailYaml(): string
    {
        $lines = [];
        $lines[] = "title: {$this->appName}_{$this->entity}";
        $lines[] = 'fields:';

        foreach ($this->columns as $col) {
            $field = $col['name'];
            $type = $this->getDetailType($col['type_name']);
            $label = Str::headline($field);

            $lines[] = "  - name: detailData.{$field}";
            $lines[] = "    label: {$label}";
            $lines[] = "    type: {$type}";
            $lines[] = '    colspan: 6';
        }

        return implode("\n", $lines) . "\n";
    }

    private function getDetailType(string $dbType): string
    {
        $normalized = mb_strtolower(preg_replace('/\(.*\)/', '', $dbType));

        return self::TYPE_MAP_DETAIL[$normalized] ?? 'text';
    }

    private function getListType(string $dbType): ?string
    {
        $normalized = mb_strtolower(preg_replace('/\(.*\)/', '', $dbType));

        // Skip json columns in list
        if ($normalized === 'json') {
            return null;
        }

        return self::TYPE_MAP_LIST[$normalized] ?? 'text';
    }

    private function guessColumnWidth(string $yamlType): int
    {
        return match ($yamlType) {
            'boolean' => 5,
            'number' => 8,
            'date', 'datetime' => 10,
            default => 15,
        };
    }
}
