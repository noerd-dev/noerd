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
package into the testbench skeleton and publishes its `app-configs` and `config/noerd.php` —
mirroring `noerd:install` — so `StaticConfigHelper` finds the YAML files.
The published `app-configs` copy is compared file by file against the package on every run and
refreshed whenever any package YAML changed, so the suite never runs against stale configs.
`Noerd\Tests\TestCase` also registers the `noerd-test::` Livewire namespace for the fixture
components under `tests/Feature/fixtures/components` (`noerd-test::theme-test`,
`noerd-test::theme-setting-test`, `noerd-test::write-denied-test`, …) — synthetic components that
receive a layout from the test instead of a shipped YAML. The package's own test trait is
`Noerd\Tests\Traits\CreatesSetupUser` (`createUserWithSetupAccess()` — an admin user with a
tenant, default languages and the `SETUP` app selected); it is the only trait under
`tests/Traits/` and, like `Noerd\Tests\ModuleTestCase` (see [A module's own Testbench
suite](#a-modules-own-testbench-suite)), part of the public test API. The fixtures only the
package suite itself uses live under `tests/Support/` and are not shipped.

### From a host project

```bash
php artisan test --compact app-modules/noerd/tests
php artisan test --compact app-modules/noerd/tests/Feature/ListViewsTest.php
php artisan test --compact --filter=ListViews
```

In this mode the host's `tests/Pest.php` is loaded and the package's `tests/Pest.php` is skipped.
Every test file therefore binds its test case itself with `uses(Noerd\Tests\TestCase::class);` —
keep that line in every new test file.

The testbench skeleton (`vendor/orchestra/testbench-core/laravel`) is shared by every Testbench
suite of the workspace and persists between runs. Never assume it contains only noerd's files and
never remove foreign `app-modules` symlinks or `app-configs` folders: write uniquely named (`zz*`)
runtime fixtures, clean them up in `afterEach`, and never write through the `app-modules/noerd`
symlink (it points into the real package working tree).

## Where module tests live

Tests, test traits (`tests/Traits/`), factories and seeders belong to the module
(`app-modules/{module}/tests/`, `app-modules/{module}/database/`) — never to the host project.
Run them the same way: `php artisan test --compact app-modules/{module}/tests/...`.

## Module test setup

Module tests run under the **host project's** test case, and every test file binds its own setup —
there is no per-module `Pest.php`. The user comes from the global fixture helper: a user on a fresh
tenant that runs the app, with that tenant and app selected and — in the `actingAs` variant —
authenticated on the noerd guard:

```php
uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = actingAsNoerdUserForApp('INVENTORY');
    $this->tenantId = $this->user->selected_tenant_id;
});
```

The `tenant_apps` row is created in its canonical shape when missing (name = **uppercase** key,
which the gates compare exactly; route = lowercase key) and assigned through `Tenant::assignApp()`,
so the `TenantAppAssigned` listeners of the installed modules run as they do when an installation
assigns the app. `createNoerdUserForApp()` is the same fixture without authentication. Both take
the tenant attributes, the profile (`Noerd\Enums\Profile::ReadOnly`, the key of a profile a module
registers, or `null` for none) and attributes for a NEW `tenant_apps` row
(`route`, `title`, …) as further arguments; `ensureTenantApp('MEDIA')` creates a further app row,
which a multi-app tenant assigns with `$tenant->assignApp(...)`.

A module trait under `tests/Traits/` holds only what is specific to the module — a seeded settings
row, a second app the tenant needs, a domain record — and builds on the helper:

```php
trait CreatesInventoryFixtures
{
    protected function actingAsInventoryUser(): NoerdUser
    {
        $user = actingAsNoerdUserForApp('INVENTORY');
        InventorySettings::factory()->create(['tenant_id' => $user->selected_tenant_id]);

        return $user;
    }
}
```

The module's `Noerd\{Module}\Tests\` PSR-4 entry sits in the production `autoload` block — a host
never loads a dependency's `autoload-dev`.

## A module's own Testbench suite

A module that also tests standalone (its own CI, no host project) binds
`Noerd\Tests\ModuleTestCase` instead of the host's test case. It is `Noerd\Tests\TestCase` — the
core providers, the noerd guard, sqlite `:memory:`, the package linked into the skeleton — plus the
module's providers, each module linked into the skeleton's `app-modules/` so `StaticConfigHelper`
finds its YAML the way it does after `noerd:install-{module}`:

```php
namespace Noerd\Inventory\Tests;

use Noerd\Inventory\Providers\InventoryServiceProvider;
use Noerd\Tests\ModuleTestCase;

abstract class TestCase extends ModuleTestCase
{
    protected array $moduleProviders = [InventoryServiceProvider::class];
}
```

A module building on other modules lists their providers too (their modules are linked as well);
environment overrides go into `defineEnvironment()` after `parent::defineEnvironment($app)`. Every
test file binds `Noerd\Inventory\Tests\TestCase` itself with `uses()` — a host-root run loads the
host's `Pest.php` instead of the module's — and the suite runs with `vendor/bin/pest` in the module
root or `php artisan test --compact app-modules/inventory/tests` from a host, sequentially: the
Testbench suites share one skeleton. The global helpers come with the test case; nothing has to be
required.

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
  `ConfigResolutionTest`, `NoerdListModalDispatchTest`, `DetailActionsTest`,
  `NavigationModalRouteTest`, `RelationBoxTest`.
- Architecture guardrails are fine (a detail YAML contains no `widgets:`/`relations:`; a page
  blade embeds exactly the `detail:` component its YAML names).

## Global helpers

`tests/helpers.php` is **not** part of the production composer autoload — a consumer app must never
load test functions on every request — and `autoload-dev` does not help either: composer only dumps
the dev autoload of the ROOT package, never that of a dependency. The file is therefore loaded
explicitly, through `Noerd\Tests\HelperLoader`:

- Suites binding `Noerd\Tests\TestCase` get the helpers from that class and need no call.
- Every other suite (a module test bound to the host's `Tests\TestCase`, the host's own `Feature`
  suite) loads them once from its `tests/Pest.php` — `noerd:make-module` adds the line with the
  first local module:

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
| `createNoerdUserForApp(string $appKey, array $tenantAttributes = [], BackedEnum\|string\|null $profile = null, array $appAttributes = [])` | The standard module fixture: a user on a fresh tenant that runs the app (row created in the canonical shape, assigned through `Tenant::assignApp()`), attached under the profile, tenant and app selected |
| `actingAsNoerdUserForApp(...)` | The same fixture, authenticated on the noerd guard — the one-line `beforeEach` of a module test |
| `ensureTenantApp(string $appKey, array $attributes = [])` | The `tenant_apps` row of a module key in its canonical shape, created when missing — for the further apps of a multi-app tenant |
| `assertElementHasClasses()` / `assertNoElementHasClasses()` | DOM class assertions on rendered HTML |
| `assertModuleBoundary(string $moduleDir)` | The one boundary test of a module, driven by `composer.json` `extra.noerd.boundary` (see below) |
| `assertModuleDependenciesDeclared(string $moduleDir, array $allowedPackages = [])` | Fails when a module references another module's namespace without a `require` entry |
| `assertModuleDoesNotReference(string $moduleDir, array $forbidden)` | Fails when a namespace (or package) the module must never use appears in its files or in its `require` |
| `assertModuleUpdateCommandPublishesConfigs(string $command, string $moduleDir, string $moduleKey)` | Standard proof for a `noerd:update-{module}` command: publishes every shipped YAML into `app-configs/{key}`, exits cleanly and `--force` restores a locally modified copy; the installed configs are snapshotted and restored |
| `zzNormalizeSpaces(string $value)` | Collapses every kind of space (ICU uses U+00A0 / U+202F around symbols and groups) to a plain one, so a formatting assertion reads like the screen |

## Testing YAML-driven detail forms

`NoerdDetail::validateFromLayout()` turns every `required: true` field into a Laravel `required`
rule and adds the rules of the field type (`email`, `numeric`, `date`, the select's options, the
varchar length — see [Field Types](field-types.md#validation)). A factory payload therefore has to
produce values of the field types: an option of every inline select, a valid email.

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
`$detailRoute` dispatches only `modalComponent` (see `NoerdListModalDispatchTest`).

## Module boundary

Every module ships one `ModuleBoundaryTest` — `noerd:make-module` generates it — calling the global
helper `assertModuleBoundary()`:

```php
it('keeps its module boundary', function (): void {
    assertModuleBoundary(dirname(__DIR__, 2));
});
```

The helper reads the module's `composer.json`. The dependency guard
(`assertModuleDependenciesDeclared()`) derives the known module namespaces from the sibling
`app-modules/*/composer.json` files and fails when a namespace of another module appears anywhere
in the module (src, resources, routes, database, config, app-configs **and tests**) without a
matching `require`/`require-dev` entry. What the module may reference without declaring it, and
what it must never reference, is declared under `extra.noerd.boundary` — with the reason next to
every allowance, since the test file no longer carries one:

```json
"extra": {
    "noerd": {
        "boundary": {
            "forbidden": ["Noerd\\Party\\"],
            "allowed": {
                "nywerk/production-planning": "the parts export of the optional module is class_exists-guarded"
            }
        }
    }
}
```

`forbidden` (namespaces, or package names) is the hard boundary between modules that must never
meet — `customer` and `party`, `booking-members` and `accounting`: `assertModuleDoesNotReference()`
fails on any reference in the module's files, in PHP and in the escaped YAML form, and on a
`require` of the package that ships the namespace. `allowed` lists the packages the dependency
guard tolerates — a map with the reason as value, or a plain list. Both helpers can be called
directly as well. The boundary test itself and the agent documentation under `resources/boost/` are
skipped by both: a guideline states the rule and has to name what it forbids.

## Install and update commands

Every tenant app's `noerd:update-{module}` command gets one standard test built on the global helper:

```php
it('publishes the module configs', function (): void {
    assertModuleUpdateCommandPublishesConfigs('noerd:update-inventory', dirname(__DIR__, 2), 'inventory');
});
```

A support module (no `app-configs/{module}/` folder) asserts what it declares instead — the
published config and the entries `ensureModuleSetup()` writes. Its INSTALL command is testable
under `--no-interaction`: nothing migrates implicitly, so the run publishes and stops
(`SupportModuleInstallationTest` in the noerd package is the reference):

```php
it('publishes the config', function (): void {
    $this->artisan('noerd:install-payment', ['--no-interaction' => true])->assertExitCode(0);

    expect(File::exists(config_path('payment.php')))->toBeTrue();
});
```

### Rules for install-command tests

- **`vendor:publish` ignores a moved base path.** A test usually calls
  `$this->app->setBasePath($this->hostPath)` so the command publishes into a throwaway directory,
  but `vendor:publish` resolves its targets from the paths the providers registered when they
  booted — it writes into the REAL installation, silently under `--force`. The core's publishes are
  guarded (`PublishesNoerdContent::publishTargetsCurrentInstallation()` skips with a warning when
  the target lies outside `base_path()`); a module command that publishes through `vendor:publish`
  guards it the same way, or its test asserts the published file instead.
- **Never move a directory the whole run shares** (`storage/fonts`, `public/`) aside — a parallel
  worker uses it, and a failing assertion never restores it. Point the application at a throwaway
  path (`app()->useStoragePath(...)`) and restore it in a `finally`.
- **The base package is installed on the fly:** `noerd:install-{module}` runs `noerd:install` when
  `config/noerd.php` is missing. A test that does not want the real installer registers a stand-in
  declaring the same options (`--force`, `--migrate`, `--build`, `--demo`), so a forwarded option
  never makes the input throw:

```php
$this->app[Kernel::class]->registerCommand(new ZzFakeNoerdInstallCommand());
```

## Factories

A factory's `definition()` must produce a fully valid, persistable record: every non-relation scalar
column that can be `required` is non-null and deterministic (no `optional()` on such fields).
Foreign keys may default to `null` but must be satisfiable through a named state or an explicit
`create([...])`. Tests that need a sparse record pass the nulls explicitly.

The package ships factories for its own models under `database/factories/`:

| Factory | Notes |
|---------|-------|
| `NoerdUserFactory` | States `adminUser()`, `withExampleTenant()`, `withSelectedApp($app)`, `unverified()` and `superAdmin()` (the installation-wide flag) |
| `TenantFactory` | A plain tenant |
| `TenantAppFactory` | A tenant app row — attach it to a tenant with `$tenant->tenantApps()->attach(...)` |
| `NoerdSettingsFactory` | The per-tenant settings singleton (`currency`, `locale`, detail theme) — the fixture for formatting tests |
| `SetupCollectionFactory`, `SetupLanguageFactory`, `UserSettingFactory`, `NoerdLoginFactory` | The remaining core models |

## Shared databases and parallel runs

- Testbench's sqlite `:memory:` isolates the package suite. Never run two suites against one shared
  MySQL test database at the same time — a `migrate:fresh` of one run drops the tables under the
  other. Give a second session its own database (`DB_DATABASE=noerd_test_<name> php artisan test …`).
- **Never `vendor/bin/pest --parallel` in a host that has Orchestra Testbench installed** (every
  host running module suites has): Pest's Laravel parallel handler disables itself as soon as
  Testbench is present, so all workers run `migrate:fresh` against the SAME database. Use
  `php artisan test --parallel` for the host suites — Laravel derives one database per worker.
- Testbench-bound suites (every suite built on `Noerd\Tests\TestCase`) share ONE skeleton for
  their runtime-written fixtures and are therefore never run in parallel with each other; run them
  sequentially (`--testsuite=Testbench` or per path).

## Request-scoped memos

`TenantHelper::getSelectedTenant()` memoizes the tenant (including its `tenantApps`) per request
and is only flushed on boot. Several `Livewire::test()` calls in one test share that process: after
attaching or detaching tenant apps (or any other tenant mutation) mid-test, call
`TenantHelper::clearCache()` before the next component renders, otherwise it sees the stale relation.

## Next Steps

- [Creating Modules](creating-modules.md) — where module tests and factories live
- [Detail View](detail-view.md) — the components these tests exercise
- [AI Agents](ai-agents.md) — the `noerd-testing` Boost skill distils this page for coding agents
