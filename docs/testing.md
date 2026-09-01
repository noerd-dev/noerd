# Testing

Noerd ships its own Pest test suite and a small set of global helpers that modules built on noerd
use to test their list, detail, page and modal components. This page describes how to run the
package suite and how to write tests for your own modules.

## Running the package suite

### Standalone (package root)

```bash
cd app-modules/noerd      # or the checkout of noerd-dev/noerd
composer install
vendor/bin/pest
vendor/bin/pest tests/Feature/ListViewsTest.php
```

The suite runs on [Orchestra Testbench](https://packages.tools/testbench) with the built-in sqlite
`:memory:` connection (`NOERD_TESTBENCH_DB`, see `phpunit.xml`). `Noerd\Tests\TestCase` links the
package into the testbench skeleton and publishes its `app-configs` and `config/noerd.php` — the
same thing `noerd:install` does in a real project — so `StaticConfigHelper` finds the YAML files.
The published `app-configs` copy is compared file by file against the package on every run and
refreshed whenever any package YAML changed, so the suite never runs against stale configs.
`Noerd\Tests\TestCase` also registers the `noerd-test::` Livewire namespace for the fixture
components under `tests/Feature/fixtures/components` (`noerd-test::theme-test`,
`noerd-test::theme-setting-test`, `noerd-test::write-denied-test`, …) — synthetic components that
receive a layout from the test instead of a shipped YAML. The package's own test trait is
`Noerd\Tests\Traits\CreatesSetupUser` (`createUserWithSetupAccess()` — an admin user with a
tenant, default languages and the `SETUP` app selected); it is the only trait under
`tests/Traits/` and part of the public test API.

### From a host project

```bash
php artisan test --compact app-modules/noerd/tests
php artisan test --compact app-modules/noerd/tests/Feature/ListViewsTest.php
php artisan test --compact --filter=ListViews
```

In this mode the host's `tests/Pest.php` is loaded and the package's `tests/Pest.php` is skipped.
Every test file therefore binds its test case itself with `uses(Noerd\Tests\TestCase::class);` —
keep that line in every new test file.

The testbench skeleton (`vendor/orchestra/testbench-core/laravel`) is shared and persistent: other
packages' suites symlink their module and publish their YAML copies into the same
skeleton, and those files survive between runs. `Noerd\Tests\TestCase` must NOT remove foreign
`app-modules` symlinks or `app-configs` folders — interleaved suites in the same workspace depend on
them. Consequently, tests must never assume the skeleton contains only noerd's files: write uniquely
named (`zz*`) runtime fixtures, clean them up in `afterEach`, and never write through the
`app-modules/noerd` symlink (it points into the real package working tree).

## Where module tests live

Tests, test traits (`tests/Traits/`), factories and seeders belong to the module
(`app-modules/{module}/tests/`, `app-modules/{module}/database/`) — never to the host project.
Run them the same way: `php artisan test --compact app-modules/{module}/tests/...`.

## Module test setup

Module tests run under the **host project's** test case, and every test file binds its own setup —
there is no per-module `Pest.php`:

```php
uses(Tests\TestCase::class, RefreshDatabase::class, CreatesInventoryUser::class);

beforeEach(function (): void {
    $this->user = $this->withInventoryModule();
    $tenant = $this->user->tenants()->firstOrFail();
    $this->user->update(['selected_tenant_id' => $tenant->id]);
    $this->actingAs($this->user);
    $this->tenantId = $tenant->id;
});
```

`Creates{Module}User` is the module's own trait in `tests/Traits/` (autoloaded via the module's
`Noerd\{Module}\Tests\` PSR-4 entry). It builds a user whose tenant has the app — note the
**uppercase** tenant-app name, which gates compare exactly:

```php
trait CreatesInventoryUser
{
    protected function withInventoryModule(): NoerdUser
    {
        $user = NoerdUser::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id);

        TenantHelper::setSelectedTenantId($tenant->id);
        TenantHelper::setSelectedApp('INVENTORY');

        $app = TenantApp::firstOrCreate(
            ['name' => 'INVENTORY'],
            [
                'title' => 'Inventory',
                'icon' => 'inventory::icons.app',
                'route' => 'inventory',
                'is_active' => true,
            ],
        );
        $tenant->tenantApps()->syncWithoutDetaching([$app->id]);

        return $user;
    }
}
```

## Tests prove mechanics, not configuration

The YAML files under `app-configs/` are per-installation configuration: the theme, titles, labels,
tabs, the field and column lists, `required:` flags and the modal target (`route:` vs.
`modalComponent:`, `$detailRoute` vs. `$detailComponent`) may all be changed by an installation.
A test that asserts their current content is wrong by definition.

- Never assert current YAML settings against a shipped component (`assertSeeHtml('data-theme="compact"')`,
  exact tab/field/column lists, which field is required, route vs. component target).
- Prove the *effect* of a setting with a synthetic layout, a fixture YAML written at runtime under
  the testbench skeleton, factories/mocks for data and runtime-registered routes. References in
  the package: `ThemeTest` (`noerd-test::theme-test` components), `StaticConfigHelperFeatureTest`,
  `PerAppConfigResolutionTest`, `ListDetailRouteFallbackTest`, `DetailActionsTest`,
  `NavigationModalRouteTest`, `RelationBoxRouteTest`.
- Architecture guardrails are fine (a detail YAML contains no `widgets:`/`relations:`; a page
  blade embeds exactly the `detail:` component its YAML names).

## Global helpers

`tests/helpers.php` is **not** part of the production composer autoload — a consumer app must never
load test functions on every request — and `autoload-dev` does not help either: composer only dumps
the dev autoload of the ROOT package, never that of a dependency. The file is therefore loaded
explicitly, through `Noerd\Tests\HelperLoader`:

- Suites binding `Noerd\Tests\TestCase` get the helpers from that class and need no call.
- Every other suite (a module test bound to the host's `Tests\TestCase`, the host's own `Feature`
  suite) loads them once from its `tests/Pest.php`:

```php
\Noerd\Tests\HelperLoader::load();
```

`HelperLoader` lives in the package's psr-4 map, so it resolves through the autoloader whether noerd
is installed as a composer package (`vendor/noerd/noerd/`) or as a git submodule
(`app-modules/noerd/`) — never hard-code either path, and never add `tests/helpers.php` to a
project's root `composer.json`. Its own file is only read once something calls it, so nothing test
related reaches a production request. New global helpers go into `tests/helpers.php`, guarded with
`function_exists`.

| Helper | Purpose |
|--------|---------|
| `validDetailPayload(Model::class, array $overrides = [])` | Complete `detailData` array sourced from the model factory (`make()`), `id`/timestamps stripped, merged with overrides |
| `requiredLayoutFields($component)` | The `detailData.*` names marked `required: true` in the component's live `pageLayout` (recurses into `type: block`) |
| `registerTestLivewireRoute(string $uri, string $component, string $name)` | Registers a named `Route::livewire()` route inside a test and refreshes the name lookups |
| `assertElementHasClasses()` / `assertNoElementHasClasses()` | DOM class assertions on rendered HTML |
| `assertModuleDependenciesDeclared(string $moduleDir, array $allowedPackages = [])` | Fails when a module references another module's namespace without a `require` entry (see below) |
| `assertModuleUpdateCommandPublishesConfigs(string $command, string $moduleDir, string $moduleKey)` | Standard proof for a `noerd:update-{module}` command: publishes every shipped YAML into `app-configs/{key}`, exits cleanly and `--force` restores a locally modified copy; the installed configs are snapshotted and restored |
| `createNoerdUserWithProfile(?Profile $profile)` | A user attached to a fresh tenant under the given `Noerd\Enums\Profile` (or none), with that tenant selected — the fixture for profile and permission tests |

## Testing YAML-driven detail forms

`NoerdDetail::validateFromLayout()` turns every `required: true` field into a Laravel `required`
rule. Nothing else in the YAML drives validation.

**Store success** — submit a complete factory payload and override only what the test asserts:

```php
use Livewire\Livewire;

it('stores a task', function (): void {
    Livewire::test('inventory::task-detail')
        ->set('detailData', validDetailPayload(Task::class, ['tenant_id' => $tenantId]))
        ->set('detailData.title', 'Call Jane')
        ->call('itemSelected', $item->id)       // relation FK via the component callback
        ->call('store')
        ->assertHasNoErrors();
});

it('updates a task', function (): void {
    $task = Task::factory()->create(['tenant_id' => $tenantId]);

    Livewire::test('inventory::task-detail', ['modelId' => $task->id])
        ->set('detailData.title', 'New title')
        ->call('store')
        ->assertHasNoErrors();
});
```

Relation FKs set by callbacks and virtual fields (translatable arrays, belongsToMany arrays) are not
part of `factory()->toArray()` — set them explicitly. Do not factory-seed a component whose
`mount()` pre-fills required fields reactively; set only the asserted fields there.

**Validation** — derive the required fields from the live layout:

```php
$component = Livewire::test('inventory::item-detail')->set('detailData', [])->call('store');
$component->assertHasErrors(requiredLayoutFields($component));
```

This applies only to components validating through `validateFromLayout()`. A hard-coded
`$this->validate([...])` tests stable code and keeps explicit assertions.

## Testing modal targets (route vs. component)

```php
it('opens the detail by route when the route exists', function (): void {
    registerTestLivewireRoute('zz/thing/{modelId}', 'zz::thing-detail', 'zz.thing.detail');

    Livewire::test('zz::things-list')
        ->call('listAction', $thing->id)
        ->assertDispatched(
            'noerdModal',
            fn (string $event, array $params): bool => ($params['route'] ?? null) === 'zz.thing.detail'
                && ($params['modalComponent'] ?? null) === 'zz::thing-detail',
        );
});
```

The list dispatches the route together with the component as fallback; a list without a
`$detailRoute` dispatches only `modalComponent` (see `ListDetailRouteFallbackTest`).

## Module dependency guard

Every business module ships a `ModuleBoundaryTest` calling the global helper
`assertModuleDependenciesDeclared(dirname(__DIR__, 2))` (from `tests/helpers.php`).
It derives the known module namespaces from the sibling `app-modules/*/composer.json`
files and fails when a namespace of another module appears anywhere in the module
(src, resources, routes, database, config, app-configs **and tests**) without a
matching `require`/`require-dev` entry. Documented exceptions (e.g. a
`class_exists()`-guarded optional integration) are passed as the second argument:

```php
assertModuleDependenciesDeclared(dirname(__DIR__, 2), ['vendor/optional-integration']);
```

Add a short comment above every allowance explaining why it is legitimate.

## Install and update commands

Every module's `noerd:update-{module}` command gets one standard test built on the global helper:

```php
it('publishes the module configs', function (): void {
    assertModuleUpdateCommandPublishesConfigs('noerd:update-inventory', dirname(__DIR__, 2), 'inventory');
});
```

## Factories

A factory's `definition()` must produce a fully valid, persistable record: every non-relation scalar
column that can be `required` is non-null and deterministic (no `optional()` on such fields).
Foreign keys may default to `null` but must be satisfiable through a named state or an explicit
`create([...])`. Tests that need a sparse record pass the nulls explicitly.

## Shared databases

Testbench's sqlite `:memory:` isolates the package suite. If you point module tests at a shared
MySQL test database, never run two suites against it at the same time — a `migrate:fresh` of one
run drops the tables under the other.

## Next Steps

- [Creating Modules](creating-modules.md) — where module tests and factories live
- [Detail View](detail-view.md) — the components these tests exercise
- [AI Agents](ai-agents.md) — the `noerd-testing` Boost skill distils this page for coding agents
