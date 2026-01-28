# Create Navigation

Navigation is defined in YAML files. Each app has its own navigation configuration.

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

## Important Notes

- Always use Heroicons for navigation icons
- Use translation keys for titles (e.g., `accounting_nav_customers`)
- When modifying navigation, ensure changes are made to both the `app-configs/` location and the module's `app-modules/{module}/app-configs/` directory if it exists

## Next Steps

Continue with [Create a List View](list-view.md) to display data in tables.
