# Noerd Framework Documentation

Noerd is a Laravel-based admin framework built on Livewire and Volt. It provides a multi-tenant architecture with YAML-based configuration for list and detail views.

## Version

**v1.0.1**

## Table of Contents

- [Installation](installation.md) - Install and set up the framework
- [Architecture](architecture.md) - Understand structure and core concepts
- [Components](components.md) - Use Blade and Livewire components
- [YAML Configuration](yaml-configuration.md) - Configure lists, details, and navigation
- [Creating Modules](creating-modules.md) - Develop your own submodules
- [Artisan Commands](artisan-commands.md) - Available CLI commands

## Core Features

- **Multi-Tenant Architecture**: Support for multiple tenants with their own apps
- **YAML-based UI**: Lists and forms are defined through YAML files
- **Livewire Volt Integration**: Single-file components with PHP and Blade
- **Blade Component Library**: Over 90 reusable UI components
- **Modular System**: Independent modules with their own routes, views, and models
- **Role-based Access Control**: Users, profiles, and permissions

## Quick Start

```bash
# 1. Add package as dependency
composer require noerd/noerd

# 2. Run migrations
php artisan migrate

# 3. Install framework
php artisan noerd:install
```

## Directory Structure

```
app-modules/noerd/
├── app-contents/setup/     # Default YAML configurations
├── database/               # Migrations, factories, seeders
├── docs/                   # This documentation
├── public/                 # Fonts and assets
├── resources/
│   ├── views/
│   │   ├── components/     # Blade components
│   │   └── livewire/       # Volt components
│   └── lang/               # Translations (de.json, en.json)
├── routes/                 # Route definitions
├── src/
│   ├── Commands/           # Artisan commands
│   ├── Helpers/            # StaticConfigHelper
│   ├── Middleware/         # SetupMiddleware, SetUserLocale
│   ├── Models/             # User, Tenant, TenantApp, etc.
│   ├── Providers/          # NoerdServiceProvider
│   ├── Services/           # NavigationService
│   └── Traits/             # Noerd, HasModuleInstallation
└── tests/                  # Pest tests
```

## Dependencies

```json
{
  "laravel/framework": "^11.0|^12.0",
  "livewire/livewire": "^3.4",
  "livewire/volt": "^v1.6.7",
  "composer/composer": "^2.9",
  "wireui/heroicons": "^2.9"
}
```

## Support

For questions or issues, please contact the development team.
