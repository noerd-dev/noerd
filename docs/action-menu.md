# Action Menu

`x-noerd::action-menu` is the dropdown menu primitive for component markup — a record's action menu
in a tab bar, the profile menu, the tenant switcher, the list-view switcher and the grid sort menu
build on it. Never hand-roll another `x-data="{ open: false }"` dropdown: the panel chrome, the
click-outside and the escape key live in the component once. (The per-row action dropdown of a
list column with `actions:` is rendered by the table cell itself from the YAML — see
[List View](list-view.md#column-properties).)

## Basic usage

```blade
<x-noerd::action-menu>
    <x-noerd::action-menu-item wire:click="openSendMailModal">
        {{ __('Send Invoice to Customer') }}
    </x-noerd::action-menu-item>

    <x-noerd::action-menu-item wire:confirm="{{ __('Really create dunning notice?') }}" wire:click="createDunning">
        {{ __('Create Dunning Notice') }}
    </x-noerd::action-menu-item>
</x-noerd::action-menu>
```

Without a `trigger` slot the component renders a kebab button labelled "Actions" (override with
`label="…"`, which becomes the screen-reader text).

## In a tab bar

`x-noerd::tabs` has an `actions` slot that renders right-aligned inside the bordered tab row — that
is where a record's action menu belongs:

```blade
<x-noerd::tabs>
    <x-noerd::tab tabNumber="1">{{ __('Customer and Invoice Data') }}</x-noerd::tab>

    <x-slot:actions>
        <x-noerd::action-menu>
            <x-noerd::action-menu-item wire:click="doSomething">{{ __('Do Something') }}</x-noerd::action-menu-item>
        </x-noerd::action-menu>
    </x-slot:actions>
</x-noerd::tabs>
```

## Items

`x-noerd::action-menu-item` renders a `<button type="button">`, or an `<a>` when `href` is set. It
carries `role="menuitem"` and closes the menu on click; `wire:click`, `wire:confirm` and any other
attribute pass through.

| Prop | Purpose |
|---|---|
| `href` | Renders a link instead of a button |
| `navigate` | Adds `wire:navigate` to a link |
| `active` | Highlights the current choice (`font-semibold`) — for switchers, not for actions |

`x-noerd::action-menu-separator` draws a divider between groups of entries.

## Menu props

| Prop | Default | Purpose |
|---|---|---|
| `align` | `right` | Panel origin — `right` or `left` |
| `width` | `w-56` | Panel width utility |
| `label` | `Actions` | Screen-reader label of the default kebab trigger |
| `anchor` | – | An x-anchor reference (e.g. `$refs.sortButton`) instead of absolute positioning; use it when the menu sits inside a scrolling or `overflow-hidden` container that would clip the panel |
| `wrapperClass` | `relative inline-block text-left` | Positioning context of the wrapper — replace it when the menu has to be a flex child |
| `panelClass` | – | Extra classes on the panel |

## Custom trigger

The `trigger` slot renders inside the Alpine scope, so a custom trigger toggles the menu itself:

```blade
<x-noerd::action-menu align="left" width="w-56">
    <x-slot:trigger>
        <button type="button" x-on:click="open = ! open" :aria-expanded="open" aria-haspopup="true">
            {{ $currentTenantName }}
        </button>
    </x-slot:trigger>

    …
</x-noerd::action-menu>
```

## What is NOT an action menu

Only use it for a list of choices. A popover holding a form — `table/column-filter` and
`filters/date-dropdown` in the core keep their own Alpine scope because they carry filter state and
nested controls, and `role="menu"`/`role="menuitem"` would be the wrong semantics for them.
