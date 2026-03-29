# noerd/noerd

[![Total Downloads](https://img.shields.io/packagist/dt/noerd/noerd.svg)](https://packagist.org/packages/noerd/noerd)
[![Latest Stable Version](https://img.shields.io/packagist/v/noerd/noerd.svg)](https://packagist.org/packages/noerd/noerd)

Noerd is a Laravel Livewire 4 package for building admin panels efficiently. It provides pre-built list and detail
views that can be configured entirely through YAML files, eliminating the need for repetitive CRUD code.

![Noerd](https://noerd.dev/assets/Noerd.gif)

For full documentation, visit [noerd.dev](https://noerd.dev).

## Key Features

- **Business Apps** – Build self-contained apps like Accounting, CMS, Booking or Production Planning and assign them individually to tenants
- **List Views** – Display data in configurable tables with minimal setup
- **Detail Views** – Render individual records with flexible field layouts
- **YAML Configuration** – Define columns, fields, and behavior through configuration files instead of PHP code
- **Multi-Tenant Architecture** – Support for multiple tenants with app-based access control

## Demo
You can access a demo here. The demo has assigned two apps, a Content-Management-System and a Study-App.

https://demo.noerd.dev

## Installation

```bash
composer require noerd/noerd
php artisan noerd:install
```

The installation wizard will guide you through creating an admin user and an initial tenant.

### Recommended Configuration

If you don't want to configure `$guarded` on every model individually, you can globally unguard all models in your `AppServiceProvider`:

```php
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    Model::unguard();
}
```

This allows mass assignment on all attributes for every model.

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

### Creating a Resource

Generate list and detail Blade views along with their YAML configuration files from an existing Eloquent model:

```bash
php artisan noerd:make-resource "App\Models\Post"
```

For more details on resources and all available configuration options, see the [documentation](https://noerd.dev).

## Auto installed packages

### Composer

- `wireui/heroicons` — Heroicon SVG components
- `pestphp/pest` (dev) — Testing framework

### NPM

- `@tiptap/core` — Rich text editor core

## Installation as Submodule to contribute

If you want to contribute to the development of Noerd, you can install it as a git submodule:

```bash
git submodule add git@github.com:noerd-dev/noerd.git app-modules/noerd
```

Then add the package manually to the `require` section of your `composer.json`:

```json
"noerd/noerd": "*"
```

Then run:

```bash
composer update noerd/noerd
php artisan noerd:install
```

This way, you can make changes directly in `app-modules/noerd` and push them back to the Noerd repository.
