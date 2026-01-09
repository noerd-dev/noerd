# Installation

This guide describes the installation and initial setup of the Noerd Framework.

## Prerequisites

- PHP >= 8.4
- Laravel >= 11.0 or >= 12.0
- Composer >= 2.9
- MySQL/MariaDB database
- Node.js and npm (for frontend assets)

## 1. Install Package

The Noerd package is included as a local Composer package via path repository.

### Configure composer.json

Add the repository to your main application's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "app-modules/*",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

### Add Package

```bash
composer require noerd/noerd
```

The ServiceProvider is automatically registered through Laravel Package Discovery.

## 2. Run Migrations

Execute the migrations to create the necessary tables:

```bash
php artisan migrate
```

### Created Tables

| Table | Description |
|-------|-------------|
| `users` | User accounts |
| `user_settings` | User settings (language, selected tenant) |
| `user_roles` | User roles per tenant |
| `tenants` | Tenants/Organizations |
| `tenant_apps` | Available apps |
| `profiles` | Role profiles (ADMIN, USER, etc.) |
| `setup_collections` | Dynamic data collections |
| `setup_collection_entries` | Entries in collections |

## 3. Install Framework

The install command sets up basic configurations:

```bash
php artisan noerd:install
```

## 4. Publish Assets

Publish the font assets:

```bash
php artisan vendor:publish --tag=noerd-assets
```

Fonts are automatically copied to `public/vendor/noerd/fonts` if they don't exist yet.

## 5. Create Admin User

Create an administrator user:

```bash
php artisan noerd:create-admin
```

The command will ask for:
- Email address
- Name
- Password

### Make Existing User Admin

```bash
php artisan noerd:make-admin {userId}
```

## 6. Create Tenant

Create a tenant:

```bash
php artisan noerd:create-tenant
```

The command will ask for:
- Tenant name
- User assignment

## 7. Create and Assign App

### Create New App

```bash
php artisan noerd:create-app
```

### Assign Apps to Tenant

```bash
php artisan noerd:assign-apps-to-tenant
```

## Configuration

### Livewire Layout

The framework automatically sets the Livewire layout:

```php
config(['livewire.layout' => 'noerd::components.layouts.app']);
```

### Middleware

The following middleware is registered:

- `setup` - Checks if setup is complete
- `SetUserLocale` - Sets user language (automatically added to web group)

### Routes

Default routes are registered at the following paths:

| Route | View | Description |
|-------|------|-------------|
| `/setup` | `setup.users-list` | Setup area |
| `/users` | `setup.users-list` | User management |
| `/user-roles` | `setup.user-roles-list` | Role management |
| `/login` | `auth.login` | Login |
| `/dashboard` | `DashboardController` | Dashboard |

## Verification

After installation you should:

1. Open the application in your browser
2. Log in with the admin user
3. Be able to access the setup at `/setup`

## Troubleshooting

### Migrations Fail

Check if the database connection is correctly configured:

```bash
php artisan config:clear
php artisan migrate:fresh
```

### Views Not Found

Clear cache:

```bash
php artisan view:clear
php artisan cache:clear
```

### Livewire Components Don't Load

Make sure Livewire is correctly installed:

```bash
composer require livewire/livewire
php artisan livewire:publish --config
```

## Next Steps

- [Architecture](architecture.md) - Understand the framework structure
- [Creating Modules](creating-modules.md) - Develop your own modules
