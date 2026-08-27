# Installation

## Noerd Package

noerd is a Laravel Livewire boilerplate for building admin panels and business applications. It provides a solid foundation with multi-tenancy, declarative configuration, and a library of ready-to-use components.

![Noerd Example App](/assets/app1.png "Title")

### Core Features
- **Multi-Tenancy** — Built on a flexible multi-tenant architecture with complete data isolation. Users can belong to multiple tenants, manage environments like development, staging, and production, and handle multiple clients or enterprise groups from a single installation.
- **YAML-Based Configuration** — Define lists, detail views, forms, and navigation through simple YAML files. Customize your instance without touching code—just configure tables, detail-views, and navigations to fit your needs.
- **Multi-Language Admin Panel** — A fully translatable interface with built-in language management.
- **App Management** — Create custom business apps for purchasing, sales, or any department. Alternatively, use ready-made apps like Booking or CMS to get started quickly.
- **AI-Powered Development** — An AI-ready boilerplate designed for rapidly building apps and tools. The YAML-driven architecture enables AI assistants to generate and modify components efficiently.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Livewire 4+
- Node.js `^20.19 || >=22.12` and npm (for the frontend build)

## Install Noerd

```bash
composer require noerd/noerd
php artisan noerd:install
```

The install command can be run in a fresh or an existing Laravel application. During installation, you will be guided through the following steps:

1. **Create a default tenant** — Your first organization or environment.
2. **Create an admin user** — Or assign an existing user as admin.
3. **Install demo data (recommended)** — Sets up a fully working Demo Customers app with model, migration, YAML configuration, and navigation — so you can explore Noerd's features right away.

If you skip any of these steps, you can run them later with the respective [Artisan commands](artisan-commands.md).

### Created Tables

| Table | Description |
|-------|-------------|
| `noerd_users` | User accounts |
| `noerd_user_settings` | User settings (language, selected tenant) |
| `noerd_profiles` | Access profiles (ADMIN, USER, etc.). One user has one profile per tenant |
| `noerd_settings` | Per-tenant system settings (currency, detail theme) |
| `tenants` | Tenants / Organizations / Environments |
| `users_tenants` | User ↔ tenant assignments |
| `tenant_apps` | Available apps which can be assigned to tenants (with `tenant_app` pivot) |
| `tenant_invoices` | Tenant invoices |
| `setup_collections` | Dynamic data collections |
| `setup_collection_entries` | Entries in collections |
| `setup_collection_definitions` | Collection schemas when `noerd.collections.mode` is `database` |
| `setup_languages` | Per-tenant admin-panel languages |

### Authentication

noerd ships its own auth stack: at runtime it registers a dedicated `noerd` guard, a `noerd_users`
provider (backed by `Noerd\Models\NoerdUser`) and a matching password broker. Your application's
`config/auth.php` and `.env` are **never modified** — noerd coexists with any existing auth setup
(Laravel Nova, Breeze, a custom guard, ...). See [Authentication](auth.md) for details, overrides
and the coexistence recipe.

## Frontend

noerd's layouts render `@vite(['resources/css/app.css', 'resources/js/app.js'])`, so the host
application needs a Vite/Tailwind scaffold. A project generated from a Laravel starter kit already
has one; an API-only application does not. `noerd:install` (and `noerd:update`) therefore **creates
whatever is missing and patches whatever exists** — it never overwrites a file you own:

| File | Missing | Present |
|------|---------|---------|
| `package.json` | Created with `dev`/`build` scripts and the build tooling in `devDependencies` | Only the missing scripts and dependencies are added; existing version ranges are never changed, and a package already declared under `dependencies` is not duplicated |
| `vite.config.js` | Created with `laravel-vite-plugin` (both entry points, `refresh: true`) and `@tailwindcss/vite` | **Never rewritten.** The installer only warns when the noerd entry points or the Tailwind plugin are missing from your config |
| `resources/css/app.css` | Created with the Tailwind import, the noerd theme import, `@plugin '@tailwindcss/forms'` and the `@source` paths | The same directives are injected individually, so re-running adds no duplicates (quote style and spacing are ignored) |
| `resources/js/app.js` | Created as an empty entry module | Never touched |

Nothing has to be imported in `resources/js/app.js`: Livewire ships its own runtime (including
Alpine) and noerd loads its compiled bundle through `<x-noerd::assets />`. Put your project's own
CSS in `resources/css/app.css` below the injected directives.

The installer pins **vite `^8`** with **laravel-vite-plugin `^3`**, or falls back to vite `^7` /
laravel-vite-plugin `^2` when the installed Node version is older than `^20.19 || >=22.12`. A
summary table lists every file as `created`, `patched`, `skipped` or `warning`.

Afterwards, build the assets:

```bash
npm install
npm run build     # or: npm run dev
```

> Installations created before the brand palette became CSS-first still carry a generated
> `tailwind.config.js` and a `@config` line in `app.css`. `noerd:update` offers to remove both (a
> `tailwind.config.js.bak` is kept); a config you customised yourself is only reported, never
> touched. See [Brand](brand.md).

## Configuration

`noerd:install` publishes `config/noerd.php`. Notable flags:

- `features.multi_tenant` (`NOERD_MULTI_TENANT`) — tenant switcher and multi-tenant UI. Enabled
  by default; `noerd:install` does not write this flag to `.env` — set `NOERD_MULTI_TENANT=false`
  yourself to disable it
- `features.currency` (`NOERD_CURRENCY_ENABLED`) — set to `false` to hide currency-related UI on
  installations that don't need it
- `auth.guard` (`NOERD_AUTH_GUARD`) / `auth.set_as_default` (`NOERD_AUTH_DEFAULT`) — noerd's
  dedicated auth guard (see [Authentication](auth.md))
- `theme.default` / `theme.enforced` — system-wide form theme (see [Themes](themes.md))
- `brand.active` — color palette (see [Brand](brand.md))

## Verification

You should now have access to `/noerd-apps` with your created user. If you installed the demo data, you will see a working Demo Customers app with a list and detail view — ready to explore and use as a reference for building your own apps.

## Next Steps

Continue with [Create an App](create-app.md) to create your first own app.