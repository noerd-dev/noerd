<?php

declare(strict_types=1);

namespace Noerd\Support;

/**
 * A configured keyboard shortcut (`config('noerd.keyboard_shortcuts.*')`) as
 * the Alpine key expression and the badge text — for every view that binds
 * a shortcut, the core's and a module's alike.
 */
final class KeyboardShortcutHelper
{
    private const KEY_SYMBOLS = [
        'enter' => '↵',
        'backspace' => '⌫',
        'delete' => '⌦',
        'escape' => '⎋',
        'tab' => '⇥',
    ];

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

    /**
     * The Alpine condition for a keydown event `e`. It mirrors matchesShortcut()
     * and isEditableElement() of resources/js/keyboard.js: a held key never
     * repeats the action, ctrl accepts Cmd, a shortcut without ctrl/meta/alt
     * never fires while one of them is held (Ctrl+N is the browser's, AltGr
     * types a character) and never while the reader types into a field.
     */
    private static function buildJs(string $shortcut): string
    {
        ['modifiers' => $modifiers, 'key' => $key] = self::parseShortcutString($shortcut);

        $js = '!e.repeat && (e.key ?? \'\').toLowerCase() === ' . json_encode($key);

        $ctrl = in_array('ctrl', $modifiers, true);
        $meta = in_array('meta', $modifiers, true);

        if ($ctrl) {
            $js .= ' && (e.ctrlKey || e.metaKey)';
        }
        if ($meta) {
            $js .= ' && e.metaKey';
        }
        if (! $ctrl && ! $meta) {
            $js .= ' && !e.ctrlKey && !e.metaKey';
        }
        if (in_array('shift', $modifiers, true)) {
            $js .= ' && e.shiftKey';
        }
        $js .= in_array('alt', $modifiers, true) ? ' && e.altKey' : ' && !e.altKey';

        if (count($modifiers) === 0) {
            $js .= " && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName) && !document.activeElement?.isContentEditable && !document.activeElement?.closest?.('[contenteditable]:not([contenteditable=\"false\"])')";
        }

        return $js;
    }

    private static function buildBadge(string $shortcut): string
    {
        ['modifiers' => $modifiers, 'key' => $key] = self::parseShortcutString($shortcut);

        $isMac = self::isMac();
        $badge = [];

        if (in_array('ctrl', $modifiers, true)) {
            $badge[] = $isMac ? '⌘' : 'Ctrl';
        }
        if (in_array('meta', $modifiers, true)) {
            $badge[] = $isMac ? '⌘' : 'Win';
        }
        if (in_array('alt', $modifiers, true)) {
            $badge[] = $isMac ? '⌥' : 'Alt';
        }
        if (in_array('shift', $modifiers, true)) {
            $badge[] = '⇧';
        }

        $badge[] = self::KEY_SYMBOLS[$key] ?? mb_strtolower($key);

        return implode('+', $badge);
    }

    /**
     * @return array{modifiers: string[], key: string}
     */
    private static function parseShortcutString(string $shortcut): array
    {
        $parts = array_map('trim', explode('+', mb_strtolower($shortcut)));
        $key = array_pop($parts);

        return ['modifiers' => $parts, 'key' => $key];
    }

    private static function isMac(): bool
    {
        return str_contains(request()->header('User-Agent', ''), 'Mac');
    }
}
