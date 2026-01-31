# Artisan Commands

The Noerd Framework provides several Artisan commands.

## Overview

### Core Commands

| Command | Description |
|---------|-------------|
| `noerd:install` | Install framework |
| `noerd:update` | Update noerd content files without running installation setup |
| `noerd:create-admin` | Create admin user |
| `noerd:make-admin` | Make user admin |
| `noerd:create-tenant` | Create new tenant |
| `noerd:create-app` | Create new TenantApp |
| `noerd:assign-apps-to-tenant` | Assign apps to tenant |
| `noerd:module` | Create new module with complete structure |
| `noerd:make-collection` | Create setup collection |

## noerd:install

Installs the Noerd Framework and performs basic configuration.

```bash
php artisan noerd:install
```

## noerd:update

Updates noerd content files without running the full installation setup.

```bash
php artisan noerd:update
```

## noerd:create-admin

Creates a new administrator user.

```bash
php artisan noerd:create-admin
```

## noerd:make-admin

Makes an existing user an administrator.

```bash
php artisan noerd:make-admin {userId}
```

## noerd:create-tenant

Creates a new tenant.

```bash
php artisan noerd:create-tenant
```


## noerd:create-app

Creates a new TenantApp.

```bash
php artisan noerd:create-app
```

## noerd:assign-apps-to-tenant

Assigns apps to a tenant.

```bash
php artisan noerd:assign-apps-to-tenant
```

## noerd:module

Creates a new module with complete directory structure, including model, migration, Livewire components, YAML configurations, and translations.

```bash
php artisan noerd:module
# or with module name
php artisan noerd:module inventory
```

## noerd:make-collection

Creates a new setup collection.

```bash
php artisan noerd:make-collection
```