# Installation

This guide describes the installation and initial setup of the Noerd Framework.

## Prerequisites

- PHP >= 8.4
- Laravel >= 12.0

## 1. Install Package



### Add Package

```bash
composer require noerd/noerd
```

The ServiceProvider is automatically registered through Laravel Package Discovery.


## 2. Install Framework

The install command sets up basic configurations:

```bash
php artisan noerd:install
```
During installation, you should create a default tenant and an admin user. If you skip those steps, you can also do it manually with an Artisan command later.
### Created Tables

| Table | Description                               |
|-------|-------------------------------------------|
| `users` | User accounts (if not already exists)     |
| `user_settings` | User settings (language, selected tenant) |
| `user_roles` | User roles per tenant                     |
| `tenants` | Tenants/Organizations/Environments        |
| `tenant_apps` | Available apps (mostly app-modules)       |
| `profiles` | Role profiles (ADMIN, USER, etc.)         |
| `setup_collections` | Dynamic data collections                  |
| `setup_collection_entries` | Entries in collections                    |


## 3. Verification
You should now have access to /noerd-home with your created user.
