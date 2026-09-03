<?php

declare(strict_types=1);

namespace Noerd\Services;

use Noerd\Support\ThemeDefinition;

/**
 * Discovers and resolves form themes.
 *
 * A theme is a folder holding element blade templates plus a theme.yml with
 * the ThemeDefinition values. Theme roots are registered with a priority
 * (project > modules > noerd built-ins); for a theme name that exists in
 * several roots the highest-priority theme.yml wins the metadata, while the
 * element templates are resolved through the `themes::` view namespace whose
 * hint paths walk the same root order — so a project may override single
 * element files of a built-in theme without shipping its own theme.yml.
 */
final class ThemeRegistry
{
    /** @var array<string, ThemeDefinition> */
    private array $themes = [];

    /** @var array<string, ThemeDefinition> */
    private array $manual = [];

    /** @var array<int, array{path: string, priority: int, order: int}> */
    private array $paths = [];

    private bool $discovered = false;

    /**
     * Register a directory containing theme folders. Modules call this in
     * their service provider's boot(). Higher priority wins name collisions;
     * the project root uses 100, the noerd built-ins 0.
     */
    public function registerPath(string $path, int $priority = 50): void
    {
        $this->paths[] = [
            'path' => mb_rtrim($path, '/'),
            'priority' => $priority,
            'order' => count($this->paths),
        ];

        $this->discovered = false;

        // Element templates (and the forms/* shims) resolve through the
        // themes:: namespace even outside a discovery-triggering render.
        $this->syncViewNamespace();
    }

    /**
     * Programmatic escape hatch for dynamically built definitions. A manual
     * registration wins over a discovered theme.yml of the same name.
     */
    public function register(ThemeDefinition $theme): void
    {
        $this->manual[$theme->name] = $theme;
        $this->discovered = false;
    }

    /**
     * Resolve a theme definition by name. Unknown or missing names fall back
     * to the 'default' definition so a YAML typo never breaks a detail page.
     */
    public function get(?string $name): ThemeDefinition
    {
        $this->discover();

        return $this->themes[$name] ?? $this->themes['default'];
    }

    public function has(string $name): bool
    {
        $this->discover();

        return isset($this->themes[$name]);
    }

    /**
     * @return array<string, ThemeDefinition>
     */
    public function all(): array
    {
        $this->discover();

        return $this->themes;
    }

    public function clearCache(): void
    {
        $this->discovered = false;
    }

    /**
     * Ordered theme roots, highest priority first. Used for discovery and as
     * the hint paths of the `themes::` view namespace.
     *
     * @return array<int, string>
     */
    public function orderedRoots(): array
    {
        $paths = $this->paths;
        usort($paths, fn(array $a, array $b): int => [$b['priority'], $a['order']] <=> [$a['priority'], $b['order']]);

        return array_values(array_unique(array_column($paths, 'path')));
    }

    private function discover(): void
    {
        if ($this->discovered) {
            return;
        }
        $this->discovered = true;

        $themes = [];
        $roots = $this->orderedRoots();

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            foreach (scandir($root) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..' || isset($themes[$entry])) {
                    continue;
                }
                $yamlFile = $root . '/' . $entry . '/theme.yml';
                if (is_file($yamlFile)) {
                    $themes[$entry] = ThemeDefinition::fromYamlFile($entry, $yamlFile);
                }
            }
        }

        $themes = array_merge($themes, $this->manual);

        // The fallback target of get() must always exist.
        $themes['default'] ??= new ThemeDefinition('default');

        $this->themes = $themes;

        $this->syncViewNamespace();
    }

    private function syncViewNamespace(): void
    {
        $existingRoots = array_values(array_filter($this->orderedRoots(), 'is_dir'));
        if ($existingRoots !== []) {
            app('view')->replaceNamespace('themes', $existingRoots);
        }
    }
}
