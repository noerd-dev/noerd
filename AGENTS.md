# AGENTS.md — contributing to noerd/noerd

This file is for humans and AI agents working **inside this repository** (the noerd core package,
`noerd/noerd`). The rules for *building with* noerd — lists, details, pages, modals, modules, tests in
a host project — are shipped as a Laravel Boost guideline in
`resources/boost/guidelines/core.blade.php` (plus the Boost skills in `resources/boost/skills/`)
and are NOT repeated here. Read that guideline first; it applies to code in this repository as well.

## What this repository is

- A Laravel package: YAML-driven list/detail/page components (`NoerdList`, `NoerdDetail`,
  `NoerdPage` traits), the modal system, themes, navigation, tenant apps, install/update commands.
- It is consumed either from Packagist (`vendor/noerd/noerd`) or as a git submodule at
  `app-modules/noerd` with a Composer path repository (symlinked) — the host project then contains
  the package source directly.
- `docs/` is the single source of the public documentation (https://noerd.dev). The repository
  `noerd-dev/docs` is an auto-synced mirror (`.github/workflows/sync-docs.yml`) — never edit the
  mirror, it is overwritten on every push to `main`.

## Workflow

- **Never commit or push directly to `main`.** Create a feature branch, push it and open a GitHub
  pull request against `main`. The only exception is the release version bump described below.
- **Release:** tagging a version requires the `composer.json` `"version"` field to equal the tag in
  the tagged commit (`Bump version to vX.Y.Z`). That bump may be committed to `main` as part of the
  tag flow.
- **A pushed tag is immutable — never move it.** Packagist indexes a version once and keeps the
  commit it saw first, so re-pointing an existing tag keeps shipping the old code while the
  repository looks correct. Consumers see no reason to update, because the version number did not
  change. Anything that has to reach users goes into the next patch release instead. This is not
  hypothetical: `v0.11.0` was moved to a later commit after it had been published, Packagist kept
  serving the commit from five days earlier, and the released `v0.11.0` was therefore older than
  `v0.10.13` — `^0.11.0` resolved to less code than `^0.10`.
- Commit messages: one concise sentence describing the change. No generated-by footers, no
  co-author lines.
- Do not change dependencies (`composer.json` `require`) without explicit agreement.

## Tests

- Tests are Pest tests under `tests/` (`tests/Feature`, `tests/Unit`, `tests/Commands`,
  `tests/Components`, …). Every change needs a test; run the smallest relevant subset.
- **Standalone run (package root):** `composer install` then `vendor/bin/pest` (or
  `vendor/bin/pest tests/Feature/SomeTest.php`). The suite runs on Orchestra Testbench with the
  built-in sqlite `:memory:` connection (`NOERD_TESTBENCH_DB`, see `phpunit.xml`); `Noerd\Tests\TestCase`
  links the package into the testbench skeleton and publishes its `app-configs` once, mirroring
  `noerd:install`.
- **Host run:** inside a host project `php artisan test --compact app-modules/noerd/tests/...`.
  The host's `tests/Pest.php` is loaded instead of ours, therefore **every test file binds the test
  case itself** with `uses(Noerd\Tests\TestCase::class);` — never move that into `tests/Pest.php`.
- `tests/helpers.php` holds the global test helpers (`validDetailPayload()`,
  `requiredLayoutFields()`, `registerTestLivewireRoute()`, …). It is deliberately NOT in the
  production composer autoload (test functions must never load in a consumer app's requests):
  `Noerd\Tests\TestCase` requires it, so every suite extending it gets the helpers, and a host
  project additionally requires it from its own `tests/Pest.php`. New global helpers go there,
  guarded with `function_exists`.
- **Tests prove mechanics, not configuration.** The YAML files under `app-configs/` are
  per-installation configuration: never assert their current content (titles, themes, field lists,
  route vs. component targets). Use synthetic layouts, fixture YAMLs written at runtime or the
  `noerd::*-test` components instead (see `docs/testing.md`).
- Do not run the suite against a shared database that another test run is using; Testbench's sqlite
  `:memory:` is the default for exactly that reason.

## Code style

- Laravel Pint with the `pint.json` in this repository (PER preset + project rules):
  `vendor/bin/pint --dirty` after `composer require laravel/pint --dev` in a standalone checkout, or
  from the host project's root (`vendor/bin/pint app-modules/noerd/...`) — the host ships an
  identical config, both runs must produce the same result.
- PHP 8.3+, strict comparisons, explicit return types, constructor property promotion, `$guarded`
  instead of `$fillable`, Eloquent models never stored as Livewire properties.
- Comments, Artisan prompts, docs and commit messages are written in English.
- YAML is always block style, never flow style. Navigation icons are heroicons.

## Architecture guardrails

- **Generic first.** A feature that more than one module needs lives ONCE in the core: in the
  `NoerdList` / `NoerdDetail` / `NoerdPage` traits, in the `noerd::` Blade components
  (`resources/views/components`) or in a registry (`src/Services/*Registry.php`). Never ask modules
  to duplicate framework behaviour.
- **Page/Detail split.** Detail YAMLs are pure model forms; `widgets:`/`relations:`/page tabs belong
  in the page YAML and `NoerdPage`. `NoerdDetail` composes `NoerdPage` — never duplicate between them.
- **Modals.** Action dialogs open by component (`Noerd::modal()`), addressable records open by route
  (`Noerd::modalRoute()`), always with the component as fallback (`Noerd::modalFor()`). Keep the two
  APIs explicit — no implicit detection.
- **Config.** `config/noerd.php` (package defaults) and `stubs/noerd.php.stub` (published into the
  host by `noerd:install`) must stay in sync; a new config key or feature flag goes into both, and
  `noerd:update` (`publishNoerdConfig()` / `setupFrontendAssets()`) must carry it into existing
  installations.
- **YAML copies.** The package's own app configs live in `app-configs/` (setup app etc.). In a host
  project the installed copy lives under `app-configs/{app}/` — when the framework changes a shipped
  YAML, both the package copy and the install/update command that publishes it must be updated.
- **Livewire children in modals** are re-mounted on every modal-stack update (`@teleport`); `mount()`
  of any component that can live inside a modal must be free of side effects.
- `docs/` contains only the documentation of the *current* version — no upgrade guides, no
  version history.

## When you add or change a framework feature

1. Implement it generically (trait / `noerd::` component / registry) with a Pest test.
2. Document it in the matching `docs/*.md` page (English, current state only) and link it from
   `docs/README.md` / `README.md` if it is a new page.
3. Update the agent-facing rules: `resources/boost/guidelines/core.blade.php` (hard rules, wrapped
   in `@verbatim`) and, if the workflow changed, the relevant skill in `resources/boost/skills/`.
   `tests/Feature/BoostGuidelineTest.php` verifies that the guideline still renders.
4. If the `noerd:module` / `noerd:make-resource` stubs are affected
   (`src/Commands/stubs/`), update them and `tests/Commands/MakeModuleStubsTest.php`.
