# Installation

noerd is a Laravel Livewire package for building multi-tenant admin panels and business
applications from YAML-configured lists, details and navigation.

![Noerd Example App](/assets/app1.png "Title")

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

The install command can be run in a fresh or an existing Laravel application. It asks, in this order:

1. **Run the migrations** — declining ends the setup steps 2 and 3 (they need the tables); run
   `php artisan migrate` yourself later.
2. **Create a default tenant** — your first organization or environment. Skipped when a tenant exists.
3. **Create an admin user** — the first user of the installation, a super admin. When users already
   exist the step is skipped; promote one with `php artisan noerd:promote-admin {user_id}`.
4. **Run `npm run build`** — compiles the frontend assets (see [Frontend](#frontend)).
5. **Install the Demo App (recommended)** — a working Demo Customers app with model, migration, YAML
   configuration and navigation (see [Example Application](example-application.md)).

Every skipped step can be run later with the respective [Artisan command](artisan-commands.md). The
closing box names the next step: `php artisan dev` starts the local development processes.

Besides the interactive steps, `noerd:install` publishes the setup app configs to `app-configs/setup/`,
publishes `config/noerd.php`, sets `component_layout` in `config/livewire.php` to `noerd::layouts.app`
(publishing the Livewire config first when needed), scaffolds the frontend (see below) and publishes
the public assets to `public/vendor/noerd` (`vendor:publish --tag=noerd-assets`). What only a project
with LOCAL modules needs — the `app-modules/` directory, its Composer path repository and the
`app-modules` test suite in `phpunit.xml` — is set up by `noerd:make-module` with the first module. Non-interactive
runs (CI) pass `--force --migrate --build --demo` explicitly — see
[noerd:install](artisan-commands.md#noerdinstall).

### Created Tables

| Table | Description |
|-------|-------------|
| `noerd_users` | User accounts |
| `noerd_user_settings` | User settings (language, `format_locale`, selected tenant) |
| `noerd_settings` | Per-tenant system settings (currency, `locale`, detail theme) |
| `tenants` | Tenants / Organizations / Environments |
| `users_tenants` | User ↔ tenant assignments, incl. the user's `profile_key` for that tenant |
| `tenant_apps` | Available apps which can be assigned to tenants (with `tenant_app` pivot) |
| `setup_collections` | Dynamic data collections |
| `setup_collection_entries` | Entries in collections |
| `setup_collection_definitions` | Collection schemas when `noerd.collections.mode` is `database` |
| `setup_languages` | Per-tenant admin-panel languages |
| `noerd_logins` | Login history (IP, user agent, impersonating admin) recorded on every login |

Password resets use Laravel's default `password_reset_tokens` table, which the standard
`0001_01_01_000000_create_users_table` migration of every Laravel application creates — noerd does
not ship it.

### Routes

All core routes are named with the `noerd.` prefix. The `/setup` area (middleware `noerd` +
`setup`, admins only) registers `noerd.setup`, `noerd.users`, `noerd.user.detail`, `noerd.tenants`,
`noerd.tenant.detail`, `noerd.create-tenant`, `noerd.tenant-apps`, `noerd.setup-collections`,
`noerd.setup-collection.detail`, `noerd.setup-collection-definitions`,
`noerd.setup-collection-definition.detail`, `noerd.setup-languages`, `noerd.setup-language.detail`
and `noerd.system-settings`. The apps dashboard is `noerd.apps` (`/noerd-apps`); under the
configurable URL prefix live `noerd.profile`, `noerd.no-tenant`, `noerd.component-page`,
`noerd.home` (`/noerd/home`, a redirect to the apps dashboard) and the
auth routes (`noerd.login`, `noerd.password.request`, `noerd.password.reset`). See
[Authentication](auth.md#routes--url-prefix).

### Authentication

noerd registers its own `noerd` guard at runtime and never modifies `config/auth.php` or `.env`, so
it coexists with any existing auth setup — see [Authentication](auth.md).

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

The installer pins **vite `^8`** with **laravel-vite-plugin `^3`**, which need Node
`^20.19 || >=22.12` (see the requirements above). A summary table lists every file as `created`, `patched`, `skipped` or `warning`.

Afterwards, build the assets:

```bash
npm install
npm run build     # or: npm run dev
```

The injected `app.css` directives reference the package by its Composer path
(`@import '../../vendor/noerd/noerd/resources/css/noerd.css';` and matching `@source` lines) — an
installation that loads noerd from another location (e.g. a path repository) must point them there
itself. The brand palette is CSS-first, no `tailwind.config.js` is needed (see [Brand](brand.md)).

## Configuration

`noerd:install` publishes `config/noerd.php`. Notable flags:

- `features.multi_tenant` (`NOERD_MULTI_TENANT`) — tenant switcher and multi-tenant UI. Enabled
  by default; `noerd:install` does not write this flag to `.env` — set `NOERD_MULTI_TENANT=false`
  yourself to disable it
- `features.new_tenant` (`NOERD_NEW_TENANT_FEATURE_ENABLED`) — lets admins create further tenants
  from the tenant switcher / quick menu
- `features.currency` (`NOERD_CURRENCY_ENABLED`) — set to `false` to hide currency-related UI on
  installations that don't need it
- `currency.default` (`NOERD_CURRENCY`, default `EUR`) — the installation-wide default currency;
  a tenant overrides it in Setup → System Settings
- `format.locale` (`NOERD_FORMAT_LOCALE`) — the installation-wide fallback locale for numbers,
  dates and amounts (user locale → tenant locale → this → interface language), plus the optional
  pins `date`, `datetime`, `decimal_separator`, `thousands_separator` (`NOERD_FORMAT_*`) and
  `csv_delimiter` (`NOERD_CSV_DELIMITER`, default `;`) — see
  [Currency, Numbers & Dates](formatting.md)
- `theme.default` / `theme.enforced` — system-wide form theme (see [Themes](themes.md))
- `brand.active` — color palette (see [Brand](brand.md))

If [Laravel Boost](ai-agents.md) is installed, `noerd:install` and `noerd:update` also register
`noerd/noerd` in `boost.json` and render the framework rules into your agent files.

## Verification

You should now have access to `/noerd-apps` with your created user. If you installed the demo data, you will see a working Demo Customers app with a list and detail view — ready to explore and use as a reference for building your own apps.

## Next Steps

Continue with [Create an App](make-app.md) to create your first own app.