# Extension Registries

Optional modules and projects extend the noerd core through a small set of container singletons. A module registers its contribution from its service provider's `boot()` (or rebinds a contract in `register()`); the core resolves the singleton where the extension point renders. This is registration-based rather than config-based on purpose: an entry in a YAML file outlives the module that wrote it and would have to be guarded against, whereas a registration simply ceases to exist once the module is gone — no config cleanup needed.

Registries with their own documentation are not repeated here:

- `FieldTypeRegistry` — custom form field types, see [field-types.md](field-types.md)
- `RelationFieldRegistry` — relation field types, see [relation-field-types.md](relation-field-types.md)
- `ThemeRegistry` — form layout themes, see [themes.md](themes.md)
- `HeaderActionsRegistry` — list/detail header actions, see [header-actions.md](header-actions.md)
- `ProfileRegistry` — additional user profiles, and `ActionPermissionRegistry` — named actions
  beyond CRUD, see [permissions.md](permissions.md)

Documented on this page: `TopBarRegistry`, `PicklistRegistry`, `DynamicNavigationRegistry`,
`RelationBoxRegistry`, `DetailSlotsRegistry`, `ComponentAccessGuard`, the overridable null bindings
and the authorization gates.

## TopBarRegistry

Contributes Livewire components to the right-hand slot of the top bar — rendered between the quick menu and the setup cog / profile dropdown, on every backend page.

**API** (`Noerd\Services\TopBarRegistry`):

| Method | Description |
|--------|-------------|
| `register(string $component)` | Add a Livewire component name to the top bar |
| `all()` | All registered component names, in registration order |

**Registering** from a module service provider's `boot()`:

```php
use Noerd\Services\TopBarRegistry;

public function boot(): void
{
    app(TopBarRegistry::class)->register('my-module::top-bar-notifications');
}
```

The registered name is a Livewire component name — typically an anonymous view-file component in the module's `resources/views/components/` folder, resolved through the module's Livewire namespace (`Livewire::addNamespace('my-module', viewPath: ...)`).

**Where it renders:** `resources/views/components/layout/top-bar.blade.php` reads `app(TopBarRegistry::class)->all()` in `mount()` and mounts every entry:

```blade
@foreach($topBarComponents as $topBarComponent)
    <livewire:dynamic-component :component="$topBarComponent" :key="$topBarComponent" />
@endforeach
```

**Important:**

- Each component decides for itself whether it renders anything (permissions, current app, feature flags) — the core mounts every registration unconditionally
- Use a single collapsing root (`<div class="contents">`) and render the icon/button inside it only when visible — never `@if` around the root element
- Keep top bar components small: one icon, one function

## PicklistRegistry

Named option providers for `type: picklist` fields. A detail YAML references options by name via `picklistField:`; the options can come from a method on the detail component itself OR from a provider another module registered — so a foreign module can supply options to a detail form purely by name, with no PHP dependency between the modules.

**API** (`Noerd\Services\PicklistRegistry`):

| Method | Description |
|--------|-------------|
| `register(string $name, callable $provider)` | Register a provider; the callable returns a `value => label` array |
| `has(string $name)` | Whether a provider is registered under that name |
| `resolve(string $name)` | The callable, or `null` when none is registered |

**Resolution order** — `NoerdDetail::resolvePicklistOptions(string $picklistField)`:

1. A public method with that name on the detail component wins — but only when it declares an
   `: array` return type. The name is client-callable, so only a genuine options provider is ever
   invoked, never a `void` action such as `store()` or `delete()`
2. Otherwise the `PicklistRegistry` provider is called
3. Neither exists → empty options (`[]`), never an error

**Registering** (an inventory module supplying warehouse options):

```php
use Noerd\Helpers\TenantHelper;
use Noerd\Services\PicklistRegistry;

public function boot(): void
{
    app(PicklistRegistry::class)->register('warehouseOptions', function (): array {
        $tenantId = TenantHelper::getSelectedTenantId();

        return ['' => __('Please select...')] + Warehouse::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    });
}
```

**Using it** in a detail YAML:

```yaml
fields:
  - name: detailData.warehouse_id
    label: Warehouse
    type: picklist
    picklistField: warehouseOptions
    colspan: 6
```

**Important:**

- Provider callables run per render — keep the query cheap and tenant-scoped
- A component method with the same name (and an `: array` return type) always shadows a registered provider
- PHP code can resolve a provider directly when it needs the options outside a form: `app(PicklistRegistry::class)->resolve('warehouseOptions')`

## DynamicNavigationRegistry

Lets a module inject navigation entries computed at runtime (e.g. one entry per setup collection) into a `navigation.yml` that only names a *type*. `StaticConfigHelper::getNavigationStructure()` runs every parsed navigation through `processDynamicNavigation()`, which resolves the type against the registry and expands the block.

**Provider contract** (`Noerd\Contracts\DynamicNavigationProviderContract`):

```php
interface DynamicNavigationProviderContract
{
    /** The dynamic navigation type key this provider handles. */
    public function type(): string;

    /** @return array<int, array{title: string, link: string, icon?: string, heroicon?: string}> */
    public function items(): array;
}
```

**API** (`Noerd\Services\DynamicNavigationRegistry`):

| Method | Description |
|--------|-------------|
| `register(DynamicNavigationProviderContract $provider)` | Register a provider under its `type()` |
| `resolve(string $type)` | The provider for a type, or `null` |
| `all()` | All providers keyed by type |

**YAML usage** — two forms, matching the two navigation structures:

A block menu with a `dynamic:` key gets its `navigations` replaced by the provider's `items()`:

```yaml
- title: Setup
  block_menus:
    - title: Data Management
      dynamic: setup-collections
```

An item in an `items:` structure with `type: dynamic` gets the provider's `items()` merged into its `children` (the type key comes from `dynamic:`, with `collection:` accepted as an alias):

```yaml
items:
  - title: Collections
    type: dynamic
    dynamic: page-collections
```

An unresolvable type expands to nothing — a navigation referencing a not-installed module's provider never breaks the sidebar.

**Shipped example** — `Noerd\Navigation\SetupCollectionsNavigationProvider` handles `setup-collections`: it maps every setup collection definition to a navigation entry linking to the `setup-collections` route with the definition's filename as query key. The core registers it itself:

```php
$registry = $this->app->make(DynamicNavigationRegistry::class);
$registry->register($this->app->make(SetupCollectionsNavigationProvider::class));
```

A module registers its own provider the same way from `boot()` — e.g. an inventory module exposing one navigation entry per warehouse under a `warehouses` type.

## RelationBoxRegistry

Lets an optional module append tiles to another module's relation box (`<x-noerd::detail-relations>`
/ `noerd::relation-box`, see [page-view.md](page-view.md)) without the host module knowing the
contributor. The host page's YAML `relations:` tiles render first in authored order; registry tiles
append after them, sorted ascending by `sort` (equal sorts keep registration order). A registration
ceases to exist with its module — the reason this is a registry and not a YAML entry: a YAML entry
would outlive an uninstalled module.

**API** (`Noerd\Services\RelationBoxRegistry`):

| Method | Description |
|--------|-------------|
| `register(string $modelClass, array $tile, int $sort = 100)` | Contribute a tile for every relation box hosting `$modelClass` |
| `for(string $modelClass)` | All contributed tiles for the class, including tiles registered for a parent class (a project-level subclass inherits its base's tiles), sorted |

A tile mirrors the YAML relation shape — `label` (translation key), `heroicon`, `component` and/or
`route`, `arguments` with the `$modelId` token — plus two keys YAML cannot express:

| Key | Description |
|-----|-------------|
| `count` | `Closure(Model): int` — for counts the host model has no relation method for. Without it, the YAML `relation:` method lookup applies (missing method → `0`) |
| `visible` | `Closure(Model): bool` — return `false` to drop the tile, e.g. when the tenant lacks the contributing app |

Closures are resolved inside the relation box at render time and never become Livewire state; counts
re-resolve on `closeTopModal` like YAML tiles.

**Example** — an invoicing module plugs its documents into the customer page's relation box
(the customer module never learns about invoicing; `Customer` has no `invoices()` relation by
design):

```php
use Noerd\Helpers\AccessHelper;
use Noerd\Services\RelationBoxRegistry;

app(RelationBoxRegistry::class)->register(Customer::class, [
    'label' => 'Invoices',
    'heroicon' => 'document-currency-euro',
    'route' => 'invoicing.invoices',
    'component' => 'invoicing::invoices-list',
    'arguments' => ['customerId' => '$modelId'],
    'count' => fn(Customer $customer): int => Invoice::where('customer_id', $customer->id)->count(),
    'visible' => fn(): bool => AccessHelper::canUseApp('INVOICING'),
], sort: 10);
```

- The count/visible closures run per render — keep the queries cheap; `BelongsToTenant` models need
  no manual tenant scoping
- A page whose only tiles are contributed still renders its relation box — the
  `<x-noerd::detail-relations>` guard consults the registry

## DetailSlotsRegistry

Named extension slots inside detail forms: an optional module contributes a Livewire component to a
slot name, the hosting detail only marks the position with `<x-noerd::detail-slot>`. The core ships
the slot and stays agnostic of whoever fills it — a registration ceases to exist with its module.

**API** (`Noerd\Services\DetailSlotsRegistry`):

| Method | Description |
|--------|-------------|
| `register(string $slot, string $component, int $sort = 100)` | Contribute a Livewire component to a slot; lower `sort` renders first, equal sorts keep registration order |
| `for(string $slot)` | The component names registered for a slot, sorted |

**Marking a slot** in a detail blade (a slot may sit anywhere in the form — typically in a tab slot
below the YAML fields):

```blade
<x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId">
    <x-slot:tab1>
        <x-noerd::detail-slot name="item-below-form" :modelId="$modelId" />
    </x-slot:tab1>
</x-noerd::tab-content>
```

**Registering** from a module service provider's `boot()`:

```php
use Noerd\Services\DetailSlotsRegistry;

public function boot(): void
{
    app(DetailSlotsRegistry::class)->register('item-below-form', 'inventory::item-stock-levels', sort: 10);
}
```

Every registered component is mounted with two parameters: the host's `modelId` and
`hostComponent` — the host's Livewire component name. The latter lets a slot child defer its own
persistence until the host actually saved, by listening for the host's
`detailStored-{hostComponent}` event (see the [store roundtrip](page-view.md#generic-store-roundtrip)):

```php
public function getListeners(): array
{
    return ['detailStored-' . $this->hostComponent => 'hostStored'];
}

public function hostStored(int $modelId): void
{
    // persist the draft state now that the host record exists / was saved
}
```

**Important:**

- Children are mounted with stable keys (`detail-slot-{name}-{component}`) and mount-only
  parameters, so re-renders of the host leave the child DOM untouched
- Quick-create dialogs stay slim and render no slots; a slot outside a Livewire host renders nothing
- Slot children holding unsaved draft state lose it when the modal stack updates (children are
  re-mounted) — hosts should not open nested modals around a filled slot
- The shipped host is the setup app's user detail (`noerd::noerd-user-detail`, slot
  `user-below-form`); mechanics: `tests/Feature/DetailSlotRenderTest.php`

## ComponentAccessGuard

An allow-list of components that may only be mounted by a tenant admin. Setup screens are normally
protected by the `setup` route middleware (`SetupMiddleware` → `NoerdUser::isAdmin()`), but two
entry points mount a component **without** ever passing through that middleware:

- the client-dispatchable `noerdModal` event — the modal stack takes the component name straight
  from the browser
- the generic component page `/noerd/component-page/{componentName}`

Both call `ComponentAccessGuard::authorize()` before instantiating the component, which re-asserts
admin access for every name on the allow-list. A module that ships admin-only screens must
therefore register them — an unregistered admin component is reachable through those two seams by
any authenticated user of the tenant.

**API** (`Noerd\Support\ComponentAccessGuard`, static):

| Method | Description |
|--------|-------------|
| `registerAdminComponents(array $componentNames): void` | Add module-owned admin components to the allow-list; idempotent |
| `allows(?string $componentName): bool` | Whether the current user may mount the component (`true` for every name not on the list, and for `null`) |
| `authorize(?string $componentName): void` | `abort(403)` when `allows()` is false |

**Registering** from a module service provider's `boot()`:

```php
use Noerd\Support\ComponentAccessGuard;

public function boot(): void
{
    ComponentAccessGuard::registerAdminComponents([
        'plus::user-roles-list',
        'plus::user-role-detail',
    ]);
}
```

**Important:**

- Matching ignores the namespace prefix and collapses every spelling Livewire resolves to the same
  component (`noerd::tenants-list`, `tenants-list`, `.tenants-list`). Consequence: a host component
  whose bare name collides with a registered one becomes admin-only too — the deliberate
  fail-closed choice
- The guard only closes the admin bypass at the dynamic-mount seams; components that are not on the
  list are still governed by their own route middleware and the object gates
  (see [permissions.md](permissions.md))
- The core's own setup screens are on the list already and are kept in lockstep with the
  `['noerd', 'setup']` route group; mechanics: `tests/Feature/DynamicMountTest.php`

## Overridable Null Bindings

Two contracts are bound to inert default implementations so the core works without the optional module that provides the real one. A module takes over by rebinding the contract in its service provider's `register()`.

### MediaResolverContract

Resolves media IDs to URLs for `type: image` fields and handles plain file uploads when no media module is installed.

**Contract** (`Noerd\Contracts\MediaResolverContract`):

| Method | Description |
|--------|-------------|
| `getPreviewUrl(int $mediaId): ?string` | Preview URL for a media item |
| `exists(int $mediaId): bool` | Whether the media item exists |
| `getRelativeUrl(int $mediaId): ?string` | Relative URL (without domain) |
| `storeUploadedFile(mixed $uploadedFile): ?string` | Store an upload, return its relative URL |
| `isAvailable(): bool` | Whether the full media module is available |
| `pickerComponent(): ?string` | The list component opened as the media picker (with the `selectMode`, `selectContext`, `selectToken` arguments; it answers with the `mediaSelected` event), or `null` when no library exists |

**Default:** `Noerd\Services\NullMediaResolver`, bound with `singletonIf` — every ID lookup returns `null`/`false`, `isAvailable()` is `false`, `pickerComponent()` is `null`, and `storeUploadedFile()` falls back to a plain `store('uploads', 'public')` upload. The image field checks `isAvailable()` to decide between the media picker and the plain upload UI (see [Field Types → image](field-types.md#image)).

**Rebinding** — the media module binds the real resolver in its `register()`; because the core uses `singletonIf`, the module's binding wins regardless of provider order:

```php
public function register(): void
{
    $this->app->singleton(
        \Noerd\Contracts\MediaResolverContract::class,
        MediaResolver::class,
    );
}
```

### Layout overrides (`noerd.layout-overrides` binding)

Applies user/tenant layout overrides to a freshly parsed YAML config. `StaticConfigHelper` consults the optional container binding `StaticConfigHelper::LAYOUT_OVERRIDES_BINDING` (`'noerd.layout-overrides'`) right after `Yaml::parse()` for every list, detail and page config — the core itself never knows which package (if any) stores overrides.

**Shape** — there is no interface; bind any object exposing these three methods:

| Method | Description |
|--------|-------------|
| `apply(string $viewType, string $component, array $config, ?string $modelClass = null): array` | Return the config with overrides applied (`$viewType` is `list`/`detail`/`page`; `$modelClass` is the rendered model when the caller knows it) |
| `listViews(string $component): array` | Additional list views (`key => title`) that exist only in the hook's own storage, with no `{component}--{key}.yml` file behind them — merged into `StaticConfigHelper::getListViews()` |
| `filterListViews(string $component, array $views): array` | Drop the views the current user may not see; called as the final step on the complete cross-app view map |

**Default:** nothing is bound — every list and detail renders straight from its YAML.

**Binding** in a layout-override package's provider:

```php
$this->app->singleton(
    \Noerd\Helpers\StaticConfigHelper::LAYOUT_OVERRIDES_BINDING,
    MyLayoutOverrides::class,
);
```

**Important:**

- All three methods are required — the core calls them without `method_exists` guards
- `apply()` runs for every config parse — an implementation must be fast and return the array unchanged when it stores nothing for the component
- `filterListViews()` receives plain view keys for the current app (`default`, `vip`) and composite `{app}::{key}` keys for other apps; restrictions stored app-agnostic must match on each entry's plain `key`

### Profile registry

Additional user profiles are registered on the `ProfileRegistry` singleton; their semantics come
from the authorization gates the module defines. See
[permissions.md → Registering additional profiles](permissions.md#registering-additional-profiles).

### Authorization gates

The generic chrome consults six **optional** Laravel gates, wrapped in `Noerd\Helpers\AccessHelper`. An undefined gate falls back to the **profile baseline** (see `docs/permissions.md`): Admin/User profiles — and users without a profile — may do everything, ReadOnly may only read and open apps. A defined gate decides alone and is expected to incorporate the baseline itself.

| Gate (constant on `AccessHelper`) | Argument | Consulted by |
|-----------------------------------|----------|--------------|
| `noerd.access-app` (`APP_GATE`) | `string $appName` (tenant_apps.name, any case) | App switcher, app tiles, `AppAccessMiddleware`, allowed-app config discovery |
| `noerd.object-read` (`OBJECT_READ_GATE`) | `class-string $modelClass` | Lists (403 + row hiding) and detail mount (denied state) |
| `noerd.object-write` (`OBJECT_WRITE_GATE`) | `class-string $modelClass` | Updating existing records: detail forms render read-only, custom list header actions hidden |
| `noerd.object-create` (`OBJECT_CREATE_GATE`) | `class-string $modelClass` | Creating new records: store() on a new record, list "New …" actions hidden |
| `noerd.object-delete` (`OBJECT_DELETE_GATE`) | `class-string $modelClass` | Delete buttons and bulk delete hidden/blocked |
| `noerd.action` (`ACTION_GATE`) | `string $actionKey` (registered in the `ActionPermissionRegistry`, see [permissions.md](permissions.md#named-action-checks)) | `action-permission:{key}` middleware and manual `canPerformAction()` call sites |

Always go through the helper (`AccessHelper::canAccessApp()`, `::canReadObject()`, `::canWriteObject()`, `::canCreateObject()`, `::canDeleteObject()`, `::canPerformAction()`) — it short-circuits null arguments and applies the baseline for undefined gates. Detail/page components additionally expose `canSaveObject()`, which picks create (new record, no `$modelId` yet) or write (update) for the form's current state — the save button, save shortcut and readonly rendering key off it.

**Defining a gate** (e.g. in a service provider's `boot()`):

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Noerd\Helpers\AccessHelper;

Gate::define(AccessHelper::APP_GATE, function (?Authenticatable $user, string $appName): bool {
    return $appName !== 'RESTRICTED_APP';
});
```

**Important:**

- The `$user` parameter MUST be nullable (`?Authenticatable`) — some call sites (config discovery, unauthenticated rendering) run for guests, and a non-nullable closure silently denies every guest check
- The gate user is resolved from noerd's own auth guard (`AccessHelper` checks via `Gate::forUser(NoerdAuth::user())`), never from the host application's default guard — see `docs/auth.md`
- Once a gate is defined, host-app `Gate::before`/`after` hooks apply to it (standard Laravel semantics); undefined gates are never touched
