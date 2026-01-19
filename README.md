# noerd/noerd

Noerd is a Laravel Livewire boilerplate for building admin panels efficiently. It provides pre-built list and detail
views that can be configured entirely through YAML files, eliminating the need for repetitive CRUD code.

## Key Features

- **List Views** – Display data in configurable tables with minimal setup
- **Detail Views** – Render individual records with flexible field layouts
- **YAML Configuration** – Define columns, fields, and behavior through configuration files instead of PHP code
- **Multi-Tenant Architecture** – Support for multiple tenants with app-based access control
- **Built on Laravel & Livewire** – Leverages the full power of Laravel's ecosystem with reactive Livewire components

## Demo
You can access a demo here. The demo has assigned two apps, a Content-Management-System and a Study-App.
TODO

## Installation

```bash
composer require noerd/noerd
php artisan noerd:install
```

The installation wizard will guide you through creating an admin user and an initial tenant.

## Usage

Noerd is designed around the concept of apps, where each app has its own navigation defined in a YAML file.

### Creating an App

```bash
php artisan noerd:create-app
```

### Assigning Apps to Tenants

```bash
php artisan noerd:assign-apps-to-tenant
```

