<?php

declare(strict_types=1);

namespace Noerd\Support;

/**
 * Brings the host's copy of a core navigation (app-configs/setup/navigation.yml,
 * app-configs/profile/navigation.yml) up to the template the package ships —
 * WITHOUT replacing it: modules add their entries to these files
 * (ensureSetupNavigation(), ensureProfileNavigation()) and a host may reorder,
 * retitle or remove entries. So only what the template has and the host lacks
 * is added, and nothing the host has is changed or moved:
 *
 * - a missing top-level key of the app (`title`, `route`, …) is added;
 * - a template block the host does not have (matched by `title`, a `dynamic:`
 *   block by its `dynamic` key) is appended;
 * - a template entry whose `route` appears nowhere in the host navigation is
 *   appended to the block of the same title.
 *
 * The one thing it removes is an entry the core retired:
 * OBSOLETE_ENTRY_CONFIGS names the `config:` gates of entries whose screens no
 * longer exist in the core.
 *
 * @internal
 */
final class HostNavigationMerger
{
    /**
     * `config:` gates of retired core entries — an entry carrying exactly this
     * gate is dropped (the definitions screen of the removed collections DB mode).
     *
     * @var list<string>
     */
    public const OBSOLETE_ENTRY_CONFIGS = ['noerd.collections.show_definitions_ui'];

    /**
     * @param  array<mixed>  $host  the parsed host navigation
     * @param  array<mixed>  $template  the parsed package template
     * @return array<mixed> the merged navigation (=== $host when nothing changed)
     */
    public function merge(array $host, array $template): array
    {
        if (! isset($template[0]) || ! is_array($template[0])) {
            return $this->withoutObsoleteEntries($host);
        }

        if (! isset($host[0]) || ! is_array($host[0])) {
            return $template;
        }

        $app = $host[0];

        foreach ($template[0] as $key => $value) {
            if ($key !== 'blockMenus' && ! array_key_exists($key, $app)) {
                $app[$key] = $value;
            }
        }

        $blocks = is_array($app['blockMenus'] ?? null) ? $app['blockMenus'] : [];
        $routes = $this->routesOf($blocks);

        foreach ((array) ($template[0]['blockMenus'] ?? []) as $templateBlock) {
            if (! is_array($templateBlock)) {
                continue;
            }

            $index = $this->blockIndex($blocks, $templateBlock);
            $missing = array_values(array_filter(
                (array) ($templateBlock['navigations'] ?? []),
                fn(mixed $entry): bool => is_array($entry) && ! in_array($entry['route'] ?? null, $routes, true),
            ));

            if ($index === null) {
                $block = $templateBlock;
                if (array_key_exists('navigations', $templateBlock)) {
                    $block['navigations'] = $missing;
                }
                $blocks[] = $block;
            } elseif ($missing !== []) {
                $blocks[$index]['navigations'] = [...(array) ($blocks[$index]['navigations'] ?? []), ...$missing];
            }

            $routes = $this->routesOf($blocks);
        }

        if ($blocks !== [] || array_key_exists('blockMenus', $app)) {
            $app['blockMenus'] = $blocks;
        }

        $host[0] = $app;

        return $this->withoutObsoleteEntries($host);
    }

    /**
     * The navigation without the entries the core retired.
     *
     * @param  array<mixed>  $navigation
     * @return array<mixed>
     */
    public function withoutObsoleteEntries(array $navigation): array
    {
        foreach ($navigation as $appIndex => $app) {
            if (! is_array($app) || ! is_array($app['blockMenus'] ?? null)) {
                continue;
            }

            foreach ($app['blockMenus'] as $blockIndex => $block) {
                if (! is_array($block) || ! is_array($block['navigations'] ?? null)) {
                    continue;
                }

                $kept = array_values(array_filter(
                    $block['navigations'],
                    static fn(mixed $entry): bool => ! is_array($entry)
                        || ! in_array($entry['config'] ?? null, self::OBSOLETE_ENTRY_CONFIGS, true),
                ));

                if (count($kept) !== count($block['navigations'])) {
                    $navigation[$appIndex]['blockMenus'][$blockIndex]['navigations'] = $kept;
                }
            }
        }

        return $navigation;
    }

    /**
     * @param  array<mixed>  $blocks
     * @param  array<mixed>  $templateBlock
     */
    private function blockIndex(array $blocks, array $templateBlock): ?int
    {
        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $matches = isset($templateBlock['dynamic'])
                ? ($block['dynamic'] ?? null) === $templateBlock['dynamic']
                : ($block['title'] ?? null) === ($templateBlock['title'] ?? null) && ! isset($block['dynamic']);

            if ($matches) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $blocks
     * @return list<string>
     */
    private function routesOf(array $blocks): array
    {
        $routes = [];

        foreach ($blocks as $block) {
            foreach ((array) (is_array($block) ? ($block['navigations'] ?? []) : []) as $entry) {
                if (is_array($entry) && is_string($entry['route'] ?? null)) {
                    $routes[] = $entry['route'];
                }
            }
        }

        return $routes;
    }
}
