/**
 * Keyboard rules shared by the page shortcuts (save, delete) and the list
 * navigation. Pure functions, so tests/js/keyboard.mjs can prove them without a
 * browser; KeyboardShortcutHelper (PHP) generates the same rules for the list
 * header shortcuts.
 */

/**
 * @param {string} shortcut e.g. "ctrl+enter", "n"
 * @returns {{key: string, ctrl: boolean, shift: boolean, alt: boolean, meta: boolean}}
 */
export function parseShortcut(shortcut) {
    const parts = String(shortcut).toLowerCase().split('+').map((part) => part.trim());
    const key = parts.pop();

    return {
        key,
        ctrl: parts.includes('ctrl'),
        shift: parts.includes('shift'),
        alt: parts.includes('alt'),
        meta: parts.includes('meta'),
    };
}

/**
 * Does a keydown event trigger the parsed shortcut?
 *
 * `ctrl` accepts Cmd on macOS as well. A shortcut WITHOUT ctrl/meta/alt never
 * fires while one of them is held — "n" must not open a record when the reader
 * presses Ctrl+N or types an AltGr character. Shift is only checked when the
 * shortcut asks for it: on many layouts "/" or "?" need Shift to be typed.
 * A held key (auto-repeat) never triggers: one press, one action.
 *
 * @param {KeyboardEvent} event
 * @param {{key: string, ctrl: boolean, shift: boolean, alt: boolean, meta: boolean}} parsed
 */
export function matchesShortcut(event, parsed) {
    if (event.repeat) return false;
    if ((event.key ?? '').toLowerCase() !== parsed.key) return false;

    const ctrlOrMeta = Boolean(event.ctrlKey || event.metaKey);

    if (parsed.ctrl && ! ctrlOrMeta) return false;
    if (parsed.meta && ! event.metaKey) return false;
    if (! parsed.ctrl && ! parsed.meta && ctrlOrMeta) return false;
    if (parsed.alt !== Boolean(event.altKey)) return false;
    if (parsed.shift && ! event.shiftKey) return false;

    return true;
}

/**
 * Is the element a place the reader types into? Keys pressed there belong to
 * the field (Backspace deletes a character, arrows move the caret), never to a
 * page or list shortcut.
 *
 * @param {Element|null|undefined} el
 */
export function isEditableElement(el) {
    if (! el) return false;

    return ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName)
        || Boolean(el.isContentEditable)
        || Boolean(el.closest?.('[contenteditable]:not([contenteditable="false"])'));
}

/**
 * The highlighted row after an arrow key: one step in `direction`, kept inside
 * the rows of the current page (0 .. rowCount - 1).
 *
 * @param {number|string} current
 * @param {number} direction
 * @param {number} rowCount
 */
export function clampRow(current, direction, rowCount) {
    const count = Math.max(0, Math.trunc(Number(rowCount) || 0));

    if (count === 0) return 0;

    const next = (Math.trunc(Number(current)) || 0) + direction;

    return Math.min(count - 1, Math.max(0, next));
}
