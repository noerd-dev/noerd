# Installation

## Noerd Package

noerd is a Laravel Livewire boilerplate for building admin panels and business applications. It provides a solid foundation with multi-tenancy, declarative configuration, and a library of ready-to-use components.

![Noerd Example App](/assets/app1.png "Title")

### Core Features
- **Multi-Tenancy** — Built on a flexible multi-tenant architecture with complete data isolation. Users can belong to multiple tenants, manage environments like development, staging, and production, and handle multiple clients or enterprise groups from a single installation.
- **YAML-Based Configuration** — Define lists, detail views, forms, and navigation through simple YAML files. Customize your instance without touching code—just configure tables, detail-views, and navigations to fit your needs.
- **Multi-Language Admin Panel** — A fully translatable interface with built-in language management.
- **App Management** — Create custom business apps for purchasing, sales, or any department. Alternatively, use ready-made apps like Booking or CMS to get started quickly.

## Install Noerd

```bash
composer require noerd/noerd
php artisan noerd:install
```

The install command can be run in a fresh or an existing Laravel application.

During installation, you should create a default tenant and an admin user. If you skip those steps, you can also do it manually with an [Artisan command](artisan-commands.md) later.

### Created Tables

| Table | Description                                                |
|-------|------------------------------------------------------------|
| `users` | User accounts (if not already exists)                      |
| `user_settings` | User settings (language, selected tenant)                  |
| `user_roles` | User roles per tenant. One User can have many roles        |
| `tenants` | Tenants / Organizations / Environments                     |
| `tenant_apps` | Available apps which can be assigned to tenants            |
| `profiles` | Role profiles (ADMIN, USER, etc.) One User has one profile |
| `setup_collections` | Dynamic data collections                                   |
| `setup_collection_entries` | Entries in collections                                     |


## Verification

You should now have access to /noerd-home with your created user. Currently, it's an empty page until we create our first app.

## Next Steps

Continue with [Create an App](create-app.md) to create your first app.