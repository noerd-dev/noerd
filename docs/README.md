# Noerd Framework Documentation

Documentation for the Noerd framework — a YAML-driven modular framework for Laravel applications.

> **Note:** The mirror at [noerd-dev/docs](https://github.com/noerd-dev/docs) is auto-synced from the `docs/` folder of [noerd-dev/noerd](https://github.com/noerd-dev/noerd) on every push to `main`. Do not commit to the mirror directly — changes there will be overwritten. Edit the docs in the noerd repository instead.

## Contents

### Getting started

- [Installation](installation.md) — requirements, `noerd:install`, created tables, routes and the frontend scaffold
- [Example Application](example-application.md) — the Demo Customers app installed by `noerd:demo`, as a reference
- [Creating Apps](make-app.md) — register a tenant app with its own dashboard, in the project or as a module (`noerd:make-app`)
- [Creating Modules](creating-modules.md) — scaffold a module with `noerd:module`, install/update commands, custom attributes
- [Artisan Commands](artisan-commands.md) — every `noerd:*` command with its options

### Building screens

- [List View](list-view.md) — YAML-driven lists: columns, actions, bulk actions, grid mode, views
- [List Search](list-search.md) — how the search field filters the list query
- [List Filters](list-filters.md) — dropdown filters and Excel-style column filters
- [Detail View](detail-view.md) — record forms: fields, tabs, actions, embedded lists
- [Page View](page-view.md) — page chrome around a record: relation box, widgets, embedded detail
- [Settings Pages](settings-page.md) — tenant-singleton forms with `NoerdSettingsPage`
- [Field Types](field-types.md) — reference of all YAML field types and the `FieldTypeRegistry`
- [Relation Field Types](relation-field-types.md) — registered `{x}Relation` pickers and their events
- [Relation Forms](relation-forms.md) — editing a related model's fields inside a detail form
- [Modal System](modal.md) — route modals vs. component modals, opening, closing, results
- [Themes](themes.md) — form layout themes (`default`, `compact`, `numbered`) and custom themes
- [Setup Collections](setup-collections.md) — tenant-maintained lookup tables defined in YAML or the database

### Application chrome

- [Navigation](navigation.md) — the per-app `navigation.yml`
- [Header Actions](header-actions.md) — module-contributed components in list and detail headers
- [Action Menu](action-menu.md) — the `x-noerd::action-menu` dropdown primitive
- [Dashboard Widgets](dashboard-widgets.md) — widgets below the app tiles on the apps dashboard
- [Quick Menu](quick-menu.md) — tenant-scoped action buttons in the header
- [Banner](banner.md) — notification banners at the top of the application
- [Keyboard Shortcuts](keyboard-shortcuts.md) — configurable list and detail shortcuts
- [Brand (Colors & Branding)](brand.md) — color palette, logo, favicon, sidebar dimensions
- [Languages](languages.md) — interface languages, translations and translatable collection values
- [Currency, Numbers & Dates](formatting.md) — tenant currency, tenant/user locale, `CurrencyHelper` and `FormatHelper`

### Platform

- [Authentication](auth.md) — the `noerd` guard, middleware groups and coexistence with a host auth stack
- [Permissions & Profiles](permissions.md) — profiles, authorization gates, named actions and the query-level read guard
- [Extension Registries](extension-registries.md) — the container singletons modules extend the core through
- [Reusable Traits](traits.md) — `BelongsToTenant`, filter traits, install-command traits and the static helpers
- [Testing](testing.md) — running the suite, global test helpers, testing YAML-driven components
- [AI Agents (Boost guidelines & skills)](ai-agents.md) — the Laravel Boost guideline and skills shipped with noerd
