<?php

namespace Noerd\Commands\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Noerd\Models\TenantApp;

trait GeneratesResourceFiles
{
    protected const EXCLUDED_COLUMNS = [
        'id',
        'tenant_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected const TYPE_MAP_DETAIL = [
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

    protected const TYPE_MAP_LIST = [
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

    protected const MAX_LIST_COLUMNS = 8;

    protected string $modelClass;

    protected string $modelBaseName;

    protected string $entity;

    protected string $entities;

    protected string $entityCamel;

    protected ?string $appName = null;

    protected string $appConfigName;

    protected string $tableName;

    protected array $columns = [];

    protected string $stubPath;

    protected function getStubPath(): string
    {
        return __DIR__ . '/../stubs/resource';
    }

    protected function resolveModelClass(string $name): ?string
    {
        $appModelsClass = 'App\\Models\\' . $name;
        $candidates = [];

        if (class_exists($appModelsClass)) {
            $candidates[] = $appModelsClass;
        }

        if (config('noerd.generators.search_modules', true)) {
            $modulesPath = base_path(config('noerd.generators.modules_path', 'app-modules'));

            if ($this->filesystem->isDirectory($modulesPath)) {
                foreach ($this->filesystem->directories($modulesPath) as $moduleDir) {
                    $composerPath = "{$moduleDir}/composer.json";
                    if (! $this->filesystem->exists($composerPath)) {
                        continue;
                    }

                    $composer = json_decode($this->filesystem->get($composerPath), true);
                    $autoload = $composer['autoload']['psr-4'] ?? [];

                    foreach (array_keys($autoload) as $ns) {
                        $candidate = $ns . 'Models\\' . $name;
                        if (class_exists($candidate) && ! in_array($candidate, $candidates)) {
                            $candidates[] = $candidate;
                        }
                    }
                }
            }
        }

        if (count($candidates) === 0) {
            return null;
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        return $this->choice(
            "Multiple models found for \"{$name}\". Which one should be used?",
            $candidates,
        );
    }

    protected function initializeFromModel(string $modelClass): int
    {
        $this->modelClass = $modelClass;

        if (! str_contains($this->modelClass, '\\')) {
            $resolved = $this->resolveModelClass($this->modelClass);

            if ($resolved === null) {
                $this->error("No model class found for \"{$this->modelClass}\" in App\\Models or module namespaces.");

                return 1;
            }

            $this->modelClass = $resolved;
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

        $this->appName = $this->detectModuleName();

        return 0;
    }

    protected function initializeFromEntity(string $entity): void
    {
        $this->entity = Str::kebab($entity);
        $this->entities = Str::plural($this->entity);
        $this->entityCamel = Str::camel($entity);
        $this->modelBaseName = Str::studly($entity);

        $resolved = $this->resolveModelClass($this->modelBaseName);
        if ($resolved !== null) {
            $this->modelClass = $resolved;
        }
    }

    protected function selectApp(?string $preselectedApp = null): int
    {
        if ($preselectedApp) {
            $this->appConfigName = Str::lower($preselectedApp);
            if (! $this->appName) {
                $this->appName = $this->appConfigName;
            }

            return 0;
        }

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

        return 0;
    }

    protected function readColumns(): int
    {
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

        return 0;
    }

    protected function detectModuleName(): ?string
    {
        $modulesPath = base_path(config('noerd.generators.modules_path', 'app-modules'));
        $moduleDirs = $this->filesystem->directories($modulesPath);

        foreach ($moduleDirs as $moduleDir) {
            $composerPath = "{$moduleDir}/composer.json";
            if (! $this->filesystem->exists($composerPath)) {
                continue;
            }

            $composer = json_decode($this->filesystem->get($composerPath), true);
            $autoload = $composer['autoload']['psr-4'] ?? [];

            foreach ($autoload as $ns => $path) {
                if (isset($this->modelClass) && str_starts_with($this->modelClass, $ns)) {
                    return basename($moduleDir);
                }
            }
        }

        return null;
    }

    protected function processStub(string $stubName): string
    {
        $path = $this->getStubPath() . "/{$stubName}";

        if (! $this->filesystem->exists($path)) {
            throw new Exception("Stub not found: {$stubName}");
        }

        $content = $this->filesystem->get($path);

        $translationPrefix = Str::snake($this->appConfigName ?? '') . '_' . Str::snake(str_replace('-', '_', $this->entity));

        $content = str_replace(
            [
                '{{ModelClass}}',
                '{{ModelBaseName}}',
                '{{entity}}',
                '{{entities}}',
                '{{entityCamel}}',
                '{{translationPrefix}}',
            ],
            [
                $this->modelClass ?? '',
                $this->modelBaseName,
                $this->entity,
                $this->entities,
                $this->entityCamel,
                $translationPrefix,
            ],
            $content,
        );

        $content = preg_replace('/^use\s*;\s*\n/m', '', $content);

        return $content;
    }

    protected function generateListYaml(): string
    {
        $lines = [];
        $entitiesHeadline = Str::headline($this->entities);
        $entityHeadline = Str::headline($this->entity);
        $lines[] = "title: {$entitiesHeadline}";
        $lines[] = 'actions:';
        $lines[] = "  - label: New {$entityHeadline}";
        $lines[] = '    action: listAction';
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

    protected function generateDetailYaml(): string
    {
        $lines = [];
        $entityHeadline = Str::headline($this->entity);
        $lines[] = "title: {$entityHeadline}";
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

    protected function createListBlade(): string
    {
        $bladeBase = base_path('resources/views/components');
        $path = "{$bladeBase}/{$this->entities}-list.blade.php";

        if ($this->checkFileExists($path)) {
            return '';
        }

        $this->filesystem->ensureDirectoryExists(dirname($path));
        $this->filesystem->put($path, $this->processStub('list.blade.stub'));
        $this->line("<info>Created:</info> {$path}");

        return $path;
    }

    protected function createDetailBlade(): string
    {
        $bladeBase = base_path('resources/views/components');
        $path = "{$bladeBase}/{$this->entity}-detail.blade.php";

        if ($this->checkFileExists($path)) {
            return '';
        }

        $this->filesystem->ensureDirectoryExists(dirname($path));
        $this->filesystem->put($path, $this->processStub('detail.blade.stub'));
        $this->line("<info>Created:</info> {$path}");

        return $path;
    }

    protected function createPageBlade(): string
    {
        $bladeBase = base_path('resources/views/components');
        $path = "{$bladeBase}/{$this->entity}-page.blade.php";

        if ($this->checkFileExists($path)) {
            return '';
        }

        $this->filesystem->ensureDirectoryExists(dirname($path));
        $this->filesystem->put($path, $this->processStub('page.blade.stub'));
        $this->line("<info>Created:</info> {$path}");

        return $path;
    }

    protected function addPageRoute(): void
    {
        $routeFile = base_path('routes/web.php');
        $content = $this->filesystem->get($routeFile);

        $routeName = "{$this->appConfigName}.{$this->entity}";

        if (str_contains($content, "'{$routeName}'")) {
            $this->warn("Route '{$routeName}' already exists in routes/web.php — skipping.");

            return;
        }

        $route = "Route::livewire('{$this->appConfigName}/{$this->entity}', '{$this->entity}-page')->name('{$routeName}');";

        if (! $this->confirm("Add page route to routes/web.php?\n  <comment>{$route}</comment>", true)) {
            return;
        }

        $this->filesystem->append($routeFile, "\n{$route}\n");
        $this->line("<info>Route added:</info> {$route}");
    }

    protected function createListYaml(): string
    {
        $yamlBase = base_path("app-configs/{$this->appConfigName}");
        $path = "{$yamlBase}/lists/{$this->entities}-list.yml";

        if ($this->checkFileExists($path)) {
            return '';
        }

        $this->filesystem->ensureDirectoryExists(dirname($path));
        $this->filesystem->put($path, $this->generateListYaml());
        $this->line("<info>Created:</info> {$path}");

        return $path;
    }

    protected function createDetailYaml(): string
    {
        $yamlBase = base_path("app-configs/{$this->appConfigName}");
        $path = "{$yamlBase}/details/{$this->entity}-detail.yml";

        if ($this->checkFileExists($path)) {
            return '';
        }

        $this->filesystem->ensureDirectoryExists(dirname($path));
        $this->filesystem->put($path, $this->generateDetailYaml());
        $this->line("<info>Created:</info> {$path}");

        return $path;
    }

    protected function addListRoute(): void
    {
        $routeFile = base_path('routes/web.php');
        $content = $this->filesystem->get($routeFile);

        $listRouteName = "{$this->appConfigName}.{$this->entities}";

        if (str_contains($content, "'{$listRouteName}'")) {
            $this->warn("Route '{$listRouteName}' already exists in routes/web.php — skipping.");

            return;
        }

        $listRoute = "Route::livewire('{$this->appConfigName}/{$this->entities}', '{$this->entities}-list')->name('{$listRouteName}');";

        if (! $this->confirm("Add list route to routes/web.php?\n  <comment>{$listRoute}</comment>", true)) {
            return;
        }

        $this->filesystem->append($routeFile, "\n{$listRoute}\n");
        $this->line("<info>Route added:</info> {$listRoute}");
    }

    protected function addDetailRoute(): void
    {
        $routeFile = base_path('routes/web.php');
        $content = $this->filesystem->get($routeFile);

        $detailRouteName = "{$this->appConfigName}.{$this->entity}.detail";

        if (str_contains($content, "'{$detailRouteName}'")) {
            $this->warn("Route '{$detailRouteName}' already exists in routes/web.php — skipping.");

            return;
        }

        $detailRoute = "Route::livewire('{$this->appConfigName}/{$this->entity}/{modelId}', '{$this->entity}-detail')->name('{$detailRouteName}');";

        if (! $this->confirm("Add detail route to routes/web.php?\n  <comment>{$detailRoute}</comment>\n  <info>Note: The component can also be used as a modal without a dedicated route.</info>", true)) {
            return;
        }

        $this->filesystem->append($routeFile, "\n{$detailRoute}\n");
        $this->line("<info>Route added:</info> {$detailRoute}");
    }

    protected function addNavigation(bool $useSingularRoute = false): void
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

            $routeEntity = $useSingularRoute ? $this->entity : $this->entities;

            if (str_contains($content, "route: {$this->appConfigName}.{$routeEntity}")) {
                $this->warn("Navigation entry for '{$this->appConfigName}.{$routeEntity}' already exists in {$navPath} — skipping.");

                continue;
            }

            $entitiesHeadline = Str::headline($this->entities);
            $navEntry = "        - title: {$entitiesHeadline}\n"
                . "          route: {$this->appConfigName}.{$routeEntity}\n"
                . "          heroicon: rectangle-stack";

            if (! $useSingularRoute) {
                $navEntry .= "\n          newComponent: {$this->entity}-detail";
            }

            if (! $this->confirm("Add navigation entry to {$this->appConfigName} navigation.yml?\n<comment>{$navEntry}</comment>", true)) {
                continue;
            }

            $settingsPos = mb_strpos($content, '_nav_settings');
            if ($settingsPos !== false) {
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

            $content = mb_rtrim($content) . "\n" . $navEntry . "\n";
            $this->filesystem->put($navPath, $content);
            $this->line("<info>Navigation added to:</info> {$navPath}");
        }
    }

    protected function getDetailType(string $dbType): string
    {
        $normalized = mb_strtolower(preg_replace('/\(.*\)/', '', $dbType));

        return self::TYPE_MAP_DETAIL[$normalized] ?? 'text';
    }

    protected function getListType(string $dbType): ?string
    {
        $normalized = mb_strtolower(preg_replace('/\(.*\)/', '', $dbType));

        if ($normalized === 'json') {
            return null;
        }

        return self::TYPE_MAP_LIST[$normalized] ?? 'text';
    }

    protected function guessColumnWidth(string $yamlType): int
    {
        return match ($yamlType) {
            'boolean' => 5,
            'number' => 8,
            'date', 'datetime' => 10,
            default => 15,
        };
    }

    protected function checkFileExists(string $path): bool
    {
        if ($this->filesystem->exists($path)) {
            $this->info("File already exists: {$path}");

            return true;
        }

        return false;
    }
}
