<?php

declare(strict_types=1);

namespace Noerd\Commands\Concerns;

use Symfony\Component\Yaml\Yaml;

/**
 * Idempotent writers for the host-wide YAML files a module contributes entries
 * to: the quick-menu, the dashboard widgets and the setup navigation. Usable by
 * tenant-app and support modules alike — none of them needs a tenant app.
 */
trait WritesHostAppConfigs
{
    /**
     * Ensure a button exists in the global quick-menu config (app-configs/quick-menu.yml).
     * Rewrites any button still pointing at one of the $legacyComponents to the new
     * component name. An existing entry with the same component is REPLACED wholesale
     * (stale keys like the removed per-module `policy:` gates drop off on re-install),
     * except its `apps:` list, which is UNIONED with the new one — several modules may
     * contribute the same button (e.g. the booking family's customer select), and the
     * union keeps the result independent of the install order. Otherwise the button is
     * prepended.
     *
     * @param  array{component: string, app?: string, apps?: string[], policy?: string}  $button
     * @param  string[]  $legacyComponents
     */
    protected function ensureQuickMenuButton(array $button, array $legacyComponents = []): void
    {
        $configPath = base_path('app-configs/quick-menu.yml');

        $config = file_exists($configPath)
            ? (Yaml::parse(file_get_contents($configPath) ?: '') ?? [])
            : [];
        $buttons = $config['buttons'] ?? [];

        foreach ($buttons as $i => $existing) {
            if (in_array($existing['component'] ?? null, $legacyComponents, true)) {
                $buttons[$i]['component'] = $button['component'];
            }
        }

        $replaced = false;
        foreach ($buttons as $i => $existing) {
            if (($existing['component'] ?? null) !== ($button['component'] ?? null)) {
                continue;
            }

            $merged = $button;
            $apps = array_values(array_unique(array_merge(
                array_map('strval', (array) ($existing['apps'] ?? [])),
                array_map('strval', (array) ($button['apps'] ?? [])),
            )));
            if ($apps !== []) {
                $merged['apps'] = $apps;
            }

            $buttons[$i] = $merged;
            $replaced = true;
            break;
        }

        if (! $replaced) {
            $buttons = [$button, ...$buttons];
        }

        if ($buttons === ($config['buttons'] ?? [])) {
            $this->line('<comment>Quick-menu already contains the button.</comment>');

            return;
        }

        $dir = dirname($configPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $config['buttons'] = $buttons;
        file_put_contents($configPath, Yaml::dump($config, 10, 2));
        $this->line('<info>Quick-menu config updated:</info> app-configs/quick-menu.yml');
    }

    /**
     * Ensure a widget exists in the global dashboard-widgets config
     * (app-configs/dashboard-widgets.yml). Rewrites any widget still pointing at one of
     * the $legacyComponents to the new component name, then appends the widget if it is
     * not present yet. Matches on `component` only — an installation may re-tune
     * width/height without the installer duplicating or overwriting the entry — and
     * appends (unlike the quick-menu prepend) so the first-installed module keeps the
     * first slot on the dashboard. An existing entry's access keys are migrated: a
     * stale `policy:` (the removed per-module tenant gates would fail closed) is
     * dropped whenever the new widget declares `app:`/`apps:`, which are copied over.
     *
     * @param  array{component: string, app?: string, apps?: string[], policy?: string, width?: int, height?: int}  $widget
     * @param  string[]  $legacyComponents
     */
    protected function ensureDashboardWidget(array $widget, array $legacyComponents = []): void
    {
        $configPath = base_path('app-configs/dashboard-widgets.yml');

        $config = file_exists($configPath)
            ? (Yaml::parse(file_get_contents($configPath) ?: '') ?? [])
            : [];
        $widgets = $config['widgets'] ?? [];

        foreach ($widgets as $i => $existing) {
            if (in_array($existing['component'] ?? null, $legacyComponents, true)) {
                $widgets[$i]['component'] = $widget['component'];
            }
        }

        $present = false;
        foreach ($widgets as $i => $existing) {
            if (($existing['component'] ?? null) !== $widget['component']) {
                continue;
            }

            $present = true;

            if (isset($widget['app']) || isset($widget['apps'])) {
                unset($widgets[$i]['policy'], $widgets[$i]['app'], $widgets[$i]['apps']);
                foreach (['app', 'apps'] as $key) {
                    if (isset($widget[$key])) {
                        $widgets[$i][$key] = $widget[$key];
                    }
                }
            }

            break;
        }

        if (! $present) {
            $widgets[] = $widget;
        }

        if (array_values($widgets) === array_values($config['widgets'] ?? [])) {
            $this->line('<comment>Dashboard-widgets config already contains the widget.</comment>');

            return;
        }

        $widgets = array_values($widgets);

        $dir = dirname($configPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $config['widgets'] = $widgets;
        file_put_contents($configPath, Yaml::dump($config, 10, 2));
        $this->line('<info>Dashboard-widgets config updated:</info> app-configs/dashboard-widgets.yml');
    }

    /**
     * Ensure a navigation entry exists in a block of the project's setup navigation
     * (app-configs/setup/navigation.yml). Matches on the entry's `route`, so calling
     * this repeatedly never duplicates the entry. Creates the named block if absent.
     * An existing entry with the same route is REPLACED wholesale when it differs —
     * module-owned entries are owned by the module, so title changes and removed
     * keys (e.g. a dropped `config:` gate) propagate on re-install. The same applies
     * across blocks: an entry with that route found in ANY other block is removed, so
     * a module that regroups its entries MOVES them instead of duplicating them.
     *
     * Only the project copy is written — never the module's install template
     * (app-modules/noerd/app-configs/setup/navigation.yml): an entry naming a route
     * of an uninstalled module would otherwise ship to every installation. Stale
     * entries are additionally tolerated at render time — the sidebar skips entries
     * whose route is not registered.
     *
     * @param  array{title: string, route: string, heroicon?: string, superAdmin?: bool}  $entry
     */
    protected function ensureSetupNavigation(string $blockTitle, array $entry): void
    {
        $configPath = base_path('app-configs/setup/navigation.yml');

        if (! file_exists($configPath)) {
            $this->warn('app-configs/setup/navigation.yml not found; navigation entry was not added.');

            return;
        }

        $navigation = Yaml::parse(file_get_contents($configPath) ?: '') ?? [];

        $blockIndex = null;
        foreach ($navigation[0]['block_menus'] ?? [] as $i => $block) {
            if (($block['title'] ?? null) === $blockTitle) {
                $blockIndex = $i;
                break;
            }
        }

        if ($blockIndex === null) {
            $navigation[0]['block_menus'][] = ['title' => $blockTitle, 'navigations' => []];
            $blockIndex = array_key_last($navigation[0]['block_menus']);
        }

        $movedFromOtherBlock = false;
        foreach ($navigation[0]['block_menus'] as $i => $block) {
            if ($i === $blockIndex) {
                continue;
            }

            $navigations = $block['navigations'] ?? [];
            if (! is_array($navigations) || $navigations === []) {
                continue;
            }

            $remaining = array_values(array_filter(
                $navigations,
                fn(array $existing): bool => ($existing['route'] ?? null) !== $entry['route'],
            ));

            if (count($remaining) === count($navigations)) {
                continue;
            }

            $navigation[0]['block_menus'][$i]['navigations'] = $remaining;
            $movedFromOtherBlock = true;
        }

        foreach ($navigation[0]['block_menus'][$blockIndex]['navigations'] ?? [] as $index => $existing) {
            if (($existing['route'] ?? null) !== $entry['route']) {
                continue;
            }

            if ($existing === $entry && ! $movedFromOtherBlock) {
                $this->line("<comment>Setup navigation already contains:</comment> {$entry['title']}");

                return;
            }

            $navigation[0]['block_menus'][$blockIndex]['navigations'][$index] = $entry;
            file_put_contents($configPath, Yaml::dump($navigation, 10, 2));
            $this->line("<info>Setup navigation entry replaced:</info> {$blockTitle} → {$entry['title']}");

            return;
        }

        $navigation[0]['block_menus'][$blockIndex]['navigations'][] = $entry;
        file_put_contents($configPath, Yaml::dump($navigation, 10, 2));
        $this->line("<info>Setup navigation updated:</info> {$blockTitle} → {$entry['title']}");
    }
}
