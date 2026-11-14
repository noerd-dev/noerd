<?php

declare(strict_types=1);

namespace Noerd\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Noerd\Services\FieldTypeRegistry;
use Noerd\Services\PicklistRegistry;
use Noerd\Services\RelationFieldRegistry;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Static checks over app-configs YAML (`noerd:lint`). An ERROR is something
 * that breaks or silently misbehaves at render time (an unparsable file, an
 * unregistered relation type, a field without a name, a modalRoute that is
 * neither registered nor backed by a modalComponent fallback, a modal target in
 * an earlier spelling); a WARNING is something that
 * is most likely unintended (an unknown field type rendering as a plain input,
 * a picklist provider no registry knows, a readonly field that is required,
 * flow-style YAML).
 *
 * @internal
 */
final class ConfigLinter
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    /**
     * The list column types the core renders (ListCellFormatter, table-cell).
     * A `cell:` renderer keeps one of these as its `type`.
     */
    public const LIST_COLUMN_TYPES = [
        'text', 'date', 'datetime', 'time', 'number', 'currency', 'bool', 'boolCheck', 'checkbox',
        'badge', 'badgeWithText', 'relationBadge', 'customAttribute', 'coloredText', 'preview', 'relationLink',
    ];

    /** The `type` of a list YAML `filters:` entry (ListFilter::fromArray()). */
    public const LIST_FILTER_TYPES = ListFilter::KINDS;

    /** Field types the detail block renders without a registration. */
    private const PLAIN_TYPES = [
        'text', 'number', 'date', 'datetime-local', 'time', 'month', 'week', 'email', 'password',
        'hidden', 'color', 'tel', 'url', 'search', 'range', 'block', 'spacer', 'file',
    ];

    private const BOM = "\xEF\xBB\xBF";

    /** Top-level folders whose files carry a field layout. */
    private const FIELD_FOLDERS = ['details', 'pages', 'settings'];

    /** @var list<array{file: string, location: string, severity: string, message: string}> */
    private array $issues = [];

    /** @var array<string, true>|null method names defined in the host's and the modules' code */
    private ?array $methodIndex = null;

    public function __construct(
        private readonly FieldTypeRegistry $fieldTypes,
        private readonly RelationFieldRegistry $relationTypes,
        private readonly PicklistRegistry $picklists,
    ) {}

    /**
     * Lint every YAML file below the given app-configs directories.
     *
     * @param  list<string>  $directories
     * @return list<array{file: string, location: string, severity: string, message: string}>
     */
    public function lint(array $directories): array
    {
        $this->issues = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                if (in_array($file->getExtension(), ['yml', 'yaml'], true)) {
                    $this->lintFile($file->getPathname(), $file->getRelativePathname());
                }
            }
        }

        return $this->issues;
    }

    private function lintFile(string $path, string $relative): void
    {
        $source = (string) file_get_contents($path);

        // The runtime reads the BOM as part of the first key — the file has to lose it.
        if (str_starts_with($source, self::BOM)) {
            $this->add($path, '', self::ERROR, 'The file starts with a UTF-8 BOM — its first key is not recognised at render time. Save it without BOM.');
            $source = mb_substr($source, 1);
        }

        try {
            $config = Yaml::parse($source);
        } catch (ParseException $exception) {
            $this->add($path, '', self::ERROR, 'The YAML cannot be parsed: ' . $exception->getMessage());

            return;
        }

        // An empty `[]`/`{}` is the only block-compatible spelling of an empty value.
        if (preg_match('/^[ \t-]*(?:[A-Za-z_][A-Za-z0-9_]*|"[^"]*"|\'[^\']*\')\s*:\s*(\{(?!\s*\}\s*$)|\[(?!\s*\]\s*$))|^[ \t]*-[ \t]+\{/m', $source)) {
            $this->add($path, '', self::WARNING, 'Flow-style YAML ({…} or […]) — noerd configs use block style.');
        }

        $listConfig = YamlVocabularyMigrator::isListConfig($relative);
        $outdated = app(YamlVocabularyMigrator::class)->outdatedNames($source, $listConfig);

        if (is_array($config)) {
            // The parsed structure catches every spelling the line patterns cannot see.
            if ($listConfig) {
                $outdated = [...$outdated, ...$this->lintListTypes($path, $config)];
            }

            if (str_starts_with(basename($relative), 'navigation')) {
                $outdated = [...$outdated, ...$this->lintNavigationKeys($path, $config)];
            }
        }

        foreach (array_unique($outdated) as $name) {
            $this->add($path, '', self::ERROR, 'Outdated name `' . $name . '` — run `php artisan noerd:update` to rewrite the file.');
        }

        // A modal opens through modalRoute:/modalComponent: only — `route:` navigates.
        foreach (app(YamlVocabularyMigrator::class)->outdatedModalTargets($source, YamlVocabularyMigrator::fileKind($relative)) as $target) {
            $this->add($path, '', self::ERROR, 'Outdated modal target ' . $target . ' — run `php artisan noerd:update` to rewrite the file.');
        }

        if (! is_array($config)) {
            return;
        }

        $segments = explode(DIRECTORY_SEPARATOR, str_replace('/', DIRECTORY_SEPARATOR, $relative));
        $folder = $segments[1] ?? null;

        if (in_array($folder, self::FIELD_FOLDERS, true)) {
            $this->lintFields($path, $config['fields'] ?? [], 'fields');
            $this->lintModalRoutes($path, $config['actions'] ?? [], 'actions');
        }

        if ($folder === 'lists') {
            $this->lintColumns($path, $config);
            $this->lintModalRoutes($path, $config['actions'] ?? [], 'actions');
        }
    }

    /**
     * @param  array<int, mixed>|mixed  $fields
     */
    private function lintFields(string $path, mixed $fields, string $location): void
    {
        if (! is_array($fields)) {
            return;
        }

        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                continue;
            }

            $at = $location . '[' . $index . ']';
            $type = is_string($field['type'] ?? null) ? $field['type'] : 'text';

            if ($type === 'block') {
                $this->lintFields($path, $field['fields'] ?? [], $at . '.fields');

                continue;
            }

            if ($type !== 'spacer' && ! is_string($field['name'] ?? null)) {
                $this->add($path, $at, self::ERROR, 'The field has no `name`.');

                continue;
            }

            $at = $location . '[' . ($field['name'] ?? $index) . ']';

            // A polymorphic relation type lives in the field type registry only.
            if (str_ends_with($type, 'Relation') && ! $this->relationTypes->has($type) && ! $this->fieldTypes->has($type)) {
                $this->add($path, $at, self::ERROR, "The relation type `{$type}` is not registered — rendering throws.");
            } elseif (! str_ends_with($type, 'Relation') && ! $this->fieldTypes->has($type) && ! in_array($type, self::PLAIN_TYPES, true)) {
                $this->add($path, $at, self::WARNING, "The field type `{$type}` is not registered — it renders as a plain input.");
            }

            foreach (['optionsMethod', 'picklistField'] as $key) {
                $provider = $field[$key] ?? null;
                if (is_string($provider) && $provider !== '' && ! $this->picklists->has($provider) && ! $this->definesMethod($provider)) {
                    $this->add($path, $at, self::WARNING, "`{$key}: {$provider}` is neither a registered picklist provider nor a method of any component — the field renders without options.");
                }
            }

            if (! empty($field['readonly']) && ! empty($field['required'])) {
                $this->add($path, $at, self::WARNING, 'The field is readonly AND required — the user cannot fill it in.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function lintColumns(string $path, array $config): void
    {
        foreach ((array) ($config['columns'] ?? []) as $index => $column) {
            if (! is_array($column) || ! is_string($column['field'] ?? null) || $column['field'] === '') {
                $this->add($path, 'columns[' . $index . ']', self::ERROR, 'The column has no `field`.');
            }
        }
    }

    /**
     * The `type` of every list column and filter, read from the PARSED file: an
     * old name is returned for the outdated-name report, a removed or unknown
     * type is an error of its own.
     *
     * @param  array<mixed>  $config
     * @return list<string> the outdated names (`type: colored_text`)
     */
    private function lintListTypes(string $path, array $config): array
    {
        $renamed = [...YamlVocabularyMigrator::COLUMN_TYPES, ...YamlVocabularyMigrator::LIST_COLUMN_TYPES];
        $outdated = [];

        foreach ((array) ($config['columns'] ?? []) as $index => $column) {
            if (! is_array($column) || ! array_key_exists('type', $column)) {
                continue;
            }

            $type = $column['type'];
            $at = 'columns[' . (is_string($column['field'] ?? null) ? $column['field'] : $index) . ']';

            if (! is_string($type)) {
                $this->add($path, $at, self::ERROR, 'The column `type` is not a name.');
            } elseif (isset($renamed[$type])) {
                $outdated[] = 'type: ' . $type;
            } elseif (isset(YamlVocabularyMigrator::REMOVED_LIST_COLUMN_TYPES[$type])) {
                $this->add($path, $at, self::ERROR, "The column type `{$type}` was removed — use " . YamlVocabularyMigrator::REMOVED_LIST_COLUMN_TYPES[$type] . '.');
            } elseif (! in_array($type, self::LIST_COLUMN_TYPES, true)) {
                $this->add($path, $at, self::ERROR, "The column type `{$type}` is unknown — the cell renders as plain text. Known types: " . implode(', ', self::LIST_COLUMN_TYPES) . '.');
            }
        }

        foreach ((array) ($config['filters'] ?? []) as $index => $filter) {
            if (! is_array($filter) || ! array_key_exists('type', $filter)) {
                continue;
            }

            if (! is_string($filter['type']) || ! in_array($filter['type'], self::LIST_FILTER_TYPES, true)) {
                $type = is_string($filter['type']) ? $filter['type'] : get_debug_type($filter['type']);
                $this->add($path, 'filters[' . $index . ']', self::ERROR, "The filter type `{$type}` is unknown. Known types: picklist, years, model, dateFrom, dateUntil.");
            }
        }

        return $outdated;
    }

    /**
     * Renamed and removed keys anywhere in a navigation file.
     *
     * @param  array<mixed>  $navigation
     * @return list<string> the outdated names (`block_menus:`)
     */
    private function lintNavigationKeys(string $path, array $navigation, string $location = ''): array
    {
        $outdated = [];

        foreach ($navigation as $key => $value) {
            $at = $location === '' ? (string) $key : $location . '.' . $key;

            if (is_string($key) && isset(YamlVocabularyMigrator::KEYS[$key])) {
                $outdated[] = $key . ':';
            } elseif (is_string($key) && isset(YamlVocabularyMigrator::REMOVED_NAVIGATION_KEYS[$key])) {
                $this->add($path, $at, self::ERROR, "The navigation key `{$key}` was removed — " . YamlVocabularyMigrator::REMOVED_NAVIGATION_KEYS[$key] . '.');
            }

            if (is_array($value)) {
                $outdated = [...$outdated, ...$this->lintNavigationKeys($path, $value, $at)];
            }
        }

        return $outdated;
    }

    /**
     * A `modalRoute:` must be registered unless the action names a
     * `modalComponent:` fallback — without one the action is not rendered.
     *
     * @param  array<int, mixed>|mixed  $actions
     */
    private function lintModalRoutes(string $path, mixed $actions, string $location): void
    {
        if (! is_array($actions)) {
            return;
        }

        foreach ($actions as $index => $action) {
            if (! is_array($action) || ! is_string($action['modalRoute'] ?? null)) {
                continue;
            }

            if (Route::has($action['modalRoute'])) {
                continue;
            }

            $hasFallback = ! empty($action['modalComponent']);
            $this->add(
                $path,
                $location . '[' . $index . ']',
                $hasFallback ? self::WARNING : self::ERROR,
                $hasFallback
                    ? "The route `{$action['modalRoute']}` is not registered — the modalComponent fallback opens instead."
                    : "The route `{$action['modalRoute']}` is not registered and the action has no modalComponent fallback — the button is not shown.",
            );
        }
    }

    /**
     * Whether any component of the host or a module defines a method of that
     * name — the options provider of a `picklistField:` may be one.
     */
    private function definesMethod(string $name): bool
    {
        if ($this->methodIndex === null) {
            $this->methodIndex = [];
            $roots = array_filter([
                base_path('app'),
                base_path('resources/views'),
                ...glob(ModulesPath::absolute('*/src'), GLOB_ONLYDIR) ?: [],
                ...glob(ModulesPath::absolute('*/resources/views'), GLOB_ONLYDIR) ?: [],
                ...glob(base_path('vendor/noerd/*/src'), GLOB_ONLYDIR) ?: [],
                ...glob(base_path('vendor/noerd/*/resources/views'), GLOB_ONLYDIR) ?: [],
                dirname(__DIR__, 2) . '/resources/views',
                dirname(__DIR__, 2) . '/src',
            ], 'is_dir');

            foreach (array_unique($roots) as $root) {
                foreach (File::allFiles($root) as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }

                    preg_match_all('/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', (string) file_get_contents($file->getPathname()), $matches);
                    foreach ($matches[1] as $method) {
                        $this->methodIndex[$method] = true;
                    }
                }
            }
        }

        return isset($this->methodIndex[$name]);
    }

    private function add(string $path, string $location, string $severity, string $message): void
    {
        $this->issues[] = ['file' => $path, 'location' => $location, 'severity' => $severity, 'message' => $message];
    }
}
