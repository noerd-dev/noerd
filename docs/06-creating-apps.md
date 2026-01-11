# Creating Apps

Apps control which functionality is available to tenants. They appear in the app switcher.

## Creating an App

```bash
php artisan noerd:create-app
```

The command asks for:
1. **App Title** - Display name (e.g., "Customer Management")
2. **App Name** - Unique identifier, uppercase (e.g., "CRM")
3. **Icon** - Heroicon name (searchable)
4. **Route** - Main route name (e.g., "crm.index")

## Assigning Apps to Tenants

```bash
php artisan noerd:assign-apps-to-tenant
```

Select a tenant, then toggle apps on/off with Space.

## Quick Workflow

```bash
# 1. Create the module
php artisan noerd:module crm

# 2. Register module
composer update noerd/crm
php artisan migrate

# 3. Create the app
php artisan noerd:create-app

# 4. Assign to tenant
php artisan noerd:assign-apps-to-tenant
```

---

## Background: Apps vs. Modules

**Apps and modules are not the same thing.**

| Aspect | Module | App |
|--------|--------|-----|
| Purpose | Code organization | User access control |
| Location | `app-modules/` | Database (`tenant_apps` table) |
| Contains | PHP code, views, routes | Configuration only |

### Examples

- **One module, multiple apps:** The `noerd` module provides both "Setup" and core functionality.
- **Multiple modules, one app:** A "Shop" app might combine `product`, `order`, and `voucher` modules.
- **One module, one app:** A `media` module maps directly to a "Media" app.

```
┌─────────────────────────────────────────────────────┐
│                     Tenant A                        │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐             │
│  │ CMS App │  │Shop App │  │Setup App│             │
│  └────┬────┘  └────┬────┘  └────┬────┘             │
└───────┼────────────┼────────────┼───────────────────┘
        │            │            │
        ▼            ▼            ▼
┌───────────┐  ┌───────────┐  ┌───────────┐
│cms module │  │product    │  │noerd      │
│           │  │order      │  │module     │
│           │  │voucher    │  │           │
└───────────┘  └───────────┘  └───────────┘
```

---

## Detailed Command Reference

### noerd:create-app

Interactive mode:

```bash
$ php artisan noerd:create-app

 App Title (display name):
 > Customer Management

 App Name (unique identifier):
 > CRM

 Search for a Heroicon:
 > users

 App Route:
 > crm.index

✅ Tenant app created successfully!
```

Non-interactive mode:

```bash
php artisan noerd:create-app \
  --title="Customer Management" \
  --name="CRM" \
  --icon="heroicon:outline:users" \
  --route="crm.index"
```

### noerd:assign-apps-to-tenant

```bash
$ php artisan noerd:assign-apps-to-tenant

 Select a tenant:
 > Company Inc. (ID: 1, Apps: 2)

 Select apps to assign:
 › ◉ CMS (CMS)
   ◯ Customer Management (CRM)
   ◉ Setup (SETUP)

✅ App assignments updated successfully!
```

Non-interactive:

```bash
php artisan noerd:assign-apps-to-tenant --tenant-id=1
```

## App Navigation

Each app needs a `navigation.yml`:

```
app-configs/{app-name}/navigation.yml
```

Example:

```yaml
- title: crm_nav_customers
  name: customers
  route: crm.index
  block_menus:
    - title: crm_nav_overview
      navigations:
        - { title: crm_nav_customers, route: crm.customers, heroicon: users }
```