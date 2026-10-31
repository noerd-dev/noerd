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
 * unregistered relation type, a field without a name, a route that is neither
 * registered nor backed by a component fallback); a WARNING is something that
 * is most likely unintended (an unknown field type rendering as a plain input,
 * a picklist provider no registry knows, a readonly field that is required,
 * flow-style YAML).
 */
final class ConfigLinter
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    /** Field types the detail block renders without a registration. */
    private const PLAIN_TYPES = [
        'text', 'number', 'date', 'datetime-local', 'time', 'month', 'week', 'email', 'password',
        'hidden', 'color', 'tel', 'url', 'search', 'range', 'block', 'spacer', 'file',
    ];

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

        try {
            $config = Yaml::parse($source);
        } catch (ParseException $exception) {
            $this->add($path, '', self::ERROR, 'The YAML cannot be parsed: ' . $exception->getMessage());

            return;
        }

        // An empty `[]`/`{}` is the only block-compatible spelling of an empty value.
        if (preg_match('/^[ \t-]*[A-Za-z_][A-Za-z0-9_]*:\s*(\{(?!\s*\}\s*$)|\[(?!\s*\]\s*$))/m', $source)) {
            $this->add($path, '', self::WARNING, 'Flow-style YAML ({…} or […]) — noerd configs use block style.');
        }

        if (! is_array($config)) {
            return;
        }

        $segments = explode(DIRECTORY_SEPARATOR, str_replace('/', DIRECTORY_SEPARATOR, $relative));
        $folder = $segments[1] ?? null;

        if (in_array($folder, self::FIELD_FOLDERS, true)) {
            $this->lintFields($path, $config['fields'] ?? [], 'fields');
            $this->lintActions($path, $config['actions'] ?? [], 'actions', ['modalComponent', 'action', 'url']);
        }

        if ($folder === 'lists') {
            $this->lintColumns($path, $config);
            $this->lintActions($path, $config['actions'] ?? [], 'actions', ['action']);
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
     * A `route:` must be registered unless the action names a fallback.
     *
     * @param  array<int, mixed>|mixed  $actions
     * @param  list<string>  $fallbackKeys
     */
    private function lintActions(string $path, mixed $actions, string $location, array $fallbackKeys): void
    {
        if (! is_array($actions)) {
            return;
        }

        foreach ($actions as $index => $action) {
            if (! is_array($action) || ! is_string($action['route'] ?? null)) {
                continue;
            }

            if (Route::has($action['route'])) {
                continue;
            }

            $hasFallback = collect($fallbackKeys)->contains(fn(string $key): bool => ! empty($action[$key]));
            $this->add(
                $path,
                $location . '[' . $index . ']',
                $hasFallback ? self::WARNING : self::ERROR,
                $hasFallback
                    ? "The route `{$action['route']}` is not registered — the fallback opens instead."
                    : "The route `{$action['route']}` is not registered and the action has no fallback — the button does nothing.",
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
                ...glob(base_path('app-modules/*/src'), GLOB_ONLYDIR) ?: [],
                ...glob(base_path('app-modules/*/resources/views'), GLOB_ONLYDIR) ?: [],
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
