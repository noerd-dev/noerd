# Extension Registries

Optional modules and projects extend the noerd core through a small set of container singletons. A module registers its contribution from its service provider's `boot()` (or rebinds a contract in `register()`); the core resolves the singleton where the extension point renders. This is registration-based rather than config-based on purpose: an entry in a YAML file outlives the module that wrote it and would have to be guarded against, whereas a registration simply ceases to exist once the module is gone — no config cleanup needed.

Four registries with their own documentation are not repeated here:

- `FieldTypeRegistry` — custom form field types, see [field-types.md](field-types.md)
- `RelationFieldRegistry` — relation field types, see [relation-field-types.md](relation-field-types.md)
- `ThemeRegistry` — form layout themes, see [themes.md](themes.md)
- `HeaderActionsRegistry` — list/detail header actions, see [header-actions.md](header-actions.md)

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

1. A method with that name on the detail component wins (`method_exists`)
2. Otherwise the `PicklistRegistry` provider is called
3. Neither exists → empty options (`[]`), never an error

**Registering** (booking module supplying staff options):

```php
use Noerd\Services\PicklistRegistry;

public function boot(): void
{
    app(PicklistRegistry::class)->register('staffOptions', function (): array {
        $tenantId = auth()->user()->selected_tenant_id;

        return ['' => __('Please select...')] + Staff::withoutGlobalScopes()
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
  - name: detailData.staff_id
    label: Staff
    type: picklist
    picklistField: staffOptions
    colspan: 6
```

**Important:**

- Provider callables run per render — keep the query cheap and tenant-scoped
- A component method with the same name always shadows a registered provider
- PHP code can resolve a provider directly when it needs the options outside a form: `app(PicklistRegistry::class)->resolve('staffOptions')`

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

A module registers its own provider the same way from `boot()`. Reference implementations outside the core: the reservation module (`reservation-object-types`) and the CMS module (`page-collections`, `collections`).

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

**Shipped example** — accounting plugs the party's documents into the party page's relation box
(party never learns about accounting; `Party` has no `invoices()` relation by design):

```php
app(RelationBoxRegistry::class)->register(Party::class, [
    'label' => 'Invoices',
    'heroicon' => 'document-currency-euro',
    'component' => 'accounting::invoices-list',
    'arguments' => ['partyId' => '$modelId'],
    'count' => fn(Party $party): int => Invoice::where('party_id', $party->id)->count(),
    'visible' => fn(): bool => /* tenant has the accounting app + per-app gate */,
], sort: 10);
```

Reference: `AccountingServiceProvider::registerPartyRelationBoxTiles()`.

- The count/visible closures run per render — keep the queries cheap; `BelongsToTenant` models need
  no manual tenant scoping
- A page whose only tiles are contributed still renders its relation box — the
  `<x-noerd::detail-relations>` guard consults the registry

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

**Default:** `Noerd\Services\NullMediaResolver`, bound with `singletonIf` — every ID lookup returns `null`/`false`, `isAvailable()` is `false`, and `storeUploadedFile()` falls back to a plain `store('uploads', 'public')` upload. The image field checks `isAvailable()` to decide between the media picker and the plain upload UI.

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

### Authorization gates

The generic chrome consults four **optional** Laravel gates, wrapped in `Noerd\Helpers\AccessHelper`. An undefined gate allows everything — the core ships no restrictions of its own; a project defines a gate to restrict centrally.

| Gate (constant on `AccessHelper`) | Argument | Consulted by |
|-----------------------------------|----------|--------------|
| `noerd.access-app` (`APP_GATE`) | `string $appName` (tenant_apps.name, any case) | App switcher, app tiles, `AppAccessMiddleware`, `PublicAppMiddleware`, allowed-app config discovery |
| `noerd.object-read` (`OBJECT_READ_GATE`) | `class-string $modelClass` | Lists (403 + row hiding) and detail mount (denied state) |
| `noerd.object-write` (`OBJECT_WRITE_GATE`) | `class-string $modelClass` | Detail forms render read-only, list "New …" actions hidden |
| `noerd.object-delete` (`OBJECT_DELETE_GATE`) | `class-string $modelClass` | Delete buttons and bulk delete hidden/blocked |

Always go through the helper (`AccessHelper::canAccessApp()`, `::canReadObject()`, `::canWriteObject()`, `::canDeleteObject()`) — it short-circuits null arguments and undefined gates.

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

- The `$user` parameter MUST be nullable (`?Authenticatable`) — some call sites (public apps, config discovery) run for guests, and a non-nullable closure silently denies every guest check
- The gate user is resolved from noerd's own auth guard (`AccessHelper` checks via `Gate::forUser(NoerdAuth::user())`), never from the host application's default guard — see `docs/auth.md`
- Once a gate is defined, host-app `Gate::before`/`after` hooks apply to it (standard Laravel semantics); undefined gates are never touched
