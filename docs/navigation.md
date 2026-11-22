# Navigation

Navigation is defined in YAML files. Each app has its own navigation configuration.

![Noerd Example App](/assets/navigation.png "Navigation")

## File Location

```text
app-configs/{app}/navigation.yml
```

For example: `app-configs/inventory/navigation.yml` (the module ships its template at
`app-modules/inventory/app-configs/inventory/navigation.yml` — keep both copies in sync)

A navigation file is a list with one app entry: app metadata on top, `blockMenus` with their
`navigations` below — see the [full example](#full-example).

## Top-Level Properties

| Property | Description |
|----------|-------------|
| `title` | Display name of the app (translation key) |
| `name` | Unique identifier for the app |
| `route` | The app's main route |
| `blockMenus` | Groups of navigation items (see below) |

`title`, `name` and `route` are app metadata: `noerd:install-{module}` writes them into the
installed copy from the app title given during installation plus the app key
(`HasModuleInstallation::installAsNewApp()`), so a module template only needs sensible defaults
there. From then on the installed copy is the host's: `noerd:update-{module}` copies the template
only when the file is missing (`--force` does not touch it); `--reset-navigation` replaces it. Whether an app shows up in the app bar is not a navigation key — it is the tenant's
`tenant_app.is_hidden` flag (Setup → Apps).

## Block Properties (`blockMenus[]`)

| Property | Description |
|----------|-------------|
| `title` | Block heading (translation key). Users can collapse a block; the state is kept in the session |
| `navigations` | The entries of the block (see below) |
| `route` | A block with a `route` and no `navigations` renders as a single top-level entry. `config:` / `superAdmin:` are entry keys and are not evaluated on it |
| `heroicon` | Icon for the single-entry form and for the entry of a sub-navigation |
| `submenu` | `true` shows the block as a [sub-navigation](#sub-navigations): one entry that opens the block's entries |
| `style` | `list` (default) or `buttons` — renders the block's entries as buttons |
| `dynamic` | Provider type resolved through the `DynamicNavigationRegistry` — the block's entries are generated at runtime (e.g. the Setup collections). See [Extension Registries](extension-registries.md) |

## Entry Properties (`navigations[]`)

| Property | Description |
|----------|-------------|
| `title` | Display name (translation key) |
| `route` | Laravel route name — the entry NAVIGATES there (and drives the active-state highlight) |
| `link` | Plain URL (`/…` or absolute) instead of a named route |
| `external` | Opens the target in a new tab and shows an external-link icon |
| `heroicon` | Icon from Heroicons (e.g., `users`, `cog-6-tooth`) |
| `modalRoute` | Named route opened as a MODAL instead of navigating |
| `modalComponent` | Livewire component opened as a modal — fallback for `modalRoute`, or the target on its own |
| `arguments` | Arguments passed to the modal opened by `modalRoute`/`modalComponent` — and, merged with the quick-create keys, to the "+" target |
| `newModalRoute` | Named detail route opened as a modal by the "+" button (preferred). The "+" renders only on a plain `route:` entry — not on `link:`, `modalRoute:` or `modalComponent:` entries |
| `newModalComponent` | Livewire component opened by the "+" button — fallback for `newModalRoute` |
| `quickCreate` | With `newModalRoute`/`newModalComponent`: open the "+" target as a narrow quick-create modal (`modelId: null`, `quickCreate: true`) |
| `config` | The entry is hidden unless `config(...)` with this key is truthy (e.g. `noerd.features.currency`) |
| `superAdmin` | The entry is only visible to super admins — the installation admins, see [Permissions](permissions.md#super-admin-installation-admin) |

### Route vs. component

`route:` always means *navigate to that page*; opening something as a modal uses the modal keys
every YAML shares (`modalRoute:`/`modalComponent:` for the entry itself,
`newModalRoute:`/`newModalComponent:` for its "+" button — see
[Modal System](modal.md#modal-targets-in-yaml)).

Route names follow the module convention (see [Creating Modules](creating-modules.md)):
`{module}.{entities}` for the list page, `{module}.{entity}.detail` for the record, and
`{module}::{entity}-detail` for the component fallback.

`newModalRoute:`/`modalRoute:` win when the named route is registered; the `*ModalComponent`
key is used otherwise, so an entry may reference a route owned by an optional module.
See [Modal System](modal.md#route-modals) for when a route is the right target.

### Entries are filtered by target existence

An entry whose target cannot be resolved is silently dropped: a `route:`/`modalRoute:` must be a
registered route (`link:` and `modalComponent:` always pass). This keeps navigation YAMLs valid when an
optional module that owns the route is not installed — stale entries simply disappear instead of
breaking the sidebar.

## Sub-navigations

A long group of entries — typically the settings of an app — does not need to fill the sidebar. Mark
its block with `submenu: true`:

```yaml
    - title: Settings
      submenu: true
      heroicon: cog-6-tooth   # optional
      navigations:
        - title: General
          route: inventory.settings
          heroicon: adjustments-horizontal
        - title: Printers
          route: printers
          heroicon: printer
```

- The navigation shows the block as ONE entry (its `heroicon`, its title and a chevron) in the place of
  the block. Clicking it slides the sidebar to the block's entries, headed by a "Back" link and the
  block title; "Back" slides back to the navigation. Switching changes no page.
- When the current page is an entry of a sub-navigation, the sidebar opens in that sub-navigation.
- `style: buttons` applies to the entries inside the sub-navigation. A sub-navigation is never
  collapsed — the session state of collapsed blocks does not apply to it.
- The entries are filtered like every other entry (see [Entries are filtered by target existence](#entries-are-filtered-by-target-existence)); a sub-navigation whose entries are all
  filtered out disappears.

## Full Example

`app-configs/inventory/navigation.yml`

```yaml
- title: Inventory
  name: inventory
  route: inventory
  blockMenus:
    - title: Stock
      navigations:
        - title: Items
          route: inventory.items
          heroicon: archive-box
          newModalRoute: inventory.item.detail
          newModalComponent: inventory::item-detail
        - title: Stock Movements
          route: inventory.stock-movements
          heroicon: arrows-right-left
        - title: Import
          modalComponent: inventory::stock-import-modal
          arguments:
            source: sidebar
          heroicon: arrow-up-tray
    - title: Reports
      style: buttons
      navigations:
        - title: Low Stock
          link: /inventory/items?view=low-stock
          heroicon: exclamation-triangle
        - title: Supplier Portal
          link: https://example.com
          external: true
          heroicon: globe-alt
    - title: Settings
      submenu: true
      heroicon: cog-6-tooth
      navigations:
        - title: Settings
          route: inventory.settings
          heroicon: cog-6-tooth
        - title: Currencies
          route: inventory.currencies
          heroicon: currency-euro
          config: noerd.features.currency
```

## Setup and profile navigation

Two navigations belong to no tenant app and are selected by route middleware instead:

- **Setup** (`app-configs/setup/navigation.yml`, middleware `setup`, admins only) — a module adds
  an entry with `ensureSetupNavigation('Administration', [...])` in its install command's
  `ensureModuleSetup()`.
- **Profile** (`app-configs/profile/navigation.yml`, middleware `profile`, every signed-in person)
  — the pages of the person's own account: Profile and Password (`noerd.profile`), Language and
  Locale (`noerd.profile.language`), Tenant Access (`noerd.profile.tenants`), Delete Account
  (`noerd.profile.delete`). A module adds a page of the person's own data with
  `ensureProfileNavigation('My Account', ['title' => 'My Connectors', 'route' => 'mymodule.connectors', 'heroicon' => 'link'])`;
  its route (`Route::livewire()`) carries `['noerd', 'profile']`, so the sidebar shows this
  navigation. Every profile page — the core's (`noerd::profile-page`, `profile-language-page`,
  `profile-tenants-page`, `profile-delete-page`) and a module's — is a full-page Livewire
  component whose root is `<x-noerd::page>`, like every other page: the title goes into
  `<x-slot:header>` with `<x-noerd::modal-title>`, so it also shows when the page is opened in a
  modal, and each block is one `<x-noerd::profile-section>` (box chrome and reading width).

Both files are host configuration: `noerd:install` publishes them when they are missing, and
`noerd:update` never replaces them — not even with `--force`. It only ADDS the core entries the
file lacks (a missing block, an entry whose `route` appears nowhere in the file) and drops entries
the core retired; host and module entries, their order and their titles stay. Entries can be
reordered and retitled there — a core entry that was deleted comes back with the next update.
`noerd:update --reset-navigation` replaces both files with the package template. An entry whose
route does not exist is skipped at render time.

## Next Steps

Continue with [Create a List View](list-view.md) to display data in tables.
