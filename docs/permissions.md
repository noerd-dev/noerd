# Permissions & Profiles

Noerd's authorization is layered in two stages:

1. **Profiles:** every user carries exactly ONE profile per tenant
   (`users_tenants.profile_key`). The profile is the BASELINE of what the user
   may do. Profiles are a fixed technical concept, hardcoded as the
   `Noerd\Enums\Profile` enum — there is no profiles table and no profile CRUD.
2. **Authorization gates (optional):** a project may define the `noerd.*`
   gates to decide app access, per-object abilities and named action checks
   centrally. A defined gate decides alone and is expected to incorporate the
   profile baseline itself.

## Profiles

| Case (`Noerd\Enums\Profile`) | Stored key | Baseline without gates |
|------------------------------|------------|------------------------|
| `Profile::Admin` | `ADMIN` | Tenant administration: `/setup` access, bypasses every permission check |
| `Profile::User` | `USER` | Full access — new apps and objects are usable immediately |
| `Profile::ReadOnly` | `READ_ONLY` | Like User, but restricted to reading (and opening apps) |

- The profiles are HARDCODED — nothing is seeded, nothing can be created,
  renamed or deleted. Display labels come from `Profile::label()` (translated).
- The profile is assigned per tenant on the user detail (Setup → Users). A user
  without a profile behaves like `User` — a missing assignment must never lock
  an installation out.
- `NoerdUser::isAdmin()` is the tenant-scoped ADMIN check (ADMIN profile of the
  selected tenant, or the global `super_admin` flag). It guards `/setup`
  (`SetupMiddleware`), the admin-only components (`ComponentAccessGuard`) and
  is the bypass every gate implementation applies first.

### Registering additional profiles

A module may offer additional profiles through the `ProfileRegistry`
(singleton) — the profile pickers (user detail, tenant-access display) render
from it, so a registered profile becomes assignable without any core change:

```php
use Noerd\Services\ProfileRegistry;

app(ProfileRegistry::class)->register('MY_PROFILE', fn(): string => __('My Profile'));
```

A registered profile's SEMANTICS come from the authorization gates the module
defines; the core's own baseline treats unknown keys like `User`.
`NoerdUser::currentProfileKey()` exposes the raw stored key for such modules;
`currentProfile()` resolves only the built-in enum cases.

## Abilities

Every check goes through `Noerd\Helpers\AccessHelper`:

| Helper | Gate | Meaning |
|--------|------|---------|
| `canAccessApp($appName)` | `noerd.access-app` | Open an app (tiles, app bar, `app-access:{app}` middleware, config discovery) |
| `canReadObject($modelClass)` | `noerd.object-read` | See the object's records (list rows, detail mount, CSV export) |
| `canWriteObject($modelClass)` | `noerd.object-write` | Update existing records |
| `canCreateObject($modelClass)` | `noerd.object-create` | Create new records ("New …" buttons, store() on a new record) |
| `canDeleteObject($modelClass)` | `noerd.object-delete` | Delete records (delete button, bulk delete) |
| `canPerformAction($actionKey)` | `noerd.action` | Perform a named action declared in code (see below) |
| `canUseApp(...$appNames)` | — | Convenience for app-bound chrome: at least one of the apps is ASSIGNED to the selected tenant and `canAccessApp()` allows it. No tenant → false |

With NO gate defined the profile baseline applies: `User`/`Admin` (and no
profile) → everything, `ReadOnly` → only `canReadObject`/`canAccessApp`.
Guests are never restricted (config discovery, unauthenticated rendering). Null arguments
(no model/app/action known) are always allowed.

Defining a gate (e.g. in a service provider's `boot()`) replaces the baseline
for that ability — see `docs/extension-registries.md` ("Authorization gates")
for the closure contract (nullable user!) and examples.

Detail/page components additionally expose **`canSaveObject()`**: the ability
for the form's CURRENT state — create while the record has no `$modelId` yet,
write once it has one. The save button, the save shortcut and the readonly
field rendering key off it; `store()` and the generic `WriteGuardHook` enforce
it server-side. Settings pages (tenant singletons) always treat saving as a
write.

## Enforcement map (all generic — never duplicate per module)

- **Lists (`NoerdList`):** read denial yields zero rows (`whereRaw('1 = 0')`)
  plus the denied panel, blocks CSV export, and strips header actions; "New …"
  actions (`action: listAction` or a `route:` action) are stripped without
  create, every other header action without write; the `deleteSelected` bulk
  action is stripped/blocked without delete.
- **Details/pages (`NoerdPage`/`NoerdDetail`):** read denial blocks the mount
  before any record data is loaded; `store()`/`delete()` are no-ops without the
  matching ability — enforced even for custom overrides by the global
  `WriteGuardHook`; fields render readonly, save/delete buttons and keyboard
  shortcuts are hidden.
- **Apps:** `AppAccessMiddleware`, the app bar, the home
  tiles and the allowed-app config discovery all consult `canAccessApp()`.
- **Dashboard widgets and quick-menu buttons** declare `app:` (string) or
  `apps:` (list) in their YAML — an entry renders only when one of its apps is
  assigned to the tenant AND allowed (`AccessHelper::canUseApp()`, see
  `docs/dashboard-widgets.md` and `docs/quick-menu.md`). NEVER hand-roll a
  per-module "tenant has app X" gate for this — such gates ignore the user's
  profile.

## Query-level read guard (opt-in trait)

The generic read guards cover the NoerdList/NoerdDetail render paths. Data
that flows through HAND-BUILT queries — dashboard counters
(`Invoice::query()->count()` / `->sum()`), bespoke widgets, manual
`listData()` queries, reports — is NOT guarded automatically: noerd promises
to never silently rewrite an application's queries, and an automatic global
guard would also empty framework internals (users, tenants) and break sign-in
under a restrictive gate.

Protecting an object at the query level therefore REQUIRES an explicit opt-in
on its model — without the trait it does not happen:

```php
use Noerd\Traits\GuardedByObjectPermission;

class Invoice extends Model
{
    use GuardedByObjectPermission;
}
```

The trait registers a global scope: while `canReadObject()` denies the model
for the current user, EVERY query on it yields nothing — aggregates, relations
and raw counters included, so denied tiles and reports simply show no data.
Admins, guests and every context without an authenticated noerd user
(console, queue jobs, public shop pages) are unaffected. A system read that
must run inside a denied user's request lifts the guard explicitly with
`Invoice::withoutGlobalScope(Invoice::OBJECT_READ_GUARD_SCOPE)`. Reference:
`GuardedByObjectPermission`, `ObjectReadGuardScopeTest`.

## Known boundaries

- Custom PUBLIC methods on components (`approve()`, `duplicate()`, custom bulk
  actions) are NOT guarded generically — only `store`/`delete` run through the
  `WriteGuardHook`. Guard such methods yourself (an object ability check or a
  named action check).
- A list with a hand-built query in `listData()` bypasses `listQuery()`'s
  read-denial row filter — give the model the `GuardedByObjectPermission`
  trait (see above) or apply `AccessHelper::canReadObject()` yourself.
- Hand-built aggregate queries (dashboard counters etc.) are only guarded for
  models that opt into `GuardedByObjectPermission`.
- `StaticConfigHelper::getListConfigForApp()` deliberately resolves configs of
  every app (cross-app list views); the DATA behind them is still guarded by
  the object abilities.

## Named action checks

A named action guards an operation that is not a plain CRUD ability — "start
the production run", "merge accounts". Actions are declared in code and
checked at their call sites; they are mutations, so the profile baseline
treats them like writes (`ReadOnly` is denied).

**1. Declare** the action in the module ServiceProvider's `boot()` so
authorization tooling can enumerate it. Keys are snake_case (`[a-z0-9_]`):

```php
use Noerd\Services\ActionPermissionRegistry;

app(ActionPermissionRegistry::class)->register('production_start_run', 'Start Production Run');
```

**2. Check** it — either on a route:

```php
Route::post('production/start', StartRunController::class)
    ->middleware(['noerd', 'app-access:production', 'action-permission:production_start_run']);
```

…or manually in a Livewire action / service:

```php
use Noerd\Helpers\AccessHelper;

if (! AccessHelper::canPerformAction('production_start_run')) {
    return;
}
```

With a `noerd.action` gate defined, its implementation decides. Without one
the baseline applies — regular users may perform every action. Always register
what you check.
