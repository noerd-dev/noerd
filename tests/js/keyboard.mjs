// Behavioural harness for the keyboard rules (resources/js/keyboard.js): the
// page shortcuts and the list navigation. Run by FrontendScriptsTest.php.
import assert from 'node:assert/strict';

const { parseShortcut, matchesShortcut, isEditableElement, clampRow } = await import('../../resources/js/keyboard.js');

const key = (k, mods = {}) => ({ key: k, ctrlKey: false, metaKey: false, altKey: false, shiftKey: false, repeat: false, ...mods });

const save = parseShortcut('ctrl+enter');
const del = parseShortcut('ctrl+backspace');
const n = parseShortcut('n');

assert.ok(matchesShortcut(key('Enter', { ctrlKey: true }), save));
assert.ok(matchesShortcut(key('Enter', { metaKey: true }), save), 'Cmd counts as ctrl');
assert.ok(! matchesShortcut(key('Enter'), save));
assert.ok(! matchesShortcut(key('Enter', { ctrlKey: true, repeat: true }), save), 'a held key saves once');
assert.ok(! matchesShortcut(key('Backspace', { ctrlKey: true, repeat: true }), del), 'a held key never deletes again');
assert.ok(! matchesShortcut(key('Enter', { ctrlKey: true, altKey: true }), save));
assert.ok(! matchesShortcut({ ctrlKey: true }, save), 'an event without key must not throw');

assert.ok(matchesShortcut(key('n'), n));
assert.ok(matchesShortcut(key('N', { shiftKey: true }), n), 'shift is not checked unless asked for');
assert.ok(! matchesShortcut(key('n', { ctrlKey: true }), n), 'Ctrl+N belongs to the browser');
assert.ok(! matchesShortcut(key('n', { metaKey: true }), n));
assert.ok(! matchesShortcut(key('n', { altKey: true }), n), 'AltGr/Alt types a character');
assert.ok(! matchesShortcut(key('n', { repeat: true }), n));

// Fields the reader types into.
const el = (tagName, extra = {}) => ({ tagName, isContentEditable: false, closest: () => null, ...extra });
assert.ok(isEditableElement(el('INPUT')));
assert.ok(isEditableElement(el('TEXTAREA')));
assert.ok(isEditableElement(el('SELECT')));
assert.ok(isEditableElement(el('DIV', { isContentEditable: true })));
assert.ok(isEditableElement(el('P', { closest: () => ({}) })), 'inside a contenteditable editor');
assert.ok(! isEditableElement(el('BUTTON')));
assert.ok(! isEditableElement(null));

// List navigation stays inside the rows of the page.
assert.equal(clampRow(0, 1, 3), 1);
assert.equal(clampRow(2, 1, 3), 2, 'never past the last row');
assert.equal(clampRow(0, -1, 3), 0, 'never before the first row');
assert.equal(clampRow('2', 1, 5), 3, 'a string index (old markup) is still a number');
assert.equal(clampRow(7, -1, 3), 2, 'a stale index after a search is pulled back');
assert.equal(clampRow(0, 1, 0), 0, 'an empty list');
