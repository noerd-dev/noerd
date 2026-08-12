# Create Navigation

Navigation is defined in YAML files. Each app has its own navigation configuration.

![Noerd Example App](/assets/navigation.png "Navigation")

## File Location

```
app-configs/{app}/navigation.yml
```

For example: `app-configs/accounting/navigation.yml`

## Navigation Structure

```yaml
- title: Accounting
  name: accounting
  hidden: true
  route: accounting-tool
  block_menus:
    - title: Customers
      navigations:
        - title: Customers
          route: 'customers'
          heroicon: 'users'
          newComponent: 'customer-detail'
        - title: Invoices
          route: 'invoices'
          heroicon: 'document-currency-euro'
    - title: Products
      navigations:
        - title: Products
          route: 'products'
          newComponent: 'product-detail'
          heroicon: 'archive-box'
```

## Top-Level Properties

| Property | Description |
|----------|-------------|
| `title` | Display name of the app (translation key) |
| `name` | Unique identifier for the app |
| `route` | The app's main route |
| `hidden` | Hide the top-level menu item |
| `block_menus` | Groups of navigation items (see below) |
| `sub_menu` | Optional flat secondary menu |

## Block Properties (`block_menus[]`)

| Property | Description |
|----------|-------------|
| `title` | Block heading (translation key). Users can collapse a block; the state is kept in the session |
| `navigations` | The entries of the block (see below) |
| `route` | A block with a `route` and no `navigations` renders as a single top-level entry |
| `heroicon` | Icon for the single-entry form |
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
| `icon` | Alternative: a noerd blade icon component name (e.g. `icons.media`) |
| `modalRoute` | Named route opened as a MODAL instead of navigating |
| `component` | Livewire component opened as a modal — fallback for `modalRoute` |
| `arguments` | Arguments passed to the modal component |
| `newRoute` | Named detail route opened as a modal by the "+" button (preferred) |
| `newComponent` | Livewire component opened by the "+" button — fallback for `newRoute` |
| `quickCreate` | With `newRoute`/`newComponent`: open the "+" target as a narrow quick-create modal (`modelId: null`, `quickCreate: true`) |
| `config` | The entry is hidden unless `config(...)` with this key is truthy (e.g. `noerd.features.currency`) |
| `superAdmin` | The entry is only visible to super admins |

### Route vs. component

`route:` always means *navigate to that page*. To open something as a modal, use the
separate keys:

```yaml
- title: Accounts
  route: crm.accounts          # the list page this entry links to
  newRoute: crm.account.detail # the "+" button opens /crm/account/new?modal=true
  newComponent: crm::account-page  # fallback when the route is not registered
  heroicon: 'building-office'
```

`newRoute:`/`modalRoute:` win when the named route is registered; the `*Component`
key is used otherwise, so an entry may reference a route owned by an optional module.
See [Modal System](modal.md#route-modals) for when a route is the right target.

### Entries are filtered by target existence

An entry whose target cannot be resolved is silently dropped: a `route:`/`modalRoute:` must be a
registered route (`link:` and `component:` always pass). This keeps navigation YAMLs valid when an
optional module that owns the route is not installed — stale entries simply disappear instead of
breaking the sidebar.

## Full Example

`app-configs/accounting/navigation.yml`

```yaml
- title: Accounting
  name: accounting
  hidden: true
  route: accounting-tool
  block_menus:
    - title: Customers
      navigations:
        - title: Customers
          route: 'customers'
          heroicon: 'users'
          newComponent: 'customer-detail'
        - title: Invoices
          route: 'invoices'
          heroicon: 'document-currency-euro'
    - title: Finances
      navigations:
        - title: Bank Accounts
          route: 'accounting.bank-accounts'
          heroicon: 'building-library'
          newComponent: 'bank-account-detail'
        - title: Bank Transactions
          route: 'accounting.bank-transactions'
          heroicon: 'banknotes'
    - title: Settings
      navigations:
        - title: Settings
          route: 'accounting-settings'
          heroicon: 'cog-6-tooth'
```

## Next Steps

Continue with [Create a List View](list-view.md) to display data in tables.
