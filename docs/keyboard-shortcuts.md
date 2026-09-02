# Keyboard Shortcuts

Noerd ships configurable keyboard shortcuts for the recurring list and detail interactions. The
defaults live under `noerd.keyboard_shortcuts` in `config/noerd.php`; a project overrides them
there (there are no environment variables for shortcuts).

Format: `'modifier+key'` — e.g. `'s'`, `'/'`, `'ctrl+enter'`, `'shift+k'`. Supported modifiers:
`ctrl`, `shift`, `alt`, `meta`. `ctrl` also matches the Cmd key on macOS.

## The Shipped Shortcuts

```php
'keyboard_shortcuts' => [
    'search_focus' => 's',
    'new_entry' => 'n',
    'save' => 'ctrl+enter',
    'delete' => 'ctrl+backspace',
],
```

| Config key | Default | What it does | Where it applies |
|------------|---------|--------------|------------------|
| `search_focus` | `s` | Focuses the search input | Every list header (`noerd::components.table.list-search`); Escape blurs the field again |
| `new_entry` | `n` | Triggers the first action button (the "New …" button) | Every list header (`list-controls-primary` / `list-controls-secondary`) — see the note on per-action keys below |
| `save` | `ctrl+enter` | Calls `store()` on the open detail/page | Every `x-noerd::page` whose component has a `store()` method and whose `canSaveObject()` allows it |
| `delete` | `ctrl+backspace` | Asks for confirmation, then calls `delete()` | Every `x-noerd::page` whose component has a `delete()` method and whose `canDeleteObject()` allows it |

**Per-action list shortcuts:** the list header resolves the shortcut of each action button from
the config key `action_{action}` / `action_{route}` (e.g.
`noerd.keyboard_shortcuts.action_listAction`). The first action defaults to `n`; further actions
have no shortcut unless the list YAML sets one via a `shortcut:` key on the action. The badge on
the button always shows the effective shortcut.

## KeyboardShortcutHelper API

`Noerd\Helpers\KeyboardShortcutHelper` turns a configured shortcut into the two things a view
needs: a JS match expression for `@keydown.window` and a badge string for a `<kbd>` element. All
methods read `config("noerd.keyboard_shortcuts.{$configKey}")` and fall back to the given default.

| Method | Returns |
|--------|---------|
| `toJs(string $configKey, string $default): string` | JS boolean expression over the event `e` (for `@keydown.window` handlers) |
| `toBadge(string $configKey, string $default): string` | Human-readable badge string (Mac symbols, key symbols) |
| `parse(string $configKey, string $default): array` | Both at once: `['js' => …, 'badge' => …]` |

### The JS expression

`toJs()` builds an expression like:

```js
e.key.toLowerCase() === "enter" && (e.ctrlKey || e.metaKey)
```

- `ctrl` matches `e.ctrlKey || e.metaKey`, so `ctrl+enter` works as Cmd+Enter on macOS.
- A shortcut **without modifiers** (like the plain `s` / `n`) gets an input guard appended: it is
  skipped while the user is typing in an `INPUT`, `TEXTAREA`, `SELECT` or a `contenteditable`
  element — pressing `s` inside the search field types an "s" instead of re-focusing it.

### The badge

`toBadge()` renders platform-aware symbols (macOS is detected from the request User-Agent):

- `ctrl` → `⌘` on Mac, `Ctrl` elsewhere; `meta` → `⌘` / `Win`; `alt` → `⌥` / `Alt`; `shift` → `⇧`
- Special keys render as symbols: `enter` → `↵`, `backspace` → `⌫`, `delete` → `⌦`,
  `escape` → `⎋`, `tab` → `⇥`

`ctrl+backspace` therefore badges as `⌘+⌫` on a Mac and `Ctrl+⌫` elsewhere.

## Usage in Views

### Lists (helper-driven)

The list header search field (`resources/views/components/table/list-search.blade.php`) is the
reference consumer — one `parse()` call feeds both the window listener and the `<kbd>` badge:

```blade
@php
    $searchShortcut = \Noerd\Helpers\KeyboardShortcutHelper::parse('search_focus', 's');
@endphp

<div @keydown.window="let e = $event; if ({{ $searchShortcut['js'] }}) { e.preventDefault(); $refs.searchInput.focus(); }">
    <x-noerd::text-input x-ref="searchInput" wire:model.live.debounce.300ms="search" … />
    <kbd …>{{ $searchShortcut['badge'] }}</kbd>
</div>
```

The action buttons (`list-controls-primary.blade.php` / `list-controls-secondary.blade.php` in the
same folder) follow the identical pattern with
`parse('action_' . ($actionItem['action'] ?? $actionItem['route'] ?? ''), $effectiveShortcut)`, where
`$effectiveShortcut` is the action's YAML `shortcut:` or `n` for the first action.

### Details (Alpine `noerdPage`)

`x-noerd::page` (`resources/views/components/page.blade.php`) passes the raw shortcut strings into
the `noerdPage` Alpine component:

```blade
@php
    $shortcuts = [];
    if (method_exists($__livewire ?? new stdClass(), 'store') && $canSaveObject && ! $pageObjectReadBlocked) {
        $shortcuts['save'] = config('noerd.keyboard_shortcuts.save', 'ctrl+enter');
    }
    if (method_exists($__livewire ?? new stdClass(), 'delete') && $canDeleteObject && ! $pageObjectReadBlocked) {
        $shortcuts['delete'] = config('noerd.keyboard_shortcuts.delete', 'ctrl+backspace');
    }
@endphp

<div x-data="noerdPage({ …, shortcuts: @js($shortcuts), … })">
```

`$canSaveObject` / `$canDeleteObject` come from the component's `canSaveObject()` /
`canDeleteObject()` (object permissions, see [Permissions](permissions.md)): a denied ability loses
its shortcut together with its button — hiding the button alone would leave the key live.

`noerdPage` (`resources/js/noerd.js`) parses the strings client-side with the same semantics
(`ctrl` matches Ctrl or Cmd), binds one window `keydown` listener per page, calls `$wire.store()`
on save and `$wire.delete()` on delete (behind a `window.confirm`), and removes the listener in
`destroy()`. A shortcut is only registered when the component actually has the matching method —
a list-only component never reacts to `ctrl+enter`.

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

**Important:**

- Shortcuts are configured per installation in `config/noerd.php` — never hardcode a key string in
  a view; go through `KeyboardShortcutHelper` (or, for `x-noerd::page`, the `shortcuts` array) so
  the badge and the listener stay in sync.
- Modifier-less shortcuts are automatically suppressed while typing in form fields; shortcuts with
  modifiers fire everywhere.
- Keys are compared lowercase; write config values in lowercase.
- Embedded details (`embedded: true`, e.g. a detail hosted inside a `*-page`) register **no**
  shortcuts — the hosting page owns them, so save/delete never fire twice.
- Detail shortcuts require the trait methods AND the permission: `save` binds only when the
  component has `store()` and `canSaveObject()` passes, `delete` only when it has `delete()` and
  `canDeleteObject()` passes.
