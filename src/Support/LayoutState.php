<?php

declare(strict_types=1);

namespace Noerd\Support;

/**
 * The per-session layout preferences (sidebar and app bar visibility, the
 * navigation width, collapsed navigation blocks) — the single owner of their
 * session keys, read by the layouts and written by the layout components.
 */
final class LayoutState
{
    private const SIDEBAR_HIDDEN = 'hide_sidebar';

    private const APP_BAR_HIDDEN = 'hide_appbar';

    private const NAVIGATION_WIDTH = 'sidebar_nav_width';

    private const BLOCK_MENU_HIDDEN = 'navi_hidden_';

    public static function sidebarVisible(): bool
    {
        return ! session(self::SIDEBAR_HIDDEN);
    }

    public static function setSidebarVisible(bool $visible): void
    {
        self::setVisible(self::SIDEBAR_HIDDEN, $visible);
    }

    public static function appBarVisible(): bool
    {
        return ! session(self::APP_BAR_HIDDEN);
    }

    public static function setAppBarVisible(bool $visible): void
    {
        self::setVisible(self::APP_BAR_HIDDEN, $visible);
    }

    public static function navigationWidth(): string
    {
        return (string) session(self::NAVIGATION_WIDTH, config('noerd.sidebar.navigation_width', '280px'));
    }

    public static function setNavigationWidth(string $width): void
    {
        session([self::NAVIGATION_WIDTH => $width]);
    }

    public static function blockMenuVisible(string $title): bool
    {
        return ! session(self::BLOCK_MENU_HIDDEN . $title);
    }

    public static function setBlockMenuVisible(string $title, bool $visible): void
    {
        self::setVisible(self::BLOCK_MENU_HIDDEN . $title, $visible);
    }

    /**
     * Visibility is stored as the ABSENCE of a "hidden" flag, so a fresh
     * session shows everything without any seeded state.
     */
    private static function setVisible(string $hiddenKey, bool $visible): void
    {
        $visible ? session()->forget($hiddenKey) : session([$hiddenKey => true]);
    }
}
