<?php

namespace Noerd\Helpers;

class KeyboardShortcutHelper
{
    private const KEY_SYMBOLS = [
        'enter' => '↵',
        'backspace' => '⌫',
        'delete' => '⌦',
        'escape' => '⎋',
        'tab' => '⇥',
    ];

    /**
     * Build a JS boolean expression for use in @keydown.window handlers.
     * Includes an input guard for shortcuts without modifiers.
     */
    public static function toJs(string $configKey, string $default): string
    {
        $shortcut = config("noerd.keyboard_shortcuts.{$configKey}", $default);

        return self::buildJs($shortcut);
    }

    /**
     * Build a badge string with Mac symbols and key symbols.
     */
    public static function toBadge(string $configKey, string $default): string
    {
        $shortcut = config("noerd.keyboard_shortcuts.{$configKey}", $default);

        return self::buildBadge($shortcut);
    }

    /**
     * Build both JS expression and badge string.
     *
     * @return array{js: string, badge: string}
     */
    public static function parse(string $configKey, string $default): array
    {
        $shortcut = config("noerd.keyboard_shortcuts.{$configKey}", $default);

        return [
            'js' => self::buildJs($shortcut),
            'badge' => self::buildBadge($shortcut),
        ];
    }

    private static function buildJs(string $shortcut): string
    {
        ['modifiers' => $modifiers, 'key' => $key] = self::parseShortcutString($shortcut);

        $js = 'e.key.toLowerCase() === ' . json_encode($key);

        if (in_array('ctrl', $modifiers)) {
            $js .= ' && (e.ctrlKey || e.metaKey)';
        }
        if (in_array('shift', $modifiers)) {
            $js .= ' && e.shiftKey';
        }
        if (in_array('alt', $modifiers)) {
            $js .= ' && e.altKey';
        }
        if (in_array('meta', $modifiers)) {
            $js .= ' && e.metaKey';
        }

        if (count($modifiers) === 0) {
            $js .= " && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName) && !document.activeElement.isContentEditable";
        }

        return $js;
    }

    private static function buildBadge(string $shortcut): string
    {
        ['modifiers' => $modifiers, 'key' => $key] = self::parseShortcutString($shortcut);

        $isMac = self::isMac();
        $badge = [];

        if (in_array('ctrl', $modifiers)) {
            $badge[] = $isMac ? '⌘' : 'Ctrl';
        }
        if (in_array('meta', $modifiers)) {
            $badge[] = $isMac ? '⌘' : 'Win';
        }
        if (in_array('alt', $modifiers)) {
            $badge[] = $isMac ? '⌥' : 'Alt';
        }
        if (in_array('shift', $modifiers)) {
            $badge[] = '⇧';
        }

        $badge[] = self::KEY_SYMBOLS[$key] ?? strtolower($key);

        return implode('+', $badge);
    }

    /**
     * @return array{modifiers: string[], key: string}
     */
    private static function parseShortcutString(string $shortcut): array
    {
        $parts = array_map('trim', explode('+', strtolower($shortcut)));
        $key = array_pop($parts);

        return ['modifiers' => $parts, 'key' => $key];
    }

    private static function isMac(): bool
    {
        return str_contains(request()->header('User-Agent', ''), 'Mac');
    }
}
