# Keyboard Shortcuts

Noerd ships configurable keyboard shortcuts for the recurring list and detail interactions. The
defaults live under `noerd.keyboard_shortcuts` in `config/noerd.php`; a project overrides them
there (there are no environment variables for shortcuts).

Format: `'modifier+key'` — e.g. `'s'`, `'/'`, `'ctrl+enter'`, `'shift+k'`, written in lowercase.
Supported modifiers: `ctrl`, `shift`, `alt`, `meta`. `ctrl` also matches the Cmd key on macOS.

## The Shipped Shortcuts

| Config key | Default | What it does | Where it applies |
|------------|---------|--------------|------------------|
| `search_focus` | `s` | Focuses the search input | Every list header; Escape blurs the field again |
| `new_entry` | `n` | Triggers the FIRST header action (the "New …" button) | Every list header |
| `save` | `ctrl+enter` | Calls `store()` on the open detail/page | Every `x-noerd::page` whose component has a `store()` method and whose `canSaveObject()` allows it |
| `delete` | `ctrl+backspace` | Asks for confirmation, then calls `delete()` | Every `x-noerd::page` whose component has a `delete()` method and whose `canDeleteObject()` allows it |

**List header actions:** an action's shortcut is its own `shortcut:` key in the list YAML, else
`new_entry` for the first action of the YAML `actions` array; further actions have none. An
installation can re-key a single action that has a shortcut through
`noerd.keyboard_shortcuts.action_{action}` / `action_{route}` (e.g. `action_listAction`). The badge
on the button always shows the effective shortcut.

```yaml
actions:
  - label: New Item
    route: inventory.item.detail      # first action: new_entry ("n")
  - label: Import
    action: openImportModal
    shortcut: i
```

**Details:** a denied ability loses its shortcut together with its button (object permissions, see
[Permissions](permissions.md)) — hiding the button alone would leave the key live. A component
without the matching method never reacts (a list ignores `ctrl+enter`), and an embedded detail
(`embedded: true`, hosted inside a `*-page`) registers NO shortcuts — the hosting page owns them, so
save/delete never fire twice. `x-noerd::page` hands the keys to the `noerdPage` Alpine component
(`resources/js/noerd.js`), which binds one window listener per page and removes it on destroy.

## Changing a Shortcut

Override the key in the **project's** `config/noerd.php`:

```php
'keyboard_shortcuts' => [
    'search_focus' => '/',
    'save' => 'ctrl+s',
],
```

Every consumer resolves the shortcut through the config at render time, so the listeners **and**
the `<kbd>` badges update everywhere — no view changes needed.

## KeyboardShortcutHelper API

A view of your own never hardcodes a key string: `Noerd\Helpers\KeyboardShortcutHelper::parse(string
$configKey, string $default): array` reads `config("noerd.keyboard_shortcuts.{$configKey}")`
(falling back to `$default`) and returns the two things a view needs — `['js' => …, 'badge' => …]`.

- **`js`** is a match expression for `@keydown.window`, e.g.
  `e.key.toLowerCase() === "enter" && (e.ctrlKey || e.metaKey)` (`ctrl` matches Ctrl or Cmd). A
  shortcut **without modifiers** (`s`, `n`) gets an input guard appended: it is skipped while the
  user types in an `INPUT`, `TEXTAREA`, `SELECT` or a `contenteditable` element; shortcuts with
  modifiers fire everywhere.
- **`badge`** is the string for a `<kbd>` element with platform-aware symbols (macOS is detected
  from the request User-Agent): `ctrl` → `⌘` on Mac, `Ctrl` elsewhere; `meta` → `⌘` / `Win`; `alt` →
  `⌥` / `Alt`; `shift` → `⇧`; `enter` → `↵`, `backspace` → `⌫`, `delete` → `⌦`, `escape` → `⎋`,
  `tab` → `⇥`. `ctrl+backspace` badges as `⌘+⌫` on a Mac and `Ctrl+⌫` elsewhere.

```blade
@php
    $searchShortcut = \Noerd\Helpers\KeyboardShortcutHelper::parse('search_focus', 's');
@endphp

<div @keydown.window="let e = $event; if ((window.noerdTopLayer?.($el) ?? true) && ({{ $searchShortcut['js'] }})) { e.preventDefault(); $refs.searchInput.focus(); }">
    <x-noerd::text-input x-ref="searchInput" wire:model.live.debounce.300ms="search" … />
    <kbd …>{{ $searchShortcut['badge'] }}</kbd>
</div>
```

## Only the Topmost Layer Reacts

A page behind an open modal is still mounted and its window listener is still bound, so every
shortcut has to ask whether it is on the layer the user is looking at. The topmost layer is the
LAST modal panel in the document (the modal stack teleports its panels to `<body>` in open order);
with no modal open — or without the modal package installed — the page itself is the top layer.

- `noerdPage` checks it before save and delete, so `ctrl+enter` in a modal never also saves the
  record behind it, and a stacked modal never decides the record underneath.
- Every `@keydown.window` listener of your own carries the `window.noerdTopLayer` guard (exported
  by `noerd.js`) shown above — a shortcut that skips it fires on every open layer at once. The
  optional chaining plus `?? true` is deliberate: a project whose published asset bundle is older
  than its views falls back to the unguarded behaviour instead of throwing on every keystroke.
- Row navigation (`noerdList`: arrow keys and Enter) additionally claims the hovered list through
  the shared Alpine store, so only one list on a layer answers the arrow keys, and it is suppressed
  while the user types in a form field.
