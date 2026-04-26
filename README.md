# noerd/noerd

[![Total Downloads](https://img.shields.io/packagist/dt/noerd/noerd.svg)](https://packagist.org/packages/noerd/noerd)
[![Latest Stable Version](https://img.shields.io/packagist/v/noerd/noerd.svg)](https://packagist.org/packages/noerd/noerd)

**Build admin panels and business apps for Laravel — without touching your production code.**
Zero intrusion: no traits, no base classes, no boilerplate. Just YAML configs.

![Noerd](https://noerd.dev/assets/Noerd.gif)

For full documentation, visit [noerd.dev](https://noerd.dev).

## Key Features

- **Business Apps** – Build self-contained apps (Accounting, CMS, Booking, Production Planning, …) and assign them per tenant or per user
- **List Views** – Sortable, searchable, paginated tables — configured in a single YAML file ([list view](docs/list-view.md), [list filters](docs/list-filters.md), [list search](docs/list-search.md))
- **Detail Views** – Tabbed forms with embedded related lists, built-in validation, and dynamic field layouts ([detail view](docs/detail-view.md))
- **Smart Field Types** – Text, date, file, image, rich text, **relations**, and dynamic **picklists** ([field types](docs/field-types.md), [relation fields](docs/relation-field-types.md))
- **Setup Collections** – Manage lookup tables (categories, countries, templates) via YAML — no migrations or models required ([setup collections](docs/setup-collections.md))
- **Hierarchical Navigation** – Nested menu groups with Heroicons, defined in YAML ([navigation](docs/navigation.md))
- **Multi-Tenant Architecture** – Complete data isolation with per-tenant app assignment
- **Multi-Language** – Translation management baked in
- **UI Building Blocks** – Reusable [modal](docs/modal.md), [banner](docs/banner.md), and [quick menu](docs/quick-menu.md) components

## Demo

A hosted demo with two pre-installed apps (a Content Management System and a Study App):

https://demo.noerd.dev

## Requirements

- PHP 8.4+
- Laravel 12+
- Livewire 4+

## Quickstart

```bash
# 1. Install the package
composer require noerd/noerd
php artisan noerd:install

# 2. Create a model and migration
php artisan make:model Customer -m

# 3. Generate list, detail, YAML configs, navigation entry, and routes
php artisan noerd:make-resource Customer
```

The installation wizard guides you through creating an admin user and an initial tenant.

### Recommended Configuration

If you don't want to configure `$guarded` on every model individually, unguard globally in your `AppServiceProvider`:

```php
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    Model::unguard();
}
```

## YAML in Action

A full CRUD screen is two YAML files. No PHP, no Blade.

**`app-configs/demo/lists/customers-list.yml`**

```yaml
title: Customers
actions:
  - label: New Customer
    action: listAction
columns:
  - field: name
    label: Name
  - field: company_name
    label: Company
  - field: email
    label: Email
  - field: city
    label: City
```

**`app-configs/demo/details/customer-detail.yml`**

```yaml
title: Customer
fields:
  - name: detailData.name
    label: Name
    type: text
    colspan: 6
    required: true
  - name: detailData.email
    label: Email
    type: email
    colspan: 6
  - name: detailData.phone
    label: Phone
    type: text
    colspan: 6
```

## Apps & Navigation

Noerd is built around **apps** — each with its own navigation YAML and assignable per tenant.

```bash
# Create a new app
php artisan noerd:create-app

# Assign apps to tenants
php artisan noerd:assign-apps-to-tenant
```

See [creating apps](docs/create-app.md) and [all artisan commands](docs/artisan-commands.md).

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

Then add a path repository and the package to your `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "app-modules/noerd",
        "options": {
            "symlink": true
        }
    }
],
"require": {
    "noerd/noerd": "*"
}
```

Then run:

```bash
composer update noerd/noerd
php artisan noerd:install
```

This way, you can make changes directly in `app-modules/noerd` and push them back to the Noerd repository.
