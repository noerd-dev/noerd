# noerd/noerd

[![Total Downloads](https://img.shields.io/packagist/dt/noerd/noerd.svg)](https://packagist.org/packages/noerd/noerd)
[![Latest Stable Version](https://img.shields.io/packagist/v/noerd/noerd.svg)](https://packagist.org/packages/noerd/noerd)

**Build admin panels and business apps for Laravel — without touching your production code.**
Zero intrusion: noerd brings its own auth guard, routes and tables, and never modifies your
`config/auth.php` or `.env`. Screens are slim Livewire components driven by YAML configs.

![Noerd](https://noerd.dev/assets/Noerd.gif)

## Quickstart

```bash
# 1. Install the package and run the wizard
composer require noerd/noerd
php artisan noerd:install

# 2. Create your first app
php artisan noerd:create-app

# 3. Create a model and its migration, then migrate
php artisan make:model Customer -m
php artisan migrate

# 4. Generate the list and detail screens for the model
php artisan noerd:make-resource Customer
```

The model needs a `tenant_id` column (`$table->foreignId('tenant_id')->constrained('tenants')`)
and the `Noerd\Traits\BelongsToTenant` trait — that is all the tenant scoping there is:

```php
use Noerd\Traits\BelongsToTenant;

class Customer extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
}
```

- `noerd:install` publishes the config and the frontend scaffold (Vite, Tailwind CSS 4), runs the
  migrations, creates the default tenant and an admin user, and optionally installs demo data.
- `noerd:create-app` asks where the app lives (project or an `app-modules/` package), for a
  title, a name and an icon, scaffolds a dashboard for the app and offers to assign it to your
  tenants. Use `noerd:assign-apps-to-tenant` to change that later.
- `noerd:make-resource` reads the model's columns and generates the list and detail components,
  their YAML configs, the routes (behind `['noerd', 'app-access:{app}']`) and the navigation entry.

Log in, open the app from the app bar, and your first CRUD screen is ready.

**Tip:** if you don't want to configure `$guarded` on every model, unguard globally in your
`AppServiceProvider`:

```php
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    Model::unguard();
}
```

## Key Features

- **Business Apps** – Build self-contained apps (sales, purchasing, HR, …), each with its own dashboard and navigation, and assign them per tenant
- **List Views** – Sortable, searchable, paginated tables with Excel-style column filters, bulk actions and a card grid mode — configured in a single YAML file
- **Detail Views & Pages** – Tabbed forms with validation, embedded related lists, a relation overview and selectable form themes
- **Smart Field Types** – Text, date, file, image, rich text, **relations** and dynamic **picklists** — extendable with your own field types
- **Setup Collections** – Manage lookup tables (categories, countries, templates) via YAML — no migrations or models required
- **Hierarchical Navigation** – Nested menu groups with Heroicons, defined in YAML
- **Multi-Tenant Architecture** – Complete data isolation with per-tenant app assignment
- **Permissions** – Profiles per tenant, app and object gates, named actions
- **Multi-Language** – Translatable admin panel with built-in language management
- **UI Building Blocks** – Modals, dashboard widgets, header actions, quick menu, banners and keyboard shortcuts
- **AI-Ready** – Ships a Laravel Boost guideline and skills so AI assistants build on noerd the right way

## Documentation

The full documentation lives at **[noerd.dev/docs](https://noerd.dev/docs/)** — installation,
apps and modules, lists, forms, field types, modals, themes, permissions, Artisan commands and
testing.

## Demo

A hosted demo is available at https://demo.noerd.dev. Locally, `php artisan noerd:demo` installs
the Demo Customers app into your project (see the Example Application page of the docs).

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Livewire 4+
- Node.js `^20.19 || >=22.12` and npm (for the frontend build)

## YAML in Action

A full CRUD screen is two YAML files plus two slim components that `noerd:make-resource`
generates for you (the list declares its model and detail route, the detail its model — nothing
else).

**`app-configs/demo/lists/customers-list.yml`**

```yaml
title: Customers
actions:
  - label: New Customer
    route: demo.customer.detail
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

## Need Help?

If you spot a bug or something does not work as documented, please
[open an issue](https://github.com/noerd-dev/noerd/issues) with the steps to reproduce it, the
noerd version and the Laravel version — we are happy to help.

## Contributing

To work on noerd itself, install it as a git submodule of your project:

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

Changes you make in `app-modules/noerd` are picked up directly. Open a pull request against `main`
— the contributor workflow is described in `AGENTS.md`.

## License

noerd is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
