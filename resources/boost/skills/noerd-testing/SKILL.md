---
name: noerd-testing
description: "Use this skill when writing or fixing tests for code built on noerd/noerd: Pest feature tests for list/detail/page/modal Livewire components, store and validation tests of YAML-driven forms (validDetailPayload, requiredLayoutFields), route-vs-component modal behaviour (registerTestLivewireRoute), install/update command tests, factories for noerd models, and the rule that tests prove mechanics rather than the current YAML configuration. Triggers on 'write a test for the list/detail', 'test fails after YAML change', 'how do I test the modal route', 'add a factory'."
license: MIT
metadata:
  author: noerd
---

# Testing with Noerd

Tests are Pest tests that live inside the module (`app-modules/{module}/tests/`), never in the
host project. Hard rules: `noerd/noerd` Boost guideline ("Tests Must Test Functionality, Not
Current Configuration", "Testing YAML-Driven Detail Forms"). Docs: `vendor/noerd/noerd/docs/testing.md`.

## 1. The one rule that breaks most tests

YAML under `app-configs/` is per-installation **configuration**. A test that asserts its current
content is wrong by definition:

- ✗ `assertSeeHtml('data-theme="compact"')` against a real detail, exact tab/column/field lists,
  titles, which field is required, whether a list opens its detail by route or by component.
- ✓ Prove the EFFECT of a configuration with synthetic layouts, runtime-written fixture YAMLs,
  factories and runtime-registered routes.
- ✓ Architecture guardrails are fine (a detail YAML has no `widgets:`/`relations:`; a page blade
  embeds the `detail:` its YAML names).

## 2. Global helpers (`tests/helpers.php` in the noerd package)

Not composer-autoloaded (test code must never load in production). Suites binding
`Noerd\Tests\TestCase` get them for free; every other `tests/Pest.php` loads them once with
`\Noerd\Tests\HelperLoader::load();` — never a hard-coded path, never a root `composer.json` entry.

| Helper | Use |
|---|---|
| `validDetailPayload(Model::class, $overrides)` | complete factory-sourced `detailData` for store-success tests |
| `requiredLayoutFields($component)` | required field names from the live `pageLayout` for validation tests |
| `registerTestLivewireRoute($uri, $component, $name)` | register a named `Route::livewire()` inside a test (route-vs-component mechanics) |

## 3. Recipes

**Detail store (create):**
```php
Livewire::test('module::thing-detail')
    ->set('detailData', validDetailPayload(Thing::class, ['tenant_id' => $tenantId]))
    ->set('detailData.name', 'Widget A')      // only the asserted field
    ->call('customerSelected', $customer->id)  // relation FKs via the component callback
    ->call('store')
    ->assertHasNoErrors();
```
**Detail store (update):** mount with `['modelId' => $model->id]`, `->set(...)`, `->call('store')`.
**Validation:** `$c = Livewire::test('module::thing-detail')->set('detailData', [])->call('store'); $c->assertHasErrors(requiredLayoutFields($c));`
— only for components that validate via `validateFromLayout()`; hard-coded `$this->validate([...])`
keeps explicit assertions.
**List:** create factory rows, `Livewire::test('module::things-list')->assertSee(...)`; for a
`listData()` override assert the filter/eager-load effect, not the YAML columns.
**Modal route fallback:** register a route with `registerTestLivewireRoute()` and assert the route
is used; without it assert the component fallback (see noerd's `NoerdListModalDispatchTest`,
`DetailActionsTest`, `NavigationModalRouteTest`, `RelationBoxTest`).
**Install/update commands:** `artisan('noerd:install-module', ['--force' => true])` with the
tenant assignment answered; assert YAML copies and the `tenant_apps` row (UPPERCASE key).

## 4. Factories

`definition()` must yield a fully valid, persistable record: every scalar column that *can* be
required is non-null and deterministic (no `optional()` there). Relation FKs may default to null
but must be satisfiable via a state or `create([...])`. Sparse/null records are created explicitly
in the test that needs them.

## 5. Running

- Host project: `php artisan test --compact app-modules/{module}/tests/Feature/ThingTest.php`
  (or `--filter=`). Run the smallest relevant subset, then `vendor/bin/pint --dirty`.
- Do not run suites in parallel against one shared MySQL test database — `migrate:fresh` of one run
  destroys the other. Package-only suites run on Testbench sqlite `:memory:`.
- Module test traits (`CreatesModuleUser`, …) live in `app-modules/{module}/tests/Traits/`.

## Done when

- [ ] test sits inside the module, is Pest, and has no assertion on current YAML content
- [ ] store tests use `validDetailPayload()` / mounted records; validation tests use `requiredLayoutFields()`
- [ ] factory produces a valid record by default
- [ ] the affected tests pass: `php artisan test --compact <file>`
