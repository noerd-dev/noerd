# Artisan Commands

The Noerd Framework provides several Artisan commands.

## Overview

| Command | Description |
|---------|-------------|
| `noerd:install` | Install framework |
| `noerd:create-admin` | Create admin user |
| `noerd:make-admin` | Make user admin |
| `noerd:create-tenant` | Create new tenant |
| `noerd:create-app` | Create new TenantApp |
| `noerd:assign-apps-to-tenant` | Assign apps to tenant |
| `noerd:make-module` | Generate new module |
| `noerd:make-collection` | Create setup collection |

## noerd:install

Installs the Noerd Framework and performs basic configuration.

```bash
php artisan noerd:install
```

### Actions

- Checks if migrations have been run
- Creates default setup configurations
- Sets up basic data structures

## noerd:create-admin

Creates a new administrator user.

```bash
php artisan noerd:create-admin
```

### Interactive Prompts

1. Email address
2. Name
3. Password

### Example

```bash
$ php artisan noerd:create-admin

 What is the admin's email?
 > admin@example.com

 What is the admin's name?
 > John Doe

 What is the admin's password?
 > ********

Admin user created successfully!
```

## noerd:make-admin

Makes an existing user an administrator.

```bash
php artisan noerd:make-admin {userId}
```

### Parameters

| Parameter | Description |
|-----------|-------------|
| `userId` | User ID |

### Example

```bash
php artisan noerd:make-admin 1
```

## noerd:create-tenant

Creates a new tenant.

```bash
php artisan noerd:create-tenant
```

### Interactive Prompts

1. Tenant name
2. Assign users (optional)

### Example

```bash
$ php artisan noerd:create-tenant

 What is the tenant's name?
 > Company Inc.

 Assign users to tenant? (yes/no)
 > yes

 Select users to assign:
 > [0] admin@example.com
 > [1] user@example.com
 > 0,1

Tenant created successfully!
```

## noerd:create-app

Creates a new TenantApp.

```bash
php artisan noerd:create-app
```

### Interactive Prompts

1. App name (technical)
2. App title (display)
3. Start route
4. Icon (Heroicon)

### Example

```bash
$ php artisan noerd:create-app

 What is the app's name (technical)?
 > CRM

 What is the app's title (display)?
 > Customer Management

 What is the app's start route?
 > crm.index

 What icon should be used? (Heroicon name)
 > users

App created successfully!
```

## noerd:assign-apps-to-tenant

Assigns apps to a tenant.

```bash
php artisan noerd:assign-apps-to-tenant
```

### Interactive Prompts

1. Select tenant
2. Select apps

### Example

```bash
$ php artisan noerd:assign-apps-to-tenant

 Select tenant:
 > [0] Company Inc.
 > [1] Demo Tenant
 > 0

 Select apps to assign:
 > [0] CMS
 > [1] CRM
 > [2] Setup
 > 0,2

Apps assigned successfully!
```

## noerd:make-module

Generates the basic structure for a new module.

```bash
php artisan noerd:make-module
```

### Interactive Prompts

1. Module name
2. Namespace (Noerd/Nywerk)
3. Description

### Generated Structure

```
app-modules/{module-name}/
├── src/
│   └── Providers/{ModuleName}ServiceProvider.php
├── resources/
│   ├── views/livewire/
│   └── lang/
├── routes/{module-name}-routes.php
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── tests/
├── composer.json
└── README.md
```

## noerd:make-collection

Creates a new setup collection.

```bash
php artisan noerd:make-collection
```

### Interactive Prompts

1. Collection name
2. Define fields

### Generated Files

- YAML configuration for collection
- List and detail views

## General Options

All commands support the following Laravel standard options:

| Option | Description |
|--------|-------------|
| `--help` | Show help |
| `--quiet` | No output |
| `--verbose` | Verbose output |
| `--no-interaction` | No interactive prompts |

### Example with Options

```bash
php artisan noerd:install --no-interaction
```

## Commands from Other Modules

Modules can include their own commands:

### CMS Module

```bash
php artisan noerd:cms-install       # Install CMS
php artisan cms:sync-form-types     # Sync form types
```

### Accounting Module

```bash
php artisan accounting:install      # Install accounting
```

## Best Practices

### Automated Installation

For automated deployments:

```bash
php artisan migrate --no-interaction
php artisan noerd:install --no-interaction
```

### Development Environment

After cloning the repository:

```bash
composer install
php artisan migrate
php artisan noerd:install
php artisan noerd:create-admin
php artisan noerd:create-tenant
php artisan noerd:create-app
php artisan noerd:assign-apps-to-tenant
```

## Troubleshooting

### "Command not found"

Make sure the Noerd package is installed:

```bash
composer require noerd/noerd
```

### Permission Issues

Clear cache:

```bash
php artisan cache:clear
php artisan config:clear
```

### Missing Migrations

Run migrations manually:

```bash
php artisan migrate
```

## Next Steps

- [Installation](installation.md) - Complete installation guide
- [Creating Modules](creating-modules.md) - Develop your own modules
