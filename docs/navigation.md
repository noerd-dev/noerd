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

## Navigation Properties

| Property | Description |
|----------|-------------|
| `title` | Display name (use translation key) |
| `name` | Unique identifier for the app |
| `route` | Laravel route name — the entry NAVIGATES there (and drives the active-state highlight) |
| `heroicon` | Icon from Heroicons (e.g., `users`, `cog-6-tooth`) |
| `newRoute` | Named detail route opened as a modal by the "+" button (preferred) |
| `newComponent` | Livewire component opened by the "+" button — fallback for `newRoute` |
| `modalRoute` | Named route opened as a MODAL instead of navigating |
| `hidden` | Hide the top-level menu item |
| `block_menus` | Groups of navigation items |

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

## Example

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
        - title: Quotes
          route: 'accounting.quotes'
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
    - title: Products
      navigations:
        - title: Products
          route: 'products'
          newComponent: 'product-detail'
          heroicon: 'archive-box'
        - title: Product Groups
          route: 'product-groups'
          heroicon: 'rectangle-group'
    - title: Settings
      navigations:
        - title: Settings
          route: 'accounting-settings'
          heroicon: 'cog-6-tooth'
```

## Next Steps

Continue with [Create a List View](list-view.md) to display data in tables.
