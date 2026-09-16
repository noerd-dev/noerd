<?php

declare(strict_types=1);

namespace Noerd\Support;

use Livewire\Component;
use Noerd\Services\HeaderActionsRegistry;
use Noerd\Traits\NoerdPage;

/**
 * Decides where the module-contributed detail header actions (HeaderActionsRegistry,
 * e.g. an export or history icon) are mounted: in the head row of the FIRST form block a
 * `*-detail` / `*-page` host renders — never in the modal header. An embedded detail
 * renders chrome-less, but its form block still belongs to it, so every detail carries
 * its own icons and a page embedding two details shows two sets.
 *
 * The actions are Livewire children keyed per parent, so they must mount exactly once
 * per host render: claim() answers the registered actions the first time a host asks
 * during its render and nothing afterwards (a second tab, a nested `type: block`, the
 * seventh direct block include of a hand-built detail). NoerdPage's rendering hook
 * resets the claim, so every render — also the many requests one test process serves
 * for the same component id — starts unclaimed.
 */
final class DetailHeaderActions
{
    /** @var array<string, true> */
    private static array $claimed = [];

    /**
     * The actions to mount in this block, or nothing.
     *
     * @return array<int, string>
     */
    public static function claim(?object $host): array
    {
        if (! self::hosts($host)) {
            return [];
        }

        /** @var Component $host */
        $id = $host->getId();

        // A component rendered outside a Livewire request has no id yet.
        if ($id === null || isset(self::$claimed[$id])) {
            return [];
        }

        self::$claimed[$id] = true;

        return app(HeaderActionsRegistry::class)->detailActions();
    }

    public static function reset(?string $hostId): void
    {
        if ($hostId !== null) {
            unset(self::$claimed[$hostId]);
        }
    }

    /**
     * A `*-detail` always hosts the actions on its form; a `*-page` only when its page
     * YAML declares a field grid of its own — a page without fields has no layout to
     * edit, its embedded detail shows the icons. Quick-create dialogs are no place
     * for admin tooling.
     */
    private static function hosts(?object $host): bool
    {
        if (! $host instanceof Component || ! in_array(NoerdPage::class, class_uses_recursive($host), true)) {
            return false;
        }

        if ($host->quickCreate ?? false) {
            return false;
        }

        $name = $host->getName();

        if (str_ends_with($name, '-detail')) {
            return true;
        }

        return str_ends_with($name, '-page') && ($host->pageLayout['fields'] ?? []) !== [];
    }
}
