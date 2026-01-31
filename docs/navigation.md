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
    - title: accounting_nav_customers
      navigations:
        - title: accounting_nav_customers
          route: 'customers'
          heroicon: 'users'
          newComponent: 'customer-detail'
        - title: accounting_nav_invoices
          route: 'invoices'
          heroicon: 'document-currency-euro'
    - title: accounting_nav_products
      navigations:
        - title: accounting_nav_products
          route: 'products'
          newComponent: 'product-detail'
          heroicon: 'archive-box'
```

## Navigation Properties

| Property | Description |
|----------|-------------|
| `title` | Display name (use translation key) |
| `name` | Unique identifier for the app |
| `route` | Laravel route name |
| `heroicon` | Icon from Heroicons (e.g., `users`, `cog-6-tooth`) |
| `newComponent` | Livewire component to open when clicking "New" |
| `hidden` | Hide the top-level menu item |
| `block_menus` | Groups of navigation items |

## Example

`app-configs/accounting/navigation.yml`

```yaml
- title: Accounting
  name: accounting
  hidden: true
  route: accounting-tool
  block_menus:
    - title: accounting_nav_customers
      navigations:
        - { title: accounting_nav_customers, route: 'customers', heroicon: 'users', newComponent: 'customer-detail' }
        - { title: accounting_nav_invoices, route: 'invoices', heroicon: 'document-currency-euro' }
        - { title: accounting_nav_quotes, route: 'accounting.quotes', heroicon: 'document-currency-euro' }
    - title: accounting_nav_finances
      navigations:
        - { title: accounting_nav_bank_accounts, route: 'accounting.bank-accounts', heroicon: 'building-library', newComponent: 'bank-account-detail' }
        - { title: accounting_nav_bank_transactions, route: 'accounting.bank-transactions', heroicon: 'banknotes' }
    - title: accounting_nav_products
      navigations:
        - { title: accounting_nav_products, route: 'products', newComponent: 'product-detail', heroicon: 'archive-box' }
        - { title: accounting_nav_product_groups, route: 'product-groups', heroicon: 'rectangle-group' }
    - title: accounting_nav_settings
      navigations:
        - { title: accounting_nav_settings, route: 'accounting-settings', heroicon: 'cog-6-tooth' }
```

## Next Steps

Continue with [Create a List View](list-view.md) to display data in tables.
