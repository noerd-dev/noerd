@verbatim
## Noerd Framework

Noerd is a YAML-driven modular framework for Laravel applications: list components (`*-list`),
detail components (`*-detail`), pages (`*-page`) and modals are configured through YAML files in
`app-configs/{app}/` and rendered by generic Livewire traits (`NoerdList`, `NoerdDetail`, `NoerdPage`).
Tenant apps and their modules live in `app-modules/{module}/`.

This block holds the HARD RULES. The how-to lives in the package documentation
(`vendor/noerd/noerd/docs/*.md`, or `app-modules/noerd/docs/*.md` for a git submodule) and in the
Boost skills (`noerd-list-development`, `noerd-detail-development`, `noerd-modal-development`,
`noerd-module-development`, `noerd-testing`). Every `docs/…` reference below points into that
folder — read the page before building the feature. Paths such as `src/…`, `tests/…` and
`resources/…` are relative to the noerd package root.

### Core Rules
- **Slim lists.** A list declares `public $listModel = Model::class;`, `public ?string $detailRoute = '{app}.{entity}.detail';`
  (mandatory for every list whose rows open a record) and `public $detailComponent = 'module::x-detail';`
  as the fallback — nothing else; `mount()`, `listAction()`, `listData()`, `renderingNoerdList()` come
  from `NoerdList`. Eager loads, relation counts and the parent scope of an embedded list are
  DECLARED: `protected array $listWith = ['category'];`, `$listWithCount = ['items'];`,
  `$listScopes = ['party_id' => 'partyId'];`. Only for extra wheres or row transformations override
  `listData()`: `$this->listQuery($this->listModel)` → `$this->paginateList($query)` (never
  `->paginate($this->perPage)`) → `return $this->buildList($rows);`. `listData()` is the ONE source
  of rows and config (a `with()` never returns `listConfig`); extra view data stays in a slim `with()`.
  `listQuery()` applies search, sort, header filters and column filters — a hand-built query gets none
  of these and renders no filter funnels; it is only acceptable for rows not backed by one Eloquent
  model. A hand-written "contains" goes through `ColumnFilterParser::applyLikeContains($query, $field, $value)`
  (sqlite has no default LIKE escape), a "has a value" check through `ColumnFilterParser::applyNotEmpty()`.
  Reference: `docs/list-view.md`, stub `src/Commands/stubs/resource/list.blade.stub`.
- **Slim details** follow `src/Commands/stubs/resource/detail.blade.stub` (see "Detail Component Structure").
- Components sit directly in the module's `resources/views/components/` (no subfolders); lists are
  named `{entities}-list.blade.php` (plural), details `{entity}-detail.blade.php` (singular).
- **Modals.** Every backend modal is the noerd modal system — never a hand-rolled overlay or an Alpine
  `x-show` dialog. Open with `Noerd::modal('{module}::{component}', [...])` / `$modal(...)`, implement
  the modal as its OWN Livewire component, close with `$dispatch('closeTopModal')`, hand results back
  through a custom event the opener listens for with `#[On('...')]`. Reference: `docs/modal.md`.
- **Route modal vs. component modal.** A modal showing ONE addressable record opens by ROUTE
  (`Noerd::modalRoute('{app}.{entity}.detail', ['modelId' => $id])` / `$modalRoute(...)`): the URL is
  rewritten to the record and restored on close, so the link is shareable. Conditions: the target uses
  `NoerdDetail`/`NoerdPage` (or `NoerdList` for a record that IS a list), a named
  `Route::livewire('{app}/{entity}/{modelId}', …)` exists for exactly that component, and every
  identity-bearing argument is a parameter of that route (`relations`/`quickCreate` are chrome).
  Everything else stays `Noerd::modal()`: action dialogs (`*-modal`, `*-confirmation`, `*-review`,
  `*-import`), pickers (`listActionMethod`, `selectMode`, `multiSelect`, `returnsSelection`, `context`)
  and lists narrowed by a parent record (a route only with `rewriteUrl: false`). **A list row click
  ALWAYS opens its record by route** (`$detailRoute` + `$detailComponent`) — never override
  `listAction()` to open something without a URL; read-only data still gets its own `*-detail`
  (`readonly: true` fields, no-op `store()`, `protected bool $deletable = false;`, related rows via `lists:`). Always keep the
  component as fallback (`route:` + `modalComponent:`, `newRoute:` + `newComponent:`;
  `Noerd::modalFor($route, $component, $args)` is the canonical PHP shape). Reviewer's tests: *does the
  address bar point at the record after the click?* and *does the URL show the same thing in a fresh tab?*
  Route mode in YAML/properties: `$detailRoute`; list action `route:`; detail action `route:`;
  relation-box tile and widget `route:` (no rewrite); column `type: relation_link` `route:`; relation
  field `detailRoute:`; navigation `modalRoute:`/`newRoute:` (plain `route:` navigates); tab
  `modalRoute:`; dashboard card `route=`.
- **Modal chrome.** A modal body is `<x-noerd::page>` with the title in `<x-slot:header>` via
  `<x-noerd::modal-title>`, the buttons in `<x-slot:footer>`, and the body wrapped in
  `<x-noerd::tab-content>` — for a custom body `<x-noerd::tab-content :layout="[]" :modelId="$modelId" :showBlock="false">`
  with the fields in `<x-slot:tab1>`. The page chrome owns the vertical spacing (no extra `pb-*`);
  a full-bleed page opts out with `:bodyPadding="false"`; a list host's body carries no padding.
- **Dropdown menus** are `<x-noerd::action-menu>` + `<x-noerd::action-menu-item>` (+ `-separator`),
  never another `x-data="{ open: false }"` + `role="menu"` panel; a record's menu goes into the
  `actions` slot of `<x-noerd::tabs>`. Only a popover carrying a form keeps its own Alpine scope.
  Reference: `docs/action-menu.md`.
- **File uploads** are the shipped `<livewire:noerd::dropzone wire:model="files" :rules="[...]" multiple />`,
  never a hand-rolled `WithFileUploads` drop target. The dropzone uploads in batches that fit PHP's
  `max_file_uploads`/`post_max_size` (`Noerd\Support\UploadLimits`) — never work around an upload limit
  in a module. Reference: `docs/detail-view.md` ("Upload limits").
- **Change history** is the shipped `noerd::audit-modal` (`Noerd::modal('noerd::audit-modal', ['modelClass' => …, 'modelId' => …])`
  or a detail action with `modalComponent: noerd::audit-modal`); requires `owen-it/laravel-auditing`
  and an `Auditable` + `BelongsToTenant` model. Never a per-module history screen. Reference: `docs/audit-log.md`.
- **Two YAML copies, always in sync:** the project's `app-configs/{app}/…` AND the module's
  `app-modules/{module}/app-configs/{app}/…` (lists, details, pages, settings, `navigation.yml`). The
  module folder is always named `app-configs/`. List YAMLs live flat in `lists/` (a dotted component
  name `booking::bookings.types-list` resolves `lists/types-list.yml`); only detail YAMLs map dots to
  subfolders. Tests and migrations live in the module, never in the project root.
- **Icons are heroicons:** navigation `heroicon: cog-6-tooth`; tenant-app icons stored as
  `heroicon:outline:{name}` (`getAppIcon()` of the install command). A module ships no icon file;
  only when no heroicon fits, a Blade icon `{module}::icons.app`.
- Every app ships its own dashboard (`noerd:make-app` / `noerd:make-module` scaffold it; the app tile
  opens it, never a list). Record types are added with `noerd:make-resource {Model} --app={app}`
  (`--only=list|detail`) once model and migration exist; for a module app the generators write into
  the module (`{app}::` components, `routes/{app}-routes.php`, both YAML copies).
- Code comments, Artisan prompts and documentation are English. Tests are Pest. YAML is block style
  (never `{ key: value }` / `[a, b]`).
- **Default sort and header filters are configuration:** `defaultSort:` (`field:`, `direction:`) and
  `filters:` (`label`, `column`, `type: picklist|years|model|dateFrom|dateUntil`, `options` /
  `optionsMethod` / `model` + `labelColumn`, `default`) in the list YAML, both copies. `listQuery()`
  applies them — never call `applyListFilters()`, never set `$this->sortField`/`$this->listFilters[...]`
  in `mount()`/`rendering()`. A filter that needs PHP is a `get{Name}ListFilter()` method returning
  `Noerd\Support\ListFilter` (`::options()`, `::model()`, `::years()`, `::dateFrom()`/`::dateUntil()`;
  `->apply(fn (Builder $query, string $value) => …)` for a virtual column, `->manual()` when the
  component reads the value itself). Reference: `docs/list-filters.md` ("Header Filters").

### Config, noerd:install and noerd:update
- Config keys come from the package (`config/noerd.php`); the host copy is published by `noerd:install`
  and refreshed by `noerd:update` (which also re-publishes the frontend assets — the provider never
  publishes on boot). Never read `env()` outside config files — `config('noerd.…')`.
- After a package upgrade run `php artisan noerd:update` and `noerd:update-all` (idempotent). With
  Laravel Boost installed they register the packages in `boost.json` and run `boost:update` — never
  edit the generated `<laravel-boost-guidelines>` block by hand.

### Install and Update Command Required for Every Module
- Every module ships `noerd:install-{module}` AND `noerd:update-{module}`; `noerd:update-all` discovers
  the latter by name, a missing one silently drops the module from the project-wide update.
- Tenant app (has `app-configs/{module}/navigation.yml`): the install command `extends Command`, uses
  `Noerd\Commands\Concerns\HasModuleInstallation` and implements `getModuleName()`, `getModuleKey()`,
  `getAppIcon()`, `getAppRoute()`, `getSourceDir()` (`getDefaultAppTitle()` defaults to the module
  name). NO own `handle()`, NO own options — the trait runs the installation (or the update for the
  `noerd:update-{module}` subclass, which carries only signature and description) and adds `--force`,
  `--migrate`, `--build`, `--scaffold`. A support module (no tenant app) uses
  `Noerd\Commands\Concerns\InstallsNoerdModule` with `getModuleName()` only.
- Publishing is DECLARED, never coded: `getConfigFiles()` (an update never overwrites an existing
  config), `{module}/app-configs/setup/`, `getAppConfigsDir()`, `getAdditionalSubdirectories()`;
  extras (the auditing migration via `PublishesAuditMigration`, a host-config patch) go into
  `publishModuleExtras(bool $update)`; the module's own idempotent steps (`ensureSetupNavigation()`,
  `ensureQuickMenuButton()`, `ensureDashboardWidget()`, seeds) into `ensureModuleSetup()`, which runs
  on install AND update and must ask nothing. Never a hand-rolled `publishConfig()`, copy loop,
  migration `confirm()` or `exec('composer require …')`.
- Nothing migrates or builds implicitly (`--migrate` / `--build`). `ensureNoerdInstalled()` installs the
  base package on the fly for `noerd:install*` commands; every other command aborts with the hint.
- The tenant app is registered ONE way: the stub `app-configs/stubs/add_{module}_tenant_app.php.stub`
  published into the host's `database/migrations/`. Never a registering migration in the module.
- The service provider extends `Noerd\Providers\NoerdModuleServiceProvider` and declares
  `protected string $moduleKey` (views, `{key}::` Livewire namespace, translations, migrations,
  `routes/{key}-routes.php`), `protected array $commands`, `protected array $configs`; bindings in
  `registerModule()`, boot logic in `bootModule()`, registrations in `relationFields()` /
  `fieldTypes()` / `picklists()`. Never repeat the plumbing by hand and never call
  `Livewire::addLocation()` — bare names collide across modules.
- Required modules are declared with `getRequiredModules(): ['MEDIA' => 'noerd:install-media']`: they
  are installed first as a silent dependency and assigned to the same tenants additively. Never
  `Artisan::call()` a sibling installer. npm runs ONCE at the end of the installation the user started
  (`askForNpmBuild()` / `finishDeferredNpm()`); one installation ends with one "ready" callout.
- `vendor:publish` ignores a moved base path — guard every publish with
  `PublishesNoerdContent::publishTargetsCurrentInstallation($tag)`; an install-command test never moves a
  run-wide directory (`storage/fonts`, `public/`) aside.
- Reference: `docs/creating-modules.md`, the stubs in `src/Commands/stubs/module/`.

### Eloquent Models: $guarded instead of $fillable
- Never `$fillable`. `protected $guarded = [];` (everything mass-assignable) or only the sensitive
  fields (`['is_admin', 'role']`); replace `$fillable` when touching a model.
- Never Laravel's throwing `encrypted` cast for a stored third-party secret: after an `APP_KEY`
  rotation every page touching the row dies. Use a `CastsAttributes` cast that catches
  `DecryptException` and returns `null`, so the module degrades to "not configured".

### Tenant Context (BelongsToTenant)
- A tenant-scoped model (`BelongsToTenant`) is filtered by the `TenantContext` or the signed-in noerd
  user's selected tenant. Where neither is known — console, queue, public frontends, token APIs,
  webhooks — a query or a create without `tenant_id` THROWS `MissingTenantContextException`. There is
  no setting to loosen this, and noerd is always multi-tenant. A website or employee guard's user is
  no noerd user.
- Code without a user names its tenant: the record's tenant → `TenantContext::run($model->tenant_id, fn () => …)`
  (jobs dispatched inside inherit it); a cron over all tenants → the selecting query `->acrossTenants()`,
  the work per row in `TenantContext::run($row->tenant_id, …)`; a public frontend / token API → its
  middleware calls `TenantContext::set()` (persistent for Livewire); one query for a known tenant →
  `->ofTenant($id)`; tenant-less data (platform mail log rows) → `TenantContext::system()`, never around
  code that dispatches jobs or creates tenant data.
- `ofTenant()` / `acrossTenants()` lift ONLY the tenant filter. `withoutGlobalScopes()` also drops soft
  deletes and the read guard and does not reach eager loads, `whereHas` or lazy relations — those stay
  scoped and throw. Reference: `docs/traits.md` ("Tenant context").

### Module Independence
- Every module in `app-modules/` is independent: no `use` of another optional module's classes, views
  or YAML; seeders, migrations, factories and tests live in the module; no module code in the host's
  `DatabaseSeeder`.
- The user fixture of a module test is `actingAsNoerdUserForApp('{APP}')` / `createNoerdUserForApp()`
  (tenant + user + the `tenant_apps` row assigned through `Tenant::assignApp()`, tenant and app
  selected); a module trait under `tests/Traits/` holds only module-specific fixtures on top of it.
- Every module ships ONE boundary test, `assertModuleBoundary(dirname(__DIR__, 2))` (generated by
  `noerd:make-module`); allowed packages (with their reason) and forbidden namespaces live in
  `composer.json` under `extra.noerd.boundary`, never in the test.

### Permissions, Profiles & Action Checks
Authorization is generic and two-staged (`docs/permissions.md`, `docs/extension-registries.md`
"Authorization gates") — never hand-roll per-module checks:
- ONE profile per user and tenant: `Noerd\Enums\Profile` (Admin/User/ReadOnly) in
  `users_tenants.profile_key`, hardcoded, no CRUD; the baseline when no gate is defined. Modules
  register further profiles via `app(ProfileRegistry::class)->register(key, label)`.
- Above the profiles: `noerd_users.super_admin` (`isSuperAdmin()`), set only on the console
  (`noerd:super-admin {id|email}`, `--revoke`). Whether a user may work in a tenant is
  `NoerdUser::canAccessTenant()` / `accessibleTenants()`, never `$user->tenants`.
- Assigning an app to a tenant is a SUPER ADMIN decision (a tenant admin only reorders and hides).
  System flows assign ALWAYS through `Tenant::assignApp($app, $pivot)` — never
  `tenantApps()->attach()`/`sync()`: it fires `Noerd\Events\TenantAppAssigned` once and is a no-op for
  a held app.
- Every check goes through `Noerd\Helpers\AccessHelper` (`canAccessApp`, `canReadObject`,
  `canWriteObject`, `canCreateObject`, `canDeleteObject`, `canPerformAction`, `canUseApp`); create and
  write are separate (`canSaveObject()` on details/pages drives the save chrome and the
  `store()`/`delete()` guards, `WriteGuardHook` included). Never a manual `isAdmin()` for object access.
- Models read by hand-built queries opt into the query-level read guard with
  `use Noerd\Traits\GuardedByObjectPermission;` (deliberately opt-in).
- Admin-only components are registered with `Noerd\Support\ComponentAccessGuard::registerAdminComponents([...])`
  in the provider's `boot()` — route middleware does not cover the `noerdModal` event and
  `/noerd/component-page/{componentName}`. The guard matches namespace-aware; give an admin-only
  component a name unique across modules anyway.
- Never bind a locked property to `wire:model`, never pass `detailModel`/`listModel`/
  `objectPermissionModel`/`settingsModels`/`pageLayout`/`listView` as modal arguments
  (`LockedPropertiesHook`); switches like `enableCsvExport` are set in `mount()`. A value rendered into
  a Livewire directive argument is JavaScript: `{{ \Illuminate\Support\Js::from($value) }}`, never `'{{ $value }}'`.
- An operation beyond CRUD is a NAMED ACTION: `app(ActionPermissionRegistry::class)->register('{module}_{action}', 'Label')`
  in `boot()`, guarded by the `action-permission:{key}` middleware or `AccessHelper::canPerformAction()`.
  Always register what you check.

### Quick-Menu Buttons Are App-Independent
- The quick-menu (`app-configs/quick-menu.yml`) is tenant scoped: a button renders the same whatever
  app is selected. A quick-menu component NEVER reads `TenantHelper::getSelectedApp()`.
- Buttons and dashboard widgets declare `app:`/`apps:`; they render only when one of the apps is
  assigned AND `AccessHelper::canUseApp()` allows it. Never a per-module "tenant has app X" gate; on
  routes use `app-access:{app}`. A per-app target becomes one button per app the tenant runs.

### Custom Attributes (Project-Specific Fields)
- Modules are NEVER modified for one project's fields — neither code nor YAML. Project fields live in the
  `custom_attributes` JSON column: the column migration in the PROJECT root, the
  `'custom_attributes' => 'array'` cast in the module model, rename migrations in the module.
- Read as `$model->custom_attributes['my_key']` / `$this->detailData['custom_attributes']['my_key']` in
  project-level views that override the module's.
- An input the Blade binds without a layout field (`wire:model="detailData.custom_attributes.my_key"`)
  MUST be declared in `additionalDetailFields()`: the client may only change what the layout or that
  list declares, a `readonly: true` field keeps the server's value (`ClientWriteGuardHook`), and a
  component without any declared field persists nothing through the default `store()`.

### Detail Component Structure (Slim `$detailModel` Syntax)
- A model-backed detail declares `public $detailModel = Model::class;` and
  `public ?string $detailPrimary = '{entity}Id';` as LITERAL property defaults (the object catalog and
  the layout editor parse them with a regex) — and nothing else: `mount()`, `store()`, `delete()` come
  from `NoerdDetail`. Never a `DETAIL_CLASS` constant, never a redeclared `$modelId` or a `#[Url]` on
  it, never a shared partial named `*-detail.blade.php`.
- Deviating logic overrides the method: a custom `mount()` starts with `$this->initDetail()`, a custom
  `store()` ends with `$this->storeProcess($model)`, a custom `delete()` with
  `$this->closeModalProcess($this->getListComponent())`. A screen whose records are never deleted
  declares `protected bool $deletable = false;` (button, shortcut and `delete()` disappear at once) —
  never an empty `delete()` override, never a YAML key.
- Values derived from the loaded record are read in `protected function detailLoaded(Model $model): void`
  (runs with `$detailData` filled, for an existing record — eager-loaded with `protected array $detailWith`
  — and the fresh model of a new one), NEVER by re-finding the record in `mount()`. A preselected
  relation (`preselect('customer_id')`) needs no `{x}Selected()` handler.
- The Eloquent model is never a component property; the form state is `$detailData` (YAML fields are
  `name: detailData.{column}`). Module-contributed header actions mount top-right of the first form
  block (`ComponentSlotsRegistry`, slot `DETAIL_HEADER_ACTIONS`); a detail's own buttons for that row go
  into the `blockActions` slot of `<x-noerd::tab-content>`.
- Settings/config screens without a single backing model are exempt (see "Settings Pages").
- Reference: `docs/detail-view.md`, stub `src/Commands/stubs/resource/detail.blade.stub`.

### Detail YAML Features
Every feature below is generic and lives once in the core — never duplicate it per module. Detail
YAMLs are pure model forms: `title`, `description`, `theme`, `quickCreate`, `tabs`, `fields`,
`actions`, `lists`, `positions`. `widgets:` and `relations:` belong to the page YAML.
- **Tabs:** `tabs:` (`number`, `label`) plus `tab:` on the fields (missing = tab 1). Hand-rolled panels
  use `<x-noerd::tabs>` + `<x-noerd::tab-panels>` / `<x-noerd::tab-panel :number>` (optional `:show`
  Alpine expression), never a bare `x-show` div; the fields of a tab are filtered in the Blade.
  Reference: `docs/detail-view.md` ("Tab Properties", "Hand-Rolled Tab Panels").
- **Actions:** `actions:` renders a button row automatically through `<x-noerd::page>` (no Blade line;
  opt out with `:detailActions="false"` and render `<x-noerd::detail-actions>` yourself). Keys: `label`,
  `action` | `route` (+ `modalComponent` fallback) | `url` (literal or a key of `detailActionUrls()`),
  `arguments`, `viewExists`, `heroicon`, `confirm`, `requiresId` (default true), `showIf`/`showIfNot`
  (Alpine `x-show`, both combine with AND). A foreign module contributes a button purely via YAML.
  Reference: `docs/detail-view.md` ("Detail Actions").
- **Conditions:** `showIf`/`showIfNot` take a property path (truthy) or `field`/`value` (compared as
  strings); fields, tabs and actions share the one expression (`Noerd\Support\FieldVisibility`), and a
  hidden field is not validated. Reference: `docs/field-types.md` ("Conditional Display").
- **Arguments:** every `arguments:` map (lists, widgets, relation tiles, actions, tabs) resolves
  `$modelId`, `$property` (a public property of the hosting component) and static values — once, in
  `Noerd\Support\LayoutArguments`.
- **Embedded lists:** `lists:` (`title`, `description`, `component`, `arguments`) rendered by
  `<x-noerd::detail-lists :layout="$pageLayout" :modelId="$modelId" />` after `<x-noerd::tab-content>`;
  dynamic cases use `<x-noerd::detail-list component= :arguments= lazy :wireKey=>` directly. Both
  render compact (no header, no pagination, first `perPage` rows) and only once the record is saved.
  A list embedded with `disableModal` breaks out of its host by `--noerd-page-inset` — never re-pad
  it; a full page nested in a page is detected (`isNestedPage`) and embedded plainly.
  Reference: `docs/detail-view.md` ("Embedded Lists").
- **Relation fields:** `type: {x}Relation` is a REGISTERED type (`RelationFieldRegistry::register('customerRelation', RelationFieldDefinition::model(listComponent:, detailRoute:, modelClass:, titleResolver:))`
  in the owning provider — the generic `type: relation` does not exist). The field keeps
  `detailData.{field}` and `relationTitles.{field}` in sync itself; a foreign key maps to its type by
  convention (`customer_id` → `customerRelation`, `RelationFieldRegistry::typeForColumn()`). A detail
  that must REACT to a selection implements `#[On('{entity}Selected')]` (event derived from the list
  name unless `selectEvent` is set) and writes `detailData.*` + `relationTitles.*` — never a separate
  `$this->customer` property. A restyled relation type passes `fieldComponent:` (a class extending
  `Noerd\Livewire\RelationFieldComponent`). Reference: `docs/relation-field-types.md`.
- **Relation forms:** a field may bind into a related model (`detailData.invoiceAddress.address_line_1`)
  when the MODEL implements `Noerd\Contracts\DeclaresRelationForms` (`RelationFormDefinition::make(relation:, fields:, persistUsing:, persistWhen:)`);
  the core hydrates and persists it (`RelationFormPersistHook`) — never hand-roll it, and a hand-rolled
  `store()` strips the payload with `RelationFormSync::strip($modelClass, $this->detailData)`.
  Reference: `docs/relation-forms.md`.
- **Field types:** the YAML `type:` resolves through `Noerd\Services\FieldTypeRegistry`; a module adds
  types in `fieldTypes()` with `FieldTypeDefinition::include('module::components.forms.x', resolver:)`
  or `::livewire(...)`. Unknown plain types render as text; unregistered `*Relation` types throw.
  Reference: `docs/field-types.md` ("Custom Field Types").
- **Defaults are YAML:** `default: <value>` (applied while the value is null; `''`, `0`, `false` are
  answers), a `select` with inline `options` starts on its first option unless `placeholder:` is set
  (`optionsMethod:` selects never default implicitly), date defaults are relative (`today`, `now`,
  `+14 days`, `-1 month`). Never `$this->detailData['status'] ??= …` in `mount()`.
  Reference: `docs/field-types.md` ("Default Values").
- **Spacer:** `type: spacer` with `colspan` keeps a grid cell empty (`fields` auto-flow in a 12-column grid).
- **Highlights:** proposed values get `highlight: true` / `previousValue` stamped onto `$pageLayout` at
  runtime with `Noerd\Support\LayoutFields::map()`, never written into the shipped YAML.
- **Another component's form:** a review or preview screen renders the TARGET's YAML with
  `StaticConfigHelper::getComponentFields('accounting::expense-detail', Expense::class)` and never
  changes the target; `validateFromLayout()` and relation fields work, the target's PHP
  (`optionsMethod` methods, `*Selected` side effects, `mount()`/`store()` formatting) does not come along
  — gate with the target model's `can*Object()` overrides.
- **Computed values:** a list column or detail field with `method:` shows a public, parameterless model
  method marked `#[Noerd\Attributes\ComputedValue(with: [...])]` (never an `$appends` entry or a
  hand-built `listData()` for display); in a detail it lives in `$computedValues`, never persisted.
  Reference: `docs/computed-columns.md`.

### Position Columns (Line-Item Tables)
- The columns of a position table are CONFIGURATION: `positions.columns` in the detail YAML (both
  copies) is the ONLY source — no code default. The module's `Noerd\Contracts\DefinesPositionColumns`
  catalog holds ONLY the columns the calculation depends on (`PositionColumn::make('amount')->number()->onChange('calcGross')`),
  always rendered and only relabelled/resized/moved by the YAML; `forbidden()` names the logic-bearing
  fields a YAML must never declare. Every other YAML column is a real, non-system column (`type`
  text/number/date/checkbox/select, `options`/`optionsMethod`, `placeholder`, `step`, `readonly`).
- The provider registers `app(PositionTableRegistry::class)->register('{module}::{entity}-detail', Catalog::class, Position::class)`;
  the detail resolves `$columns = $this->positionColumns()` once and passes it to
  `<x-noerd::positions.table :columns>` and every row; rows `use Noerd\Traits\NoerdPositionRow` and
  render `<x-noerd::positions.cells :theme :columns />`. Never hard-code header arrays or cells.
- Tests prove the mechanics with a synthetic catalog and layout, never a shipped YAML.
  Reference: `docs/detail-view.md` ("Configurable Columns").

### Pages, Relation Box and Settings Pages
- A `*-page` (`NoerdPage`; `NoerdDetail` composes it) hosts everything AROUND the form: chrome, tabs,
  relation box, widgets and the embedded slim `*-detail` named by the page YAML
  (`app-configs/{app}/pages/{entity}-page.yml`, keys `title`, `detail`, `quickCreate`, `tabs`,
  `relations`, `widgets`, `fields`; a missing page YAML is fine).
- Embed with `@livewire($pageLayout['detail'], ['modelId' => $modelId, 'embedded' => true], key('embedded-detail'))`.
  Two rules for EVERY embedded Livewire child: the `@livewire()` directive for namespaced components, and
  ONE unconditional root element in the child's view. A component that can live inside a modal keeps
  its `mount()` free of side effects — the modal stack re-mounts its children on every update.
- The save roundtrip is generic: the page dispatches `storeDetail-{detail}` (with `host`), the detail
  answers `detailStored-{detail}`, the page adopts the id and runs the protected
  `afterEmbeddedDetailStored($model)`; live sync via `detailDataUpdated-{detail}` / `syncPayload()`.
  One `<x-noerd::delete-save-bar>` per page. Never a `component:`, `ref`, `el`, `self` or `to` key in an
  event payload (Livewire reserves them).
- **Relation box:** `relations:` tiles (`label`, `heroicon`, `relation` = the count method, `route` (no
  URL rewrite) + `component` fallback, `arguments`) rendered by
  `<x-noerd::detail-relations :layout :modelId :modelClass>`; an unknown relation counts 0.
  Reference: `docs/page-view.md`.
- **Settings pages** edit a TENANT SINGLETON and use `Noerd\Traits\NoerdSettingsPage` with
  `public array $settingsModels = ['detailData' => ModuleSettings::class]` (several models allowed,
  each with a public array property) and the YAML `settings/{component}.yml` (keys `title`,
  `description`, `tabs`, `fields`; no grid, the hidden `settings` theme is forced, no layout overrides,
  no `custom_attributes`). A custom `mount()` starts with `initSettings()`, a custom `store()` ends with
  `validateFromLayout(); persistSettings(); $this->showSuccessIndicator = true;`. The model
  `implements Noerd\Contracts\TenantSingletonContract` and uses `Noerd\Traits\TenantSingleton`:
  `Model::forTenant()` (never null — an unsaved instance with `tenantDefaults()`), `updateForTenant()`,
  `existsForTenant()`; never a module's own `forTenant()`/`firstWhere('tenant_id', …)` and never
  "effective defaults" in `mount()`. Reference: `docs/settings-page.md`.

### List YAML Features
Every feature below is generic in `NoerdList` and the list views — never duplicate it per module.
- **Actions:** `actions:` (`label`, `route:` for the "New …" button or `action:` naming a method
  `(mixed $modelId = null, array $relations = [])`, `heroicon`, `style: secondary`); no key, no button.
  The list header is TWO rows (title row with the buttons, filter row with search, scrolling filter
  strip and pagination summary) and never anything else; the footer repeats the pagination through
  `noerd::components.table.list-pagination-nav`. Reference: `docs/list-view.md` ("Actions").
- **Badges and cells:** a picklist value renders as a translated badge — automatically from the paired
  detail YAML (`{x}-list` → `{x}-detail`, `PicklistBadges`) or by `type: badge` + `options` on the
  column. Every rendering mode (table, grid, widget list, CSV, display theme) formats a cell through
  `Noerd\Support\ListCellFormatter::format($value, $column)` and draws every pill with
  `<x-noerd::badge variant="…">` — never a per-type format or a hand-rolled pill in a module view.
  A column needing its own look keeps its real `type` and names a renderer with `cell:`
  (`app(ListCellRendererRegistry::class)->register('{key}', '{module}::cells.{view}')`, the view gets
  `$value`, `$text`, `$row`, `$column`); never a derived `*_display` field or a type branch in
  `table-cell.blade.php`. Reference: `docs/list-view.md` ("Column Types", "Custom Cell Renderers").
- **Multi-select:** `multiSelect: true` + `bulkActions:` (`label`, `action` — `deleteSelected` is
  generic —, `heroicon`, `style`, `confirm`); list-specific bulk methods read `$this->selectedRecordIds`.
  A picker opens the list with `['multiSelect' => true, 'returnsSelection' => true, 'context' => …]`
  and receives `recordsSelected` (`ids`, `context`). Reference: `docs/list-view.md` ("Multi-Select & Bulk Actions").
- **Grid mode:** `displayMode: grid` + `gridColumns` (1–6) swaps only the rows block; the first
  non-empty column is the card title, a `type: preview` column the picture; filters and sort render as
  a control bar. Never a hand-rolled card grid. Reference: `docs/list-view.md` ("Grid Mode").
- **Compact mode:** the `compact` attribute (`<livewire:{module}::{entities}-list compact disableModal :parentId="$modelId" />`)
  hides header, description and pagination — use it for record-scoped lists, through
  `<x-noerd::detail-lists>` in details. Reference: `docs/list-view.md` ("Compact Mode").
- **List views:** alternate complete configs as `customers-list--vip.yml` siblings (`--` and `::` are
  reserved); the title becomes a switcher listing every allowed app's views; project-only views are
  fine. Reference: `docs/list-view.md` ("Multiple List Views").

### Theme System (`theme:` in Detail Components)
- A detail YAML opts into a form theme with the top-level `theme:` key (per-field and nested-block
  overrides allowed); there is no `view:` or `compact:` key. Built-in themes: `default` (label on top),
  `compact` (label left, tight), `numbered` (official-form rows with numbers; `number:` pins a row),
  `display` (hidden, `textOnly`: values as text — per field, per read-only block or whole layout;
  `hideIfEmpty: true` drops blank rows), `settings` (hidden, forced on settings pages).
- Setup → System Settings sets a per-tenant default (`noerd_settings.detail_theme`, `detail_theme_enforced`,
  fallback `config('noerd.theme')`); the YAML wins unless the admin enforces, which also drops the
  overrides — except the text-only theme, which is a rendering mode. Never apply it to list configs.
- A theme is a FOLDER with element templates and a `theme.yml` (`label`, `hidden`, `textOnly`,
  `gridClasses`, `fullWidthRows`, `numbersRows`, `spacerClass`, `controlClasses`, `controlSize`,
  `buttonClasses`, position-table classes), discovered by `ThemeRegistry` from the project's
  `resources/views/themes/` and every module path registered with `registerPath()`; elements resolve
  `themes::{theme}.{element}` → `themes::default.{element}` → the registered target
  (`ThemeElementResolver`). Create one with `php artisan noerd:make-theme {name}` — never modify the
  shipped folders, never ship a second text theme per module.
- Buttons follow the theme: `x-noerd::button` without `size` uses the theme's `buttonClasses`
  (`ThemeContext`); never hard-code button sizes in form chrome. The display row markup lives once in
  `noerd::components.detail.display-value`; never hand-write read-only rows or fake them with
  `readonly: true` inputs. Theme ≠ brand (`noerd.brand`, `BrandService`).
- Reference: `docs/themes.md`.

### Currency, Numbers & Dates Are Formatted by the Core, Never by Hand
- Three settings decide the output: the TENANT CURRENCY (`noerd_settings.currency`), the TENANT LOCALE
  (`noerd_settings.locale`, how DOCUMENTS are written) and the USER LOCALE
  (`noerd_user_settings.format_locale`, how the backend UI is written). The LANGUAGE only selects
  translations. The locale list is `Noerd\Support\Locales::SUPPORTED` — never add one per project.
- Backend UI: `CurrencyHelper::format($amount)`, `FormatHelper::date()`, `dateTime()`, `time()`,
  `longDate()`, `weekday()`, `monthName()`, `decimal()`, `number()`, `percent()`, `fileSize()`.
  Documents (PDF, receipt, customer e-mail, ESC/POS): `CurrencyHelper::formatForDocument($x, $model->tenant_id)`,
  `FormatHelper::documentDate()` / `documentDateTime()` / `documentDecimal()`. Public frontends without a
  noerd user pass the tenant id (`CurrencyHelper::format($x, $tenantId)`,
  `FormatHelper::date($d, FormatHelper::tenantLocale($tenantId))`). Machine payloads use
  `CurrencyHelper::codeForTenant($tenantId)` with machine-formatted amounts.
- NEVER `number_format($x, 2, ',', '.')`, a literal `€`/`'EUR'`, `->format('d.m.Y')`,
  `Number::currency(..., locale: 'de')`, `->locale('de')`, `Carbon::setLocale()`, `isoFormat('dddd')`
  or `translatedFormat('F')` in module code, templates or translation keys (`__('Buy for :price')`
  with a pre-formatted `:price`). A pattern the helpers do not cover uses `isoFormat()` with
  `->locale(FormatHelper::locale())`.
- List columns `type: currency|date|datetime|time` and the detail field `type: currency` are
  formatted by the core; CSV exports stay plain decimals. Tests normalise ICU spaces
  (`zzNormalizeSpaces()`) and prove mechanics with a `NoerdSettings` row plus the user's `format_locale`.
- Reference: `docs/formatting.md`.

### Tests Must Test Functionality, Not Current Configuration
- The YAML under `app-configs/` is per-installation CONFIGURATION: theme, titles, labels, tabs, field and
  column lists, `required:` flags, the modal target (`route:` vs. `modalComponent:`, `$detailRoute` vs.
  `$detailComponent`). A test asserting their current content is wrong by definition — never
  `assertSeeHtml('data-theme="compact"')` against a shipped detail, never an exact field list.
- Prove the EFFECT with synthetic layouts (`noerd-test::` fixture components), runtime-written fixture
  YAMLs under the testbench skeleton, factories and runtime-registered routes
  (`registerTestLivewireRoute()`: route wins when registered, the component is the fallback).
  References in the package: `ThemeTest`, `StaticConfigHelperFeatureTest`, `ConfigResolutionTest`,
  `NoerdListModalDispatchTest`, `DetailActionsTest`, `NavigationModalRouteTest`, `RelationBoxTest`.
- Architecture guardrails are fine (a detail YAML has no `widgets:`/`relations:`); component wiring the
  code depends on (`pageLayout.detail` and its event names) may be asserted.
- Reference: `docs/testing.md`, skill `noerd-testing`.

### Testing YAML-Driven Detail Forms
- `validateFromLayout()` derives the rules from the YAML: `required` for `required: true`, the rules of
  the field TYPE (`email`, `numeric`, `date`, `boolean`, a select's inline options incl. the stored
  value, `string|max:{varchar length}`) or the field's `validation:` list; readonly and text-only fields
  keep only `required`; a hidden field is not validated. A factory payload must therefore produce values
  of the field types.
- Store-success tests submit a COMPLETE factory payload — `validDetailPayload(Model::class, $overrides)`
  — and override only the asserted fields, or mount an existing record via `modelId`; relation FKs and
  virtual fields are set with `->set()`/`->call('{x}Selected', …)`. Validation tests derive the
  required fields from the live layout: `$component->assertHasErrors(requiredLayoutFields($component))`.
  Components with a hard-coded `$this->validate([...])` keep explicit assertions.
- Factories produce a fully valid record by default (no `optional()` on a column that can be required);
  sparse records are created explicitly in the test.
- The helpers live in the package's `tests/helpers.php`, NOT composer-autoloaded: suites binding
  `Noerd\Tests\TestCase` get them, every other `tests/Pest.php` loads them with
  `\Noerd\Tests\HelperLoader::load();` — never a hard-coded path or a root `composer.json` entry. The
  module fixture (`actingAsNoerdUserForApp()`), the boundary guard (`assertModuleBoundary()`) and the
  update-command proof (`assertModuleUpdateCommandPublishesConfigs()`) live there too; a module with a
  Testbench suite of its own binds `Noerd\Tests\ModuleTestCase` (`$moduleProviders`).

### Translations
- English text IS the key (`__('Invoice')`, YAML `label: Invoice`, Blade `{{ __('Dashboard') }}`); only
  `app-modules/{module}/resources/lang/de.json` maps it (`"Invoice": "Rechnung"`), no `en.json`.
  Entries where English = German may be omitted.
- The JSON namespace is flat across modules — never the same key with a different German value.
  The root `lang/` directory holds only Laravel's framework translations.
@endverbatim
