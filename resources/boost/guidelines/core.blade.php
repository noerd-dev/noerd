@verbatim
## Noerd Framework

Noerd is a YAML-driven modular framework for Laravel applications: list components (`*-list`),
detail components (`*-detail`), pages (`*-page`) and modals are configured through YAML files in
`app-configs/{app}/` and rendered by generic Livewire traits (`NoerdList`, `NoerdDetail`, `NoerdPage`).
Tenant apps and their modules live in `app-modules/{module}/`.

The full documentation ships with the package in `docs/` (`vendor/noerd/noerd/docs/*.md`, or
`app-modules/noerd/docs/*.md` when noerd is installed as a git submodule). Every `docs/…` reference
below points into that folder — read the referenced page before building the feature. Paths such as
`src/…`, `tests/…`, `stubs/…` and `resources/…` are relative to the noerd package root.

### Core Rules
- When creating new lists/tables, always follow the slim list pattern below (reference: the `noerd:make-resource` stub `src/Commands/stubs/resource/list.blade.stub` and `docs/list-view.md`).
- List components declare their model as `public $listModel = Model::class;` and their detail target
  as `public ?string $detailRoute = '{app}.{entity}.detail';` (preferred — opens the record as a
  route modal and rewrites the URL) plus `public $detailComponent = 'module::x-detail';` as the
  fallback, at the top of the class. The trait
  methods (`mount()`, `listAction()`, `listData()`, `renderingNoerdList()`) are always used from `NoerdList` —
  a slim component contains nothing else (reference: `src/Commands/stubs/resource/list.blade.stub`). Only when custom
  query logic is needed, override `listData()` (reference: `docs/list-view.md`, "Custom Query Logic"): build the query
  via `$this->listQuery($this->listModel)` (chain additional wheres/eager loads and
  `->paginate($this->perPage)`) and end with `return $this->buildList($rows);`. Never leave a custom
  query in `with()` once `$listModel` is declared — the generic trait features (row click,
  select-all, bulk delete) resolve the list via `listData()`. Extra view data (e.g. a footer
  `summary`) stays in a slim `with()` returning only the extra keys, sharing the query with
  `listData()` via a private helper. `listQuery()` applies search, sort and the Excel-style column
  filters from the YAML config — a manually built query gets none of these and renders no filter
  funnels in the header. A manual query (and no `$listModel`) is only acceptable when technically
  required (rows not backed by a single Eloquent model, repository/raw queries) — such lists
  intentionally show no column filters.
- When creating new models/components, always follow the slim detail pattern (reference: `src/Commands/stubs/resource/detail.blade.stub` and `docs/detail-view.md`).
- When creating new components, they must always be placed directly in the livewire folder. Not in subfolders like livewire/setup.
- When creating lists/tables, they should always be named -list.blade.php. For models/components, they should always be named -detail.blade.php.
Example for user: users-list.blade.php (plural) and user-detail.blade.php (singular)
- Modals in the backend must ALWAYS use the noerd modal system. Never build a custom inline/overlay
  modal (e.g. a hand-rolled `fixed inset-0` Tailwind overlay or an Alpine `x-show` dialog) inside a
  Livewire component. Open the modal from a backend Livewire action with
  `Noerd::modal('{module}::{component}', ['key' => $value])` (or the Alpine `$modal('{module}::{component}', {...})`
  magic from the frontend), and implement the modal as its OWN Livewire component. Close it with
  `wire:click="$dispatch('closeTopModal')"`. To hand results back to the opener, dispatch a custom event
  the opener listens for via `#[On('...')]`. Reference: `docs/modal.md` (opening, closing, events).
- **Dropdown menus.** Every menu — a record's action menu, a switcher, a sort menu — uses
  `<x-noerd::action-menu>` with `<x-noerd::action-menu-item>` entries (and
  `<x-noerd::action-menu-separator>` between groups). NEVER hand-roll another
  `x-data="{ open: false }"` + `role="menu"` panel: the chrome, click-outside and escape handling
  live in the component once. The default trigger is a kebab button; the `trigger` slot replaces it
  and toggles the menu with `@click="open = ! open"`. A menu belonging to a record goes into
  the `actions` slot of `<x-noerd::tabs>`, which renders right-aligned inside the tab row. Only for
  a list of choices — a popover carrying a form (filters) keeps its own Alpine scope.
  Reference: `docs/action-menu.md`.
- **Route modal vs. component modal.** A modal that shows ONE addressable record must be opened by
  ROUTE, not by component: `Noerd::modalRoute('{app}.{entity}.detail', ['modelId' => $id])` /
  `$modalRoute(...)`. The route is resolved to the component behind it, the browser URL is rewritten
  to the record (`+ ?modal=true`) and restored on close, so the link is shareable and a reload
  reopens the record as a modal over the previously visited page. All three conditions must hold:
  (1) the target uses `NoerdDetail`/`NoerdPage` (a `*-detail`/`*-page`) or — for a record that IS a
  list, e.g. the object of the custom-attribute manager — `NoerdList`; those traits share the
  `?modal=true` reload contract through `Noerd\Traits\RoutedModal`, `NoerdPage` additionally
  understands the `'new'` sentinel, (2) a named `Route::livewire('{app}/{entity}/{modelId}', …)`
  route exists for exactly that component, (3) every identity-bearing argument is a parameter of
  that route — conventionally `modelId`, but any bound property works
  (`/setup/object-manager/{table}`); `relations`/`quickCreate` are chrome.
  **Everything else stays `Noerd::modal()`/`$modal()`:** action dialogs (`*-modal`, `*-confirmation`,
  `*-review`, `*-import`, `*-editor`), pickers (`listActionMethod`, `selectMode`, `selectContext`,
  `multiSelect`, `returnsSelection`, `context`) and lists narrowed by a parent record. A narrowed
  list MAY use a route for decoupling, but then with `rewriteUrl: false` — a reload of the plain list
  route would show the unfiltered list.
  **Always keep the component key as the fallback** (`route:` + `modalComponent:`, `$detailRoute` +
  `$detailComponent`, `newRoute:` + `newComponent:`): the route wins when registered, the component
  opens when the owning module is not installed. `Noerd::modalFor($route, $component, $args)` is the
  canonical PHP shape. Reviewer's test: *paste the resulting URL into a fresh tab — does it show the
  same thing?* Yes → route, no → component. Full reference: `docs/modal.md`.
- Where the route mode is available in YAML/properties: list row click `public ?string $detailRoute`;
  list header action `route:` (instead of `action: listAction`); detail action `route:` (instead of
  `modalComponent:`); relation-box tile and widget `route:` (no URL rewrite); list column
  `type: relation_link` `route:`; relation field `detailRoute:`; sidebar `navigation.yml`
  `modalRoute:` / `newRoute:` (plain `route:` keeps meaning "navigate"); page/detail tab
  `modalRoute:`; dashboard card `route=`.
- A modal component must use the standard header (and footer) chrome — exactly like the detail
  components — never a free-floating title. Wrap the modal body in `<x-noerd::page>` and put the title in
  a header slot via `<x-noerd::modal-title>`; put action buttons (Save/Cancel) in the `<x-slot:footer>`.
  Do NOT render the title as a bare `<div class="text-xl …">` floating above the content.
- The modal BODY must always be wrapped in `<x-noerd::tab-content>` so the tab/panel structure matches
  the detail components — even when the modal has NO YAML layout. For a custom (non-YAML) body, pass an
  empty layout and disable the YAML block, then put the fields in the `tab1` slot:
  `<x-noerd::tab-content :layout="[]" :modelId="$modelId" :showBlock="false"><x-slot:tab1>…fields…</x-slot:tab1></x-noerd::tab-content>`.
  The vertical spacing itself (gap below the header, gap above the footer) is guaranteed by the
  `<x-noerd::page>` chrome for every non-list component — it never depends on which children render
  (action bar, tabs, YAML block), so custom bodies need NO extra `pb-*` padding. A full-bleed page can
  opt out with `<x-noerd::page :bodyPadding="false">`. A LIST host's body carries no chrome padding
  (the list brings its own spacing); a `<x-noerd::tabs>` bar sitting above the list therefore adds the
  identical gap itself — generic in `tabs.blade.php`, never hand-padded per component.
```blade
<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Mark as Contacted') }}</x-noerd::modal-title>
    </x-slot:header>

    {{-- YAML-driven body: --}}
    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />
    {{-- …or a custom body without YAML: --}}
    <x-noerd::tab-content :layout="[]" :modelId="$modelId" :showBlock="false">
        <x-slot:tab1>
            {{-- form fields --}}
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <div class="ml-auto flex items-center gap-2">
            <x-noerd::button variant="secondary" wire:click="$dispatch('closeTopModal')">{{ __('Cancel') }}</x-noerd::button>
            <x-noerd::button variant="primary" wire:click="save">{{ __('Save') }}</x-noerd::button>
        </div>
    </x-slot:footer>
</x-noerd::page>
```
- When modifying a navigation.yml, ensure that changes are always made to both files: the project copy (`app-configs/{app}/navigation.yml`) and the one in the respective module (`app-modules/{module}/app-configs/{app}/navigation.yml`).
- When modifying YAML files for lists (`*-list.yml`) or details (`*-detail.yml`), ensure that changes are always made to BOTH locations: the project's `app-configs/` directory AND the module's `app-modules/{module}/app-configs/` directory. Both files must be kept in sync.
- The directory holding YAML copy templates inside a module must always be named `app-configs/` (at `app-modules/{module}/app-configs/`). Never use `app-contents/` or any other name — neither for the directory itself, nor in install commands, helpers, tests, or docs.
- List YAML files must ALWAYS live directly in `lists/` (`app-configs/{app}/lists/` and the module copy) — never in subfolders. Nested Livewire component names (e.g. `booking::bookings.types-list`) resolve their list YAML by the flat file name (`lists/types-list.yml`); the dot segments are ignored for lists. Only detail YAMLs may use the dot-to-subfolder mapping.
- When creating tests or migrations, ensure they are always placed in the module under app-modules and not in the main directory.
- When using icons in navigation, always use heroicons. In a yml file it would look like this:
```yaml
        title: Settings
        route: business-hours.settings
        heroicon: cog-6-tooth
```
- Tenant-app icons (`tenant_apps.icon`, the tile on the apps page and in the app bar) are heroicons
  too, stored as `heroicon:outline:{name}` and rendered by `noerd::app-icon`. `noerd:create-app` and
  `noerd:module` ask for the heroicon; a module's install command returns it from `getAppIcon()`. A
  module ships NO icon file. Only when no heroicon fits, add a Blade icon by hand
  (`resources/views/components/icons/app.blade.php`) and return `{module}::icons.app` instead.
- Every app — root app or module — ships its own dashboard: `noerd:create-app` scaffolds
  `{app}-dashboard` (root) or `{module}::{module}-dashboard` (module, via `noerd:module`), and the
  app's main route opens it. Never point a new app's tile at a list.
- Neither `noerd:create-app` nor `noerd:module` generates a model: an app starts with its dashboard,
  and every record type is added with `noerd:make-resource {Model} --app={app}` (list + detail,
  YAML, routes, navigation) once the model and its migration exist. For a MODULE app
  (`app-modules/{app}/composer.json` exists) the `noerd:make-*` generators write into the module —
  components under the `{app}::` namespace, routes into `routes/{app}-routes.php`, YAML and
  navigation into BOTH copies — never hand-copy generated files from the project root into a module.
- Both code comments and Artisan prompts must always be written in English.
- Documentation (README files, docs folders, markdown files) must always be written in English.
- When writing tests, they must always be in PEST format.
- YAML files must always use block style formatting. Never use flow/inline style like `{ key: value }` or `[item1, item2]`.
- Default sorting of a list is CONFIGURATION, never component code: set a top-level `defaultSort:` block in the list YAML (`field:` plus optional `direction: asc|desc`, `desc` when omitted) — in BOTH synced copies. `mountList()` applies it while the user has not sorted the list; a session-saved user sort always wins. There is no component API for it: never set `$this->sortField`/`$this->sortAsc` and never override `mount()` for sorting.

### Config, noerd:install and noerd:update
- Configuration keys come from the package config (`vendor/noerd/noerd/config/noerd.php`); the
  host project's `config/noerd.php` is published by `noerd:install` and refreshed by `noerd:update`
  (which also re-publishes the frontend assets). Never read `env()` outside config files — use
  `config('noerd.…')`.
- After upgrading the package run `php artisan noerd:update` (core) and `php artisan noerd:update-all`
  (every installed module) — both are idempotent.

### Install Command Required for Every App Module
- Every module that is a tenant app (has `app-configs/{module}/` with a `navigation.yml`) MUST ship a `noerd:install-{module}` command. New submodules always get one — never rely on the manual `noerd:create-app` flow.
- The command extends `Illuminate\Console\Command`, uses the `HasModuleInstallation` and `RequiresNoerdInstallation` traits, and implements `getModuleName()`, `getModuleKey()`, `getDefaultAppTitle()`, `getAppIcon()`, `getAppRoute()` and `getSourceDir()`. Its `handle()` calls `$this->runModuleInstallation()` (which copies the YAML configs, registers the app via a published migration and runs migrations).
- Register the command in the module's ServiceProvider inside `if ($this->app->runningInConsole()) { $this->commands([...]); }`.
- The `noerd:module` scaffolder generates this command and its ServiceProvider registration automatically, from `src/Commands/stubs/module/install-command.stub`.
- Every such module MUST also ship a `noerd:update-{module}` command — a slim subclass of the install
  command whose `handle()` calls `$this->runModuleUpdate()` (never `runModuleInstallation()`, which
  prompts for the tenant assignment) plus the module's *idempotent* post-install steps (e.g.
  `ensureDashboardWidget()`), never the install-only ones. A module without an `app-configs/` folder
  republishes its config instead.
  Register it next to the install command. `noerd:update-all` discovers every command named
  `noerd:update-{module}`, so a missing one silently drops the module out of the project-wide update.
- Reference: the `install-command.stub` / `update-command.stub` rendered by `noerd:module` (`src/Commands/stubs/module/`) and `docs/creating-modules.md`.

### Eloquent Models: $guarded instead of $fillable
- Never use `$fillable` in Eloquent models. Always use `$guarded` instead.
- `$guarded` should either be an empty array `[]` (all fields mass-assignable) or contain only sensitive fields that must be protected (e.g., `is_admin`, `role`).
- When reviewing or refactoring existing models, replace `$fillable` with the appropriate `$guarded` definition.

**Example:**
```php
// Correct: all fields mass-assignable
protected $guarded = [];

// Correct: only sensitive fields protected
protected $guarded = ['is_admin', 'role'];

// WRONG: never use $fillable
protected $fillable = ['name', 'email', 'phone'];
```

### Module Independence
- Every module in `app-modules/` must be independent from other modules
- Test traits (e.g., `CreatesInventoryUser`) belong in the module itself: `app-modules/{module}/tests/Traits/`
- Seeders for module-specific data belong in the module: `app-modules/{module}/database/seeders/`
- Migrations for module-specific tables belong in the module: `app-modules/{module}/database/migrations/`
- No dependencies between optional modules (an optional module must never `use` classes, views or YAML of another optional module)
- Module-specific code (e.g., in `DatabaseSeeder`) must not be placed in the main project

### Permissions, Profiles & Action Checks

Authorization is generic and two-staged (reference: `docs/permissions.md` and
`docs/extension-registries.md`, "Authorization gates") — never hand-roll per-module checks:

- Every user has ONE profile per tenant: the `Noerd\Enums\Profile` enum (cases Admin/User/ReadOnly),
  stored as `users_tenants.profile_key`. Profiles are HARDCODED — no profiles table, no seeding, no
  profile CRUD; labels come from `Profile::label()`. With no gates defined the profile is the
  baseline: Admin = setup access + bypass, User (or no profile) = everything, ReadOnly = read only.
  Modules may register ADDITIONAL profiles via `app(ProfileRegistry::class)->register(key, label)`
  (their semantics come from the gates that module defines; the core treats unknown keys like User).
- ABOVE the profiles sits one installation-level flag: `noerd_users.super_admin` (`isSuperAdmin()`).
  A super admin administers the whole installation — `isAdmin()` in every tenant, may ENTER every
  tenant, sees every tenant and every account. It is `$guarded` and set ONLY on the console
  (`noerd:super-admin {id|email}`, `--revoke` to withdraw), never from a screen. Whether a user may
  WORK IN a tenant is `NoerdUser::canAccessTenant()` / `accessibleTenants()` — never compare
  against `$user->tenants` directly for that question.
- All checks go through `Noerd\Helpers\AccessHelper` (`canAccessApp`, `canReadObject`,
  `canWriteObject`, `canCreateObject`, `canDeleteObject`, `canPerformAction`, `canUseApp`). Create
  and write are SEPARATE abilities: `canSaveObject()` on detail/page components picks create (new
  record) vs. write (update) — the save chrome and the store()/delete() guards (incl.
  `WriteGuardHook` for custom overrides) key off it. Never bypass these helpers with manual
  `isAdmin()` checks for object access.
- Models whose data also flows through HAND-BUILT queries (dashboard counters, bespoke widgets,
  manual listData()) opt into the query-level read guard: `use Noerd\Traits\GuardedByObjectPermission;`
  on the model — while read is denied, every query (aggregates included) yields nothing. This is
  DELIBERATELY opt-in per model; without the trait such queries are NOT permission-guarded
  (reference: `docs/permissions.md`, "Query-level read guard").
- An operation beyond CRUD ("start production run") is a NAMED ACTION: register it in the module
  provider's `boot()` via `app(ActionPermissionRegistry::class)->register('{module}_{action}', 'Label')`
  (snake_case keys, `[a-z0-9_]` — e.g. `production_start_run`) and guard the call site with the
  `action-permission:{key}` route middleware or `AccessHelper::canPerformAction()`. Always register
  what you check — an unregistered action stays invisible to authorization tooling.

### Quick-Menu Buttons Are App-Independent
The quick-menu (`app-configs/quick-menu.yml`) is tenant scoped, not app scoped: a button renders the
same, with the same target, no matter which app is selected in the app bar.

- A quick-menu component must NEVER read `TenantHelper::getSelectedApp()` — not directly, and not
  through a helper that falls back to it. Pass the module/app explicitly instead.
- Buttons (and dashboard widgets) declare their app(s) via the YAML `app:`/`apps:` key — rendered
  only when one of the apps is assigned to the tenant AND the app permission allows it
  (`AccessHelper::canUseApp()`). NEVER define a per-module "tenant has app X" gate (the removed
  canOrders/canCms pattern) — such gates ignore the user's permissions; inside a component use
  `AccessHelper::canUseApp(...)` directly, on routes use the `app-access:{app}` middleware.
- If a target only makes sense per app, render one button per app the tenant runs (labelled with
  that app's tenant-app title) instead of one button that changes meaning.
- App-specific entry points belong in the app's navigation, dashboard or header actions.

### Custom Attributes (Project-Specific Fields)
Modules must NEVER be modified for project-specific data fields — neither the module code, nor the module's YAML configuration files (lists/details). Instead, use the `custom_attributes` JSON column available on key models.

**Rules:**
- The migration to add `custom_attributes` to a model belongs in the **project root** `database/migrations/`, NOT in the module
- The model cast `'custom_attributes' => 'array'` belongs in the module's model
- Rename migrations (e.g., `additional_fields` → `custom_attributes`) belong in the **module** so existing installations get the rename on module update
- YAML detail/list configurations in `app-configs/` must NOT be modified for project-specific fields. Use `custom_attributes` in the project's Blade views instead
- Project-specific Blade views that need custom_attributes fields should override the module's view or add custom logic in the project

**Access patterns:**
```php
// In PHP
$model->custom_attributes['my_key'];

// In Blade/Livewire detail views
$this->detailData['custom_attributes']['my_key'];
```

**Models with `custom_attributes`:** check the module's model for the `'custom_attributes' => 'array'`
cast — a module that supports project-specific fields declares it there. Add the column (project-root
migration) and the cast (module model) for a model that does not have it yet.
### Tabs in Detail Components
When a detail component needs tabs, two files must be modified:

**1. YAML file (e.g., `details/example-detail.yml`):**
```yaml
title: Example
tabs:
  - number: 1
    label: module_tab_general
  - number: 2
    label: module_tab_settings
fields:
  - name: detailData.name
    label: Name
    type: text
    colspan: 6
  - name: detailData.description
    label: Description
    type: textarea
    colspan: 6
  - name: detailData.setting_a
    label: Setting A
    type: checkbox
    colspan: 6
    tab: 2
  - name: detailData.setting_b
    label: Setting B
    type: text
    colspan: 6
    tab: 2
```

**2. Blade file (e.g., `example-detail.blade.php`):**
```blade
<x-noerd::tabs :layout="$pageLayout" />

<x-noerd::tab-panels>
    @foreach($pageLayout['tabs'] ?? [['number' => 1]] as $tab)
        <x-noerd::tab-panel :number="$tab['number']">
            @php
                $tabFields = array_filter($pageLayout['fields'] ?? [], fn($field) => ($field['tab'] ?? 1) === $tab['number']);
                $tabLayout = array_merge($pageLayout, ['fields' => array_values($tabFields)]);
                if ($tab['number'] !== 1) {
                    unset($tabLayout['title'], $tabLayout['description']);
                }
            @endphp
            @include('noerd::components.detail.block', $tabLayout)

            {{-- Additional content for specific tabs --}}
            @if($tab['number'] === 2)
                {{-- Custom content for tab 2 --}}
            @endif
        </x-noerd::tab-panel>
    @endforeach
</x-noerd::tab-panels>
```

**Important:**
- Fields without a `tab` property are automatically assigned to Tab 1
- Tab filtering must be done in the Blade file using `array_filter`
- Hand-rolled tab panels must ALWAYS use the generic `<x-noerd::tab-panels>` / `<x-noerd::tab-panel>`
  components — never a bare `x-show` div and never a hand-built stacking grid. The components keep the
  modal height constant across tabs (no jumping tab bar) and give every panel its own scroll container,
  so an empty tab never forces scrolling. `<x-noerd::tab-panel>` accepts an optional `show` prop with an
  Alpine expression (e.g. `:show="'$wire.someFlag'"`) for reactive visibility on top of the tab switch
- Reference: `docs/detail-view.md` ("Tab Properties" / "Hand-Rolled Tab Panels")

### Relation Forms in Detail Components
A detail YAML field may bind into a RELATED model (e.g. `detailData.invoiceAddress.address_line_1`
edits the customer's default invoice address). This is a single generic core feature: the MODEL
declares the form via the `Noerd\Contracts\DeclaresRelationForms` interface
(`relationForms(): array` returning `RelationFormDefinition::make(relation: ..., fields: [...])`,
optionally with `persistUsing`/`persistWhen` for domain persistence). The framework hydrates the
form on load and persists + rehydrates it after every save through the global
`RelationFormPersistHook` — **never hand-roll hydrate/strip/persist/rehydrate logic in a detail
component**. A hand-rolled `store()` that mass-assigns `$this->detailData` directly must build its
payload with `RelationFormSync::strip($modelClass, $this->detailData)`. Reference:
`docs/relation-forms.md`.

### Relation Fields and Select Events in Detail Components
A relation field (`type: {x}Relation`) is a REGISTERED type: the owning module registers it in its
ServiceProvider via `RelationFieldRegistry::register('customerRelation', RelationFieldDefinition::model(
listComponent: ..., detailRoute: ..., modelClass: ..., titleResolver: ...))`; the YAML references
that type and nothing else. The generic `type: relation` does not exist and throws during rendering.
The field component keeps the value and the display title in sync with the detail itself
(`detailData.{field}` + `relationTitles.{field}`), so the detail needs no code for the common case.

When the detail must REACT to a selection (derive further fields, load defaults):

1. **Event Handler Method**: Name follows pattern `{entity}Selected` (e.g., `customerSelected`, `bookSelected`) —
   the event name is derived from the list component (`customers-list` → `customerSelected`) unless the
   definition sets `selectEvent`
2. **Use relationTitles array**: Always use `$this->relationTitles['field_id']` for the display value
3. **Never create separate properties** like `$this->customer` or `$this->book` for relation display values

**Example PHP:**
```php
#[On('customerSelected')]
public function customerSelected($customerId): void
{
    $customer = Customer::find($customerId);
    $this->detailData['customer_id'] = $customer->id;
    $this->relationTitles['customer_id'] = $customer->name;
}
```

**Example YAML:**
```yaml
- name: detailData.customer_id
  label: Customer
  type: customerRelation
  colspan: 6
```

**Reference:** `docs/relation-field-types.md`

### Custom Field Types (FieldTypeRegistry)
The YAML `type:` of a detail field is resolved through the `Noerd\Services\FieldTypeRegistry`
singleton — nothing is hardcoded. A module registers additional field types in its ServiceProvider
`boot()` and any detail YAML may then use them:

- `FieldTypeDefinition::include('module::components.forms.my-type', resolver: ...)` for Blade
  partials, `FieldTypeDefinition::livewire('module::my-field', resolver: ..., keyResolver: ...)`
  for dedicated Livewire field components. The optional `resolver` computes the props per render
  from `(array $field, $component, $detailData, $modelId)`.
- Relation types are registered via `RelationFieldRegistry::register('{x}Relation', RelationFieldDefinition::model(...))`
  — this auto-registers the matching field type; never register both by hand. The generic renderer
  is `noerd-relation-field` (readonly input + magnifier). To restyle ONE relation type (e.g. an
  address card), pass a custom Livewire component as `fieldComponent:` in the definition; that
  component MUST extend `Noerd\Livewire\RelationFieldComponent` (inherits the full picker/select/
  clear/openDetail behaviour and the same props) and may read the related record via
  `$this->relatedModel()`. Never fork the selection round trip in custom markup — reuse
  `$modal('{{ $listComponent }}', {id: ..., context: '{{ $fieldName }}', listActionMethod: 'selectAction'})`.
- Unknown types fall back to the plain input (`text`, `number`, `date`, … need no registration);
  unregistered `*Relation` types throw during rendering.
- Reference: `docs/field-types.md` ("Custom Field Types"), `docs/relation-field-types.md`
  ("Custom Renderer Component"), `docs/extension-registries.md`.

### Actions in List Components
List components support multiple action buttons via the `actions` array in the YAML configuration.

**YAML configuration:**
```yaml
title: Bank Transactions
actions:
  - label: Import
    action: openImportModal
    heroicon: arrow-up-tray
  - label: New Transaction
    action: listAction
```

- `label`: Translation key for the button text
- `route`: Named route opened as a modal — use this instead of `action: listAction` for the "New …" button
- `action`: Livewire method name to call (used when no `route` is given)
- `heroicon`: (optional) Heroicon name for the button icon
- `style`: (optional) Set to `secondary` for secondary button style. Default is primary.

**Button layout:**
- All buttons are primary style by default (`!bg-brand-primary`)
- Set `style: secondary` on individual actions for secondary style
- Keyboard shortcut (N) applies only to the first button
- Buttons are displayed side by side

**Standard single action (most common):**
```yaml
title: Customers
actions:
  - label: New Customer
    action: listAction
```

**PHP method for custom actions:**
```php
use Noerd\Facades\Noerd;

public function openImportModal(mixed $modelId = null, array $relations = []): void
{
    Noerd::modal('bank-transaction-import');
}
```

**Important:**
- Custom methods must accept `(mixed $modelId = null, array $relations = [])` parameters
- No `actions` key means no button is rendered
- The `action` value must always be explicitly set (no implicit fallback to `listAction`)

**Reference:** `docs/list-view.md` ("Actions")

### Picklist Columns Render as Translated Badges in Lists
When a list column displays a picklist/select value, it must ALWAYS be shown as a **badge** with the
option's label translated into the currently active language — never the raw database value. This is a
single generic feature in `NoerdList` + the list views; never duplicate it per module.

- **Automatic:** `NoerdList::applyPicklistBadges()` (called from `buildList()`) derives the option
  labels from the list's **paired detail YAML** (`{x}-list` → `{x}-detail`, e.g. `campaigns-list` →
  `campaign-detail`) and renders the matching columns (`type: select` fields with inline `options`) as a
  translated badge via the `badge` cell type. No per-list configuration is needed — adding the column to
  the list YAML is enough.
- A column that already declares an explicit `type` (e.g. `date`, `currency`, `bool`) or carries its own
  `options` is never overridden.
- **Manual opt-in** (for a list with no matching detail field): set `type: badge` and provide the
  `options` on the list column:
  ```yaml
  columns:
    - field: status
      label: Status
      type: badge
      options:
        - value: draft
          label: Draft
        - value: active
          label: Active
  ```
- The label is translated with `__()` (English-text key → `de.json`), so the badge follows the active
  language. Badges use a neutral single-colour style.
- The `badge` cell renderer lives in `noerd::components.table.table-cell` (full lists) and
  `noerd::components.list.minimal` (embedded widgets); CSV export resolves the label too
  (`NoerdList::formatCsvValue`).
- Reference: `docs/list-view.md` ("Column Types", `badge`).

### Multi-Select & Bulk Actions in List Components
List components support a **generic multi-select** mode: a leading checkbox column plus a footer bar
that operates on the ticked rows. It is a single generic feature in `NoerdList` + the list view — never
duplicate it per module. There are two flavours:

**1. Bulk-action page (`multiSelect: true` in the list YAML).** The page shows checkboxes; row click
still opens the detail (only the checkbox ticks the row). When ≥1 row is selected, a footer bar renders
the YAML `bulkActions` buttons. Selection is never shown in compact/embedded (`compact`) lists.

```yaml
title: Tasks
multiSelect: true
bulkActions:
  - label: Delete
    action: deleteSelected      # generic NoerdList method — works for any list
    heroicon: trash
    style: danger               # optional: secondary | danger (default primary)
    confirm: Delete the selected entries?   # optional: shown via wire:confirm
  - label: Create task
    action: createTaskForSelected   # list-specific method defined on the component itself
    heroicon: clipboard-document-check
```

- **`deleteSelected()` lives in `NoerdList`** and deletes every selected id through the tenant-scoped
  query (firing model events). Wire it up purely from YAML — no per-list method needed.
- **List-specific bulk actions** are public methods on the list component (e.g. a `createTaskForSelected()`
  that opens a task-create modal with the ticked rows pre-selected); they read the
  ticked ids from `$this->selectedRecordIds`.
- The selected ids are tracked in the generic `public array $selectedRecordIds` (on `NoerdList`).

**2. Picker (returns a selection to an opener).** Open a list as a modal with
`Noerd::modal('{module}::{entities}-list', ['multiSelect' => true, 'returnsSelection' => true, 'context' => '…', 'selectedRecordIds' => $current])`.
In picker mode a row click ticks the row, the top "New …" action is hidden, and the footer shows
**Cancel / Apply selection**. On confirm the list dispatches `recordsSelected` with `ids` + `context`;
the opener listens via `#[On('recordsSelected')]` and filters by its `context`.

```php
#[On('recordsSelected')]
public function recordsSelected(array $ids, mixed $context = null): void
{
    if ($context !== 'taskRecords') { return; }
    $this->selectedIds = array_values(array_map('intval', $ids));
}
```

**Important:**
- `multiSelect`, `returnsSelection`, `selectedRecordIds`, `toggleRecordSelection`,
  `toggleSelectAllVisible`, `confirmRecordSelection` and `deleteSelected` are all generic on `NoerdList`
- Keep both synced copies of the list YAML in sync (`app-configs/` and the module's `app-configs/`)
- Reference: `docs/list-view.md` ("Multi-Select & Bulk Actions")

### Grid Mode in List Components (Card Layout)
Any NoerdList-based list can render its rows as a **card grid** instead of the table — purely via
the list YAML, with ZERO changes in the component's Blade file. It is a single generic feature
(`noerd::components.list.grid`, switched in `list/index.blade.php`) — never build a hand-rolled
grid in a list component.

```yaml
title: POS
displayMode: grid
gridColumns: 4
columns:
  - field: name
    label: Name
  - field: city
    label: City
```

- `displayMode: grid` swaps ONLY the rows block; list header (title, search, view switcher,
  actions, filter chips), pagination, picker/bulk footers and permission handling stay unchanged
- `gridColumns` (1–6, default 4) = cards per row at the largest breakpoint, responsive collapse
  below; the classes come from a static map (Tailwind cannot generate class names at runtime)
- Card content derives from `columns`: first non-empty value = bold title, remaining columns =
  secondary lines, empty values skipped; column types are honored (`currency`, `date`, `bool`,
  `badge` pill, …)
- Row click behaves exactly like a table row (`openListRow` → `$detailRoute`/`$detailComponent` or
  a custom `listAction()` override), incl. keyboard nav, picker mode and per-card multi-select
  checkboxes
- Excel column filters and sorting render as a control bar above the cards
  (`noerd::components.list.grid-controls`): labeled funnel buttons per filterable column (same
  popover as the header funnel) plus a sort dropdown on the right listing every sortable column
  (`NoerdList::isSortableColumn()` — not `action`, not dotted, not in `notSortableColumns`) and two
  entries for the direction (`setSortDirection()`). No bar in compact/embedded lists
- Not rendered in grid mode (thead-only): select-all, `showLineNumbers`, summary footer.
  Minimal mode (widgets) ignores `displayMode`
- Reference: `docs/list-view.md` ("Grid Mode")

### Compact Mode in List Components (Embedded Lists)
List components support a **compact mode** for embedding a list inside another component (e.g. a
related list rendered inside a detail view). In compact mode the list renders only the table:

- the list header is hidden (title, search field and action buttons / "New …")
- the inline list description is hidden
- the pagination footer is hidden (the "Showing 1 to N of N results" row and the per-page select)

**Enabling it** — pass the `compact` attribute when embedding the list Livewire component, exactly
like `disableModal`:
```blade
<livewire:{module}::{entities}-list
    wire:key="parent-{entities}-{{ $modelId }}"
    disableModal
    compact
    :parentId="$modelId" />
```

**Architecture (generic — never duplicate per module):**
- `compact` is a public property on the `NoerdList` trait (`public bool $compact = false;`), so any
  list component accepts it as a tag attribute
- `noerd::components.list` (`list/index.blade.php`) reads the flag via
  `$compact = $compact ?? ($this->compact ?? false);` and skips the header slot and the pagination
- A list embedded with `disableModal` breaks out by `-2rem` (intended for full-page routes). Inside a
  modal/detail, the surrounding wrappers re-pad it so it aligns cleanly

**Important:**
- Compact mode also removes pagination — only the first `perPage` rows are shown. Use it for
  narrowly-scoped lists (e.g. records belonging to the current detail record)
- **Do not hand-wire this in detail views** — use the generic `<x-noerd::detail-lists>` component
  (see below), which renders the heading, the breakout wrappers and the compact list for you

### Multiple List Views in List Components (View Switcher)
A list can ship **multiple YAML views** — alternate complete configs of the same list. When ≥2 views
exist, the list title + record count (e.g. „Kunden (431)") becomes a dropdown button that switches the
active view (Salesforce-style). It is a single generic feature in `NoerdList` + `StaticConfigHelper` +
the list header — never duplicate it per module.

- **Naming convention:** sibling files in the same `lists/` folder, suffixed with `--{key}`:
  `customers-list.yml` (default view) + `customers-list--vip.yml` + `customers-list--inactive.yml`.
  The view key is the suffix after `--`; `--` is reserved as the view separator and must never appear
  in list names themselves.
- Each view file is a **complete standalone list config** (title, columns, actions, …) — nothing is
  merged from the base file. The dropdown label is the view file's `title` (translated).
- **Cross-app enumeration:** the dropdown lists the views of EVERY app allowed for the tenant, not
  just the session's current app — one entry per app that ships the list name (base + `--` variants),
  each labelled with its source app at reduced opacity, e.g. „Kunden (Delivery)". Current-app entries
  use plain keys (`default`, `vip`); other apps' entries use composite `{app}::{key}` keys
  (`delivery::vip`) — `::` is therefore also reserved. Selecting a foreign-app view renders that
  app's YAML via `StaticConfigHelper::getListConfigForApp()`; the session app is NOT changed.
  Ordering: current app first, `default` leading each app group.
- Views MAY be **project-only** (a `--{key}.yml` in the project's `app-configs/` without a module
  copy). Discovery (`StaticConfigHelper::getListViews()`) searches the same locations as the base
  config; within one app a project file shadows a module-source file with the same view key. The
  both-copies sync rule applies per file only when a module copy exists.
- The selected view is persisted per list in the session (`listView.{component}`) — as the composite
  `{app}::{key}` for foreign-app views. A removed view YAML silently falls back to the default view.
- The switcher never renders in compact/embedded lists or pickers.
- Generic API on `NoerdList`: `?string $listView` (active plain key, `null` = base YAML),
  `?string $listViewApp` (source-app folder, `null` = current app), `switchListView(string $key)`
  (`'default'` = base YAML; accepts composite keys), computed `availableListViews`
  (`'{viewKey}' => ['key' => …, 'app' => …, 'appLabel' => …, 'title' => …]`).
- Reference: `docs/list-view.md` ("Multiple List Views") and `tests/Feature/ListViewsTest.php`.

### Embedded Lists in Detail Components
Detail components can render one or more **compact lists** below the form — e.g. the Opportunities of
an Account, or one parts list per assembly on a vehicle. Each entry renders a section heading
(styled like the detail block title) and the referenced list Livewire component in its compact,
full-width variant (`compact` + `disableModal` are applied automatically). There are **two ways** to
use it:

1. **YAML-driven** — `<x-noerd::detail-lists>` (plural) for a fixed set of lists declared in the YAML
2. **Blade-direct** — `<x-noerd::detail-list>` (singular) for dynamic cases (e.g. a `@foreach` loop)
   where the number of lists depends on data and cannot be expressed in YAML

`<x-noerd::detail-lists>` simply loops the YAML `lists` array and delegates each entry to
`<x-noerd::detail-list>`, so both share the exact same rendering.

#### YAML-driven: `<x-noerd::detail-lists>`
The list counterpart to `<x-noerd::tab-content>` / `<x-noerd::detail-relations>`: a single line in
the Blade, fully driven by a `lists` array in the detail YAML.

**Blade usage** (place after `<x-noerd::tab-content>`):
```blade
<x-noerd::detail-lists :layout="$pageLayout" :modelId="$modelId" />
```

**YAML configuration** (`details/{entity}-detail.yml`):
```yaml
title: Account Information
lists:
  - title: Opportunities
    component: crm::opportunities-list
    arguments:
      accountId: $modelId
fields:
  - name: detailData.name
    label: Name
    type: text
```

**List properties:**

| Property | Description |
|----------|-------------|
| `title` | (optional) Section heading above the list (translation key), rendered via `detail.block-head` |
| `description` | (optional) Sub-heading text (translation key) |
| `component` | The list Livewire component to embed (e.g. `crm::opportunities-list`) |
| `arguments` | Arguments passed to the list; the `$modelId` token resolves to the current record id |

**Important:**
- `<x-noerd::detail-lists>` is generic and lives in the noerd module — never duplicate it per module
- Nothing is rendered until the record is saved (`$modelId` is set) or when `lists` is empty
- The embedded list is always rendered compact (no header, no pagination — only the first `perPage`
  rows). Use it for record-scoped lists
- Only the `$modelId` token and static values are supported in `arguments`
- Reference: `docs/detail-view.md` ("Embedded Lists")

#### Blade-direct: `<x-noerd::detail-list>`
For dynamic cases that YAML cannot express — e.g. rendering one list **per related record** in a loop.
Pass the values directly as props instead of through the layout.

**Blade usage:**
```blade
@foreach ($vehicle->assemblies as $assembly)
    <x-noerd::detail-list
        component="pdm::parts-list"
        :arguments="['assemblyId' => $assembly->id]"
        lazy
        :title="$assembly->name"
        :wireKey="$assembly->id . '-parts'" />
@endforeach
```

**Props:**

| Prop | Description |
|------|-------------|
| `component` | The list Livewire component to embed (e.g. `pdm::parts-list`) |
| `arguments` | Array of mount params for the list (real values — no `$modelId` token resolution here) |
| `title` | (optional) Section heading (translation key) |
| `description` | (optional) Sub-heading text (translation key) |
| `lazy` | (optional) Lazy-load the list (passed through to Livewire via the params array) |
| `wireKey` | (optional) Explicit `wire:key`; defaults to a hash of component + arguments. Vary it (e.g. include a timestamp) to force a re-render when the underlying data changes |

**Important:**
- `<x-noerd::detail-list>` is generic and lives in the noerd module — never duplicate it per module
- The embedded list is always compact (no header, no pagination — only the first `perPage` rows)
- Reference: `docs/detail-view.md` ("Blade-direct: `<x-noerd::detail-list>`")

### Actions in Detail Components
Detail components can render a row of action buttons above the form. The buttons are defined in the
detail YAML through an `actions` array and either call Livewire methods on the detail component
itself (`action`) or open a noerd modal directly (`modalComponent`) — the latter needs NO method on
the detail component, so a foreign module can contribute a button purely via YAML without any
cross-module PHP dependency.

**Blade usage: NONE.** `<x-noerd::page>` renders the actions row automatically (as the first
element of the page body) whenever the component's `$pageLayout` has an `actions:` array — never
add a manual `<x-noerd::detail-actions>` include for the standard case; adding an action is purely
a YAML change. The auto-render skips embedded details, quick-create dialogs and components without
a `$pageLayout` (lists — the list-level `actions:` key is a separate concept). Only when the layout
needs custom logic (e.g. conditionally suppressing the actions), opt out with
`<x-noerd::page :detailActions="false">` and render the component explicitly (otherwise the row
would appear twice):
```blade
<x-noerd::page :detailActions="false">
    ...
    <x-noerd::detail-actions :layout="$condition ? $pageLayout : []" :modelId="$modelId" />
```

**YAML configuration** (`details/{entity}-detail.yml`):
```yaml
title: Lead
actions:
  - label: Transfer to Account
    action: transferToAccount
    heroicon: arrows-right-left
    confirm: Transfer this lead to a new account?
fields:
  - name: detailData.name
    label: Name
    type: text
```

**Action properties:**

| Property | Description |
|----------|-------------|
| `label` | Button label (translation key) |
| `action` | Livewire method to call via `wire:click` (required unless `modalComponent` is set) |
| `route` | (optional) Named `Route::livewire()` route opened as a modal via `$modalRoute(...)` — preferred for record targets. Precedence: `route:` → `modalComponent:` → `url:` → `action:` |
| `modalComponent` | (optional) Livewire modal component (e.g. `pos::pos-order-modal`) opened directly via the Alpine `$modal(...)` magic instead of a `wire:click` method; also the fallback for an unregistered `route:` |
| `url` | (optional) Renders the action as a plain link (`<a href>`) opening in a new tab — either a literal URL (`http…` / `/…`) or a key in the `urls` map returned by a public `detailActionUrls(): array` method on the detail component (for record-dependent URLs; picked up by convention). An unresolvable key hides the button. Add `newTab: false` to stay in the same tab |
| `arguments` | (optional, with `modalComponent`) Arguments passed to the modal; the `$modelId` token resolves to the current record id |
| `viewExists` | (optional) View name (e.g. `pos::components.pos-order-modal`) — the button is hidden when that view is not registered, so YAML may reference an optional module safely |
| `heroicon` | (optional) Heroicon name rendered before the label |
| `confirm` | (optional, `action` only) Confirmation prompt shown via `wire:confirm` (translation key) |
| `requiresId` | (optional) Defaults to `true` — the button is hidden until the record is saved (`$modelId` is set). Set to `false` to always show it |
| `showIf` / `showIfNot` | (optional) Show the button only while a component property is truthy (string form, e.g. `showIf: hasAccount`) or equals a value (object form with `field:` / `value:`). Rendered as an Alpine `x-show`, so it follows the component state live; both keys may sit on one action and are combined with AND. Use it for record STATE — `requiresId` and `viewExists` stay the structural conditions. When EVERY action is conditional the action bar hides with them |

**Modal action example** (no PHP method needed on the detail component):
```yaml
actions:
  - label: New Order
    heroicon: shopping-cart
    modalComponent: pos::pos-order-modal
    viewExists: pos::components.pos-order-modal
    arguments:
      customerId: $modelId
```

**PHP method on the detail component:**
```php
public function transferToAccount(): void
{
    // ... validation / business logic ...
}
```

**Important:**
- No `actions` key means no button row is rendered
- `<x-noerd::detail-actions>` is a generic Blade component in the noerd module — never duplicate it per module
- Reference: `docs/detail-view.md` ("Detail Actions")

### Pages (NoerdPage) and the Page/Detail Split
`*-page` components host everything AROUND a record's form: page chrome (header/footer/tabs), the
Relation Box, the widget sidebar and optionally an embedded slim `*-detail`. They use the
`NoerdPage` trait (NoerdDetail composes it — never duplicate functionality between the two).

- A page MAY ship a YAML at `app-configs/{app}/pages/{entity}-page.yml` (+ module copy, both in
  sync). Keys: `title`, `detail:` (the embedded detail component, e.g. `crm::account-detail`),
  `quickCreate`, `tabs`, `relations`, `widgets`. A missing page YAML is fine
  (`StaticConfigHelper::getPageFields()` is silent on miss).
- **Detail YAMLs are pure model forms** (mandatory): only `title`, `description`, `theme`,
  `quickCreate`, `tabs`, `fields`, `actions` and `lists`. `widgets:` and `relations:` NEVER belong in
  a detail YAML — they are page concerns. A detail opened standalone renders just the form.
- The save roundtrip page↔detail is generic via trait events: page `store()` dispatches
  `storeDetail-{detail}`; the detail's `store()` ends in `finishStore($model)` which dispatches
  `detailStored-{detail}`; the page adopts the id and runs the protected hook
  `afterEmbeddedDetailStored($model)` (override for page-owned persistence, e.g. product groups).
  Live form sync runs via `detailDataUpdated-{detail}` (`syncPayload()` filters the payload).
- Embed the detail in the page blade via
  `@livewire($pageLayout['detail'], ['modelId' => $modelId, 'embedded' => true], key('embedded-detail'))` —
  `x-noerd::page` renders embedded components chrome-less automatically.
- Reference: `docs/page-view.md` and the `page.blade.stub` rendered by `noerd:make-page`
  (`src/Commands/stubs/resource/page.blade.stub`).

### Settings Pages (NoerdSettingsPage)

A settings screen is a TENANT SINGLETON: it edits one row per tenant (keyed by `tenant_id`), not an
addressable record. Such screens must use the `Noerd\Traits\NoerdSettingsPage` trait and a settings
YAML — never hand-written fields, never `NoerdDetail` with a bespoke tenant-keyed `mount()`/`store()`.

- The component (name keeps the `*-page` suffix) declares the models it edits, keyed by the public
  array property the YAML fields bind to — a settings page may edit SEVERAL models at once:
  ```php
  use NoerdSettingsPage;

  public array $settingsModels = [
      'detailData' => ModuleSettings::class,
      'extraData' => OtherSettings::class,   // extra keys need a matching public array property

  ];

  public array $extraData = [];
  ```
  The slim component contains nothing else. Custom `mount()` starts with `$this->initSettings()`;
  custom `store()` (extra validation) ends with `$this->validateFromLayout();
  $this->persistSettings(); $this->showSuccessIndicator = true;`.
- The layout comes EXCLUSIVELY from `settings/{component}.yml` (`app-configs/{app}/settings/` + the
  module copy, both in sync). Allowed keys: `title`, `description`, `tabs`, `fields` — same field
  types, `tab:`, `required:`, `showIf`/`showIfNot`, `helpText` as detail YAMLs. `colspan` is
  irrelevant: settings pages have NO grid, every field renders as a stacked full-width row in the
  built-in hidden `settings` theme. A `theme:` key in the YAML and the tenant-wide theme setting
  (even enforced) are both ignored, and layout overrides NEVER apply — settings pages have no
  layout overrides at all. There is also no `custom_attributes` object manager.
- No `$detailPrimary`, no `$modelId`, no delete: the URL stays clean, the singleton row is created
  on first save via `updateOrCreate(['tenant_id' => …])` (stripping id/tenant_id/timestamps).
- Blade skeleton: `<x-noerd::page>` + `<x-noerd::modal-title>` header +
  `<x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId"/>` (tab slots like `tab2` may add
  extra markup) + footer `<x-noerd::delete-save-bar :show-delete="false"/>`.
- The `settings/` folder is published by `noerd:install-{module}` / `noerd:update-{module}` exactly
  like `lists/`/`details/`/`pages/`.
- Reference: `docs/settings-page.md` and `tests/Feature/SettingsPageTraitTest.php`.

### Relations on Pages (Relation Box)
Page components can render a "Relation Box" — a grid of clickable tiles (6 per row), each
showing a heroicon, a label and the related record count, e.g. `Contacts (5)`. Clicking a tile
opens the related list component as a modal, filtered by the current record. Use this instead of
relation tabs when you want an overview of all relations at a glance.

**Blade usage** (place between the header slot and the page body):
```blade
<x-noerd::detail-relations
    :layout="$pageLayout"
    :modelId="$modelId"
    :modelClass="\Noerd\Crm\Models\Account::class" />
```

`<x-noerd::detail-relations>` is a thin wrapper that renders the `<livewire:noerd::relation-box>`
component only when `$modelId`, a non-empty `relations` array, and `modelClass` are all present.
The relation box computes each count via the model's relationship method and refreshes the counts
automatically when a list modal is closed (`#[On('closeTopModal')]`).

**YAML configuration** (`pages/{entity}-page.yml`):
```yaml
title: Account
detail: crm::account-detail
relations:
  - label: Sub-Accounts
    heroicon: building-office-2
    relation: children
    component: accounts-list
    arguments:
      parentAccountId: $modelId
  - label: Contacts
    heroicon: users
    relation: contacts
    component: contacts-list
    arguments:
      accountId: $modelId
fields:
  - name: detailData.name
    label: Name
    type: text
```

**Relation properties:**

| Property | Description |
|----------|-------------|
| `label` | Tile label (translation key) |
| `heroicon` | Heroicon name rendered before the label |
| `relation` | Eloquent relationship method on the model used to count records (e.g. `contacts`) |
| `route` | Named list route opened as a modal. The URL is deliberately NOT rewritten — the tile opens the list narrowed by the current record |
| `component` | List component opened as a modal on click (e.g. `contacts-list`, no `crm::` prefix) — fallback when `route` is not registered |
| `arguments` | Arguments passed to the modal; the `$modelId` token resolves to the current record id |

**Important:**
- The components (`detail-relations`, `relation-box`) are generic and live in the noerd module — never duplicate them per module
- Only the `$modelId` token and static values are supported in `arguments`
- An unknown `relation` method yields a count of `0` instead of throwing
- `modelClass` must be a fully-qualified Eloquent model class string
- Reference: `docs/page-view.md` ("Relation Box")

### Theme System (`theme:` in Detail Components)
Detail forms render each field with its label ON TOP of the input by default. A detail YAML can opt
into an alternate **theme** via the top-level string key `theme:`. Built-in themes:

| Theme | Layout |
|-------|--------|
| `default` | Label on top of the input (also used when `theme` is absent or unknown) |
| `compact` | Label to the LEFT of the input, tighter vertical spacing |
| `numbered` | Numbered form rows in the style of official/tax forms: one field per full-width row, light gray row background, leading row number, right-aligned label, input on the right |

**YAML configuration** (`details/{entity}-detail.yml`):
```yaml
title: Account
description: ''
theme: compact
fields:
  - name: detailData.name
    label: Name
    type: text
    colspan: 6
  - name: detailData.notes
    label: Notes
    type: textarea
    colspan: 12
    theme: default   # per-field override
```

There is no `view:` or boolean `compact:` key on a detail YAML — the form layout is always `theme:`.

**System-wide default (Setup → System Settings):** an admin preconfigures the theme for the whole
system there; it is stored per tenant on `noerd_settings` (`detail_theme`, `detail_theme_enforced`),
with `config('noerd.theme')` (`NOERD_THEME` / `NOERD_THEME_ENFORCED`) as the fallback. The YAML wins
over the system default — unless the admin ticked **"Enforce in Setup"**, which forces the system
theme everywhere and additionally drops every per-field and nested-block `theme:` override. This is
a single generic feature — `Noerd\Helpers\ThemeHelper` (per-tenant memo + `clearCache()`) read from
`StaticConfigHelper::applyThemeSetting()`, applied in `getComponentFields()` and `getPageFields()`
only. Never duplicate it per module, and never apply it to list configs (the `compact` flag on lists
is an unrelated concept).

**Architecture — self-contained theme folders (never modify the originals):**
- A theme is a FOLDER of element blade templates plus a `theme.yml` (label, gridClasses,
  fullWidthRows, numbersRows, spacerClass, controlClasses, buttonClasses, position-table classes).
  Built-ins: `resources/views/themes/{default,compact,numbered}/`.
- Discovery is automatic through the `ThemeRegistry` singleton
  (`src/Services/ThemeRegistry.php`): the project root `resources/views/themes/`
  (priority 100) and every root registered by a module via
  `app(ThemeRegistry::class)->registerPath(__DIR__ . '/../../resources/views/themes')` in its
  `boot()` are scanned lazily; a folder containing a `theme.yml` defines a theme (name = folder
  name). Higher priority wins name collisions; element templates resolve through the `themes::`
  view namespace root by root, so a project can override a SINGLE element file of a built-in theme.
  Unknown theme names silently fall back to `default` — a YAML typo never breaks a detail page.
- Element resolution (`Noerd\Support\ThemeElementResolver`, used by
  `noerd::components.detail.block`): for `include` field types the element name is the basename of
  the registered target (`noerd::components.forms.input-currency` → `input-currency`) and resolves
  `themes::{theme}.{element}` → `themes::default.{element}` → registered target. For `livewire`
  field types a `-{theme}` suffix component wins when it exists (namespace-aware). Relation fields
  are Livewire components that `@include` the theme templates `relation-field.blade.php` /
  `polymorphic-relation-field.blade.php`, so a copied theme restyles them like any element.
- **Creating a NEW theme:** `php artisan noerd:theme {name}` (or copy `themes/default/` to
  `resources/views/themes/{name}/`, for modules + `registerPath()`), edit `theme.yml`, adapt the
  element templates you change — missing elements fall back to the default theme. No PHP needed.
  Full reference: `docs/themes.md`.
- **Buttons follow the theme:** `x-noerd::button` without an explicit `size` uses the active
  theme's `buttonClasses` (via `Noerd\Support\ThemeContext`, set by the rendering detail/page and
  the detail block); an explicit `size`/`theme` prop wins. Footer bars and `detail-actions` follow
  automatically — never hardcode button sizes in form chrome.
- **Theme ≠ Brand:** `noerd.theme` is the FORM LAYOUT system. The color palette (sidebar/appbar
  `brand-*` CSS variables) is `noerd.brand` (`NOERD_BRAND`, `Noerd\Services\BrandService`).

**Compact theme specifics:**
- The label column is a fixed `w-36` and is `truncate`d (single line, ellipsis when too long — never
  wraps to two lines); the full label is exposed via the `title` attribute on hover. The input fills the
  remaining width. Compact inputs use a clean 1px border (`border border-zinc-200`, no shadow), minimal
  rounding (`rounded-sm`), reduced padding/height (`h-7`), and a thin focus ring with no offset
  (`focus:ring-1`, no `ring-offset`)
- **Checkbox is intentionally exempt** in compact — it is already laid out horizontally (checkbox
  left, label right) and only gains the tighter grid spacing

**Numbered theme specifics:**
- Every field renders as a full-width gray row (`colspan` is ignored); the shared row chrome lives in
  `<x-noerd::detail.numbered-row>` (`resources/views/components/detail/numbered-row.blade.php`) — the
  element templates in `themes/numbered/` only provide the bare control
- Rows are numbered automatically (1, 2, 3, … per block; nested `type: block` restarts at 1;
  `type: spacer` rows render as a blank line and consume NO number). A field
  may pin its number with an explicit `number:` key in the YAML (numbers may repeat, like tax forms)

**Important:**
- This is a generic noerd feature — never duplicate the theme registry or element templates per module
- The theme is read once in `noerd::components.detail.block` and threaded into every field and nested
  `type: block`. A single field may override the block-level theme with its own `theme:` key
- The grid wrapper emits `data-theme="{theme}"` for non-default themes
- Keep both synced copies of the detail YAML in sync (`app-configs/` and the module's `app-configs/`)
- Reference: `docs/themes.md`

### Field Defaults Are Configuration, Never `mount()` Code

A form must never display a value it does not hold. A `<select>` bound to a null property has no
matching `<option>`, so the browser shows the FIRST one by pure HTML fallback — the user sees a
status, the component holds `null`, and `null` is what gets saved. Initial values are therefore
declared in the YAML and applied generically by `NoerdDetail::applyLayoutDefaults()`.

- **Any field** may declare `default: <value>`. It is applied while the bound value is `null` (a
  missing key counts as null); `''`, `0` and `false` are answers and are never replaced.
- **A `type: select` whose `options` are written in the YAML starts on its FIRST option** and
  persists it on save — that is what makes the displayed value real. An explicit `default:` wins.
- **Opt out with `placeholder:`** when nothing-selected is a legitimate answer: the select renders a
  leading empty option and gets no implicit default.
- The implicit rule never applies to `optionsMethod:` selects — the list is built from data at
  runtime, where "the first row wins" would be arbitrary (a staff list, a person picker).
- Defaults are re-applied before every render, so they also fill an EXISTING record whose column is
  `NULL`, and they survive a custom `mount()` that replaces `$detailData` wholesale.
- **NEVER hand-roll this per component** (`$this->detailData['status'] ??= 'created';` in a custom
  `mount()`) — adding a default is a YAML change, in BOTH synced copies.
```yaml
- name: detailData.invoice_status
  label: Status
  type: select
  options:
    - value: created      # the default: shown AND saved
      label: Created
    - value: paid
      label: Paid
- name: detailData.size_class
  label: Size Class
  type: select
  placeholder: '—'        # empty is a valid answer: no implicit default
  options:
    - value: micro
      label: Micro
```
- Reference: `docs/field-types.md` ("Default Values")

### Empty Spacer Columns in Detail/Block Layouts
Fields in a detail/block layout flow into a 12-column grid via auto-placement, so removing a field makes
the following field move up into the freed slot. To keep a deliberate empty column (e.g. leave the right
half of a row blank so the next field starts on a new row), use the generic `spacer` field type:
```yaml
fields:
  - name: detailData.name
    label: Name
    type: text
    colspan: 6
  - type: spacer
    colspan: 6
  - name: detailData.email
    label: Email
    type: text
    colspan: 6
```
- `spacer` renders nothing but still occupies its `colspan`, reserving an empty grid cell
- It needs no `name`; only `type: spacer` and `colspan` are relevant
- It is a generic noerd field type (`noerd::components.forms.spacer`) — works in any list/detail block,
  compact or not. Never duplicate it per module

### Detail Component Structure (Slim `$detailModel` Syntax)
Detail components (`*-detail.blade.php`) declare their model as `public $detailModel = Model::class;`
and their URL alias as `public ?string $detailPrimary = '{entity}Id';` (e.g. `'customerId'`) at the
top of the class — both are MANDATORY for every model-backed detail (`$detailModel` drives mounting,
the default `store()`/`delete()` and the header actions; `$detailPrimary` binds `$modelId` to the
entity-scoped URL parameter and a missing declaration throws on mount). Never use a `DETAIL_CLASS`
constant — that pattern is removed. Never redeclare `$modelId` and never add a `#[Url]` attribute to
it: the binding comes from the trait (`queryStringNoerdPage()`) and is skipped automatically for
`embedded: true` instances, so a hosting page may own the same URL parameter. `detailPrimary` must be
a literal property default (never assigned in `mount()`). The trait methods (`mount()`, `store()`,
`delete()`) are always used from `NoerdDetail` — a slim component contains nothing else besides
`$detailModel` and `$detailPrimary`. Only when the logic deviates, override the method (call `$this->initDetail()`
first in a custom `mount()`; end a custom `store()` with `$this->storeProcess($model)` and a custom
`delete()` with `$this->closeModalProcess($this->getListComponent())`). Settings/config screens
without a single backing Eloquent model are exempt and keep their bespoke methods.

**Property naming:**
- **Property:** `$detailData` (array) - Provided by the `NoerdDetail` trait, used for form binding with `wire:model`
- **Local variable:** `$modelName` - Only inside methods, never as a property

The Eloquent Model must NEVER be stored as a component property. It only exists as a local variable within methods.

**Example (custom store via service — the slim default needs NO methods at all):**
```php
public $detailModel = Customer::class;

public function store(): void
{
    $this->validateFromLayout();

    $customer = CustomerService::save($this->modelId, $this->detailData);

    $this->storeProcess($customer);
}
```

**YAML field names must use the `detailData` prefix:** `name: detailData.name`

### Tests Must Test Functionality, Not Current Configuration
The YAML files under `app-configs/` are per-installation CONFIGURATION. Changing them — the `theme:`
mode, titles, labels, tabs, the field or column lists — is always legitimate and must NEVER break a
test. A test that asserts the current content of a real YAML config is wrong by definition.

- **NEVER assert current YAML settings**: no `assertSeeHtml('data-theme="compact"')` against a real
  detail component, no exact tab/field/column lists or titles read from a shipped YAML, no "component
  X renders in theme Y" tests. If such a test exists, rewrite or delete it.
- **DO test the mechanics with synthetic layouts and fixtures**: what must be proven is that a YAML
  change (any value) has the correct EFFECT. Use dedicated test components that receive a synthetic
  layout (reference: `noerd-test::theme-test` / `noerd-test::theme-setting-test` + `ThemeTest` in the
  noerd module) or runtime-written fixture YAMLs under the testbench skeleton (reference:
  `StaticConfigHelperFeatureTest`, `ConfigResolutionTest`), plus factories/mocks for data.
- **The modal target is configuration too**: `route:` vs. `modalComponent:`/`component:`,
  `$detailRoute` vs. `$detailComponent`, `modalRoute:`/`newRoute:` vs. `newComponent:` — every one of
  these may be flipped per installation and must NEVER be asserted against a shipped YAML. Prove the
  EFFECT instead, with a synthetic layout plus a runtime-registered route
  (`registerTestLivewireRoute()` in `tests/helpers.php`): route wins when
  registered, the component opens as the fallback. References: `NoerdListModalDispatchTest`,
  `DetailActionsTest`, `NavigationModalRouteTest`, `RelationBoxTest` in the noerd module, and
  `NoerdModalTest` (`describe('Modal Route URL')`) in the noerd-modal module.
- **Validation stays config-agnostic** via `requiredLayoutFields()` / `validDetailPayload()` — never
  hard-code which fields are required (see "Testing YAML-Driven Detail Forms" below).
- **Architecture guardrails are fine**: asserting that a detail YAML contains no `widgets:`/`relations:`
  keys or that page tabs live in the page YAML enforces framework rules, not tunable settings.
- **Component wiring may be asserted** when the code itself depends on it (e.g. `pageLayout.detail`
  that a page blade embeds and whose event names the page listens to) — changing it requires touching
  the component anyway.

### Testing YAML-Driven Detail Forms
Detail components (`*-detail`) build their validation rules at runtime from the detail YAML:
`NoerdDetail::validateFromLayout()` turns every field marked `required: true` into a Laravel
`required` rule (recursing into `type: block`). Nothing else in the YAML drives validation.

**These `required:` flags are configuration and can be changed arbitrarily per installation, so a
test must never assume a specific field is — or is not — required.** Two global helpers support this:
`validDetailPayload(Model::class, $overrides)` (a complete factory payload) and
`requiredLayoutFields($component)` (the required field names from the live `pageLayout`). They live in
the noerd package (`tests/helpers.php`). They are deliberately NOT composer-autoloaded (test
functions must never load in a production request): suites binding `Noerd\Tests\TestCase` get them
from that class, every other `tests/Pest.php` loads them once with `\Noerd\Tests\HelperLoader::load();`
— never a hard-coded path and never an entry in the project's root `composer.json`. When adding a new
global test helper, put it there (guarded with `function_exists`).

**Store-SUCCESS tests** — submit a COMPLETE payload sourced from the model factory and override only
the fields the test asserts on. Never hand-pick a minimal subset (it breaks the moment a new field
becomes `required: true`). Prefer mounting an existing record via `modelId` (so `mount()` hydrates
`detailData` fully), or seed the payload from the factory for create semantics:
```php
// Create path — factory-sourced payload, override only the asserted field
Livewire::test('crm::task-detail')
    ->set('detailData', validDetailPayload(Task::class, ['tenant_id' => $tenantId]))
    ->set('detailData.title', 'Call Jane')   // only what the test asserts
    ->call('leadSelected', $lead->id)         // relation set via component callback
    ->call('store')
    ->assertHasNoErrors();

// Update path — mount an existing record, then override
$model = Task::factory()->create(['tenant_id' => $tenantId]);
Livewire::test('crm::task-detail', ['modelId' => $model->id])
    ->set('detailData.title', 'New title')
    ->call('store')
    ->assertHasNoErrors();
```
Relation FKs set by component callbacks (`accountSelected`, `leadSelected`, …) and virtual/non-column
fields (translatable arrays, belongsToMany arrays, `placeInput`) are NOT in `factory()->toArray()` —
set them explicitly with `->set()` / `->call()`. Do not factory-seed a component whose `mount()`
reactively pre-fills required fields (e.g. opportunity record-type/stage) — seeding wipes them; set
only the asserted fields there.

**Validation tests** — derive the expected required fields from the live layout; never hard-code a
field name:
```php
$component = Livewire::test('accounting::expense-detail')->set('detailData', [])->call('store');
$component->assertHasErrors(requiredLayoutFields($component));
```
This applies ONLY to components that validate via `validateFromLayout()`. Components with a hardcoded
`$this->validate([...])` or `addError()` (e.g. invoice, times, page) test stable code, not per-system
config — keep their explicit assertions.

**Factories** — a factory's default `definition()` must produce a fully valid, persistable record:
every non-relation scalar column that can be `required` must be non-null and deterministic (no
`optional()` on such fields). Foreign-key/relation columns may default to null but must be satisfiable
via a named state or an explicit `create([...])` override. Tests that need a sparse/null record must
pass that null explicitly.

### Currency, Numbers & Dates Are Formatted by the Core, Never by Hand

Every amount, number, date and time is written through ICU in a LOCALE; nothing in module code
hard-codes a format. Three settings decide the output (reference: `docs/formatting.md`):
the TENANT CURRENCY (Setup → System Settings, `noerd_settings.currency` — every amount in the
system is an amount in that currency), the TENANT LOCALE (`noerd_settings.locale` — how DOCUMENTS
are written: PDFs, receipts, customer e-mails, independent of who generates them) and the USER
LOCALE (Profile → Locale, `noerd_user_settings.format_locale` — how the backend UI is written for
that reader). The LANGUAGE (Setup → Languages, Profile → Language) is a separate setting and only
selects translations; German UI + `en-US` formats is a valid combination. The locale list is fixed
in `Noerd\Support\Locales::SUPPORTED` — never add a locale per project or tenant.

- **Backend UI** (Livewire views, custom `listData()`, dashboards, widgets, relation `titleResolver`s):
  `CurrencyHelper::format($amount)`, `FormatHelper::date()`, `dateTime()`, `time()`, `decimal()`,
  `number()` (quantities), `percent()` — reader's locale, resolved automatically.
- **Documents** (PDF templates, receipts, customer e-mails, ESC/POS): `CurrencyHelper::formatForDocument($amount, $model->tenant_id)`
  and `FormatHelper::documentDate($date, $model->tenant_id)` / `documentDateTime()` / `documentDecimal()`
  — tenant locale, never the acting user's.
- **Public frontends** without a noerd user (shop, booking widget, table ordering): pass the tenant
  id explicitly — `CurrencyHelper::format($amount, $tenantId)`, `FormatHelper::date($date, FormatHelper::tenantLocale($tenantId))`.
- **Machine payloads** (PayPal/Mollie, DATEV, JSON APIs): `'currency' => CurrencyHelper::codeForTenant($tenantId)`;
  amounts stay machine-formatted (`number_format($x, 2, '.', '')`, commented as such).
- NEVER `number_format($x, 2, ',', '.')`, a literal `€`/`&euro;`/`'EUR'`, `->format('d.m.Y')`,
  `Number::currency(..., in: 'EUR', locale: 'de')`, `->locale('de')` or `Carbon::setLocale()` in module
  code, templates or translation keys (`__('Buy for :price')` with a pre-formatted `:price`, never
  `__('Buy for :price €')`). Use `->locale(FormatHelper::locale())` when Carbon's own `isoFormat()` /
  `translatedFormat()` is needed.
- List columns `type: currency|date|datetime` and the detail field `type: currency` are formatted by
  the core in the reader's locale — no per-component code. CSV exports stay plain decimals.
- Tests normalise ICU spaces (`zzNormalizeSpaces()`) and prove mechanics with a `NoerdSettings` row
  (`currency`, `locale`) plus the acting user's `format_locale` — a document renders in the tenant
  locale while a list renders in the user locale. Never assert shipped YAML.

### Translations
- Translations use English text as keys (not module-prefixed keys)
- Only `de.json` is needed per module: `app-modules/{module}/resources/lang/de.json`
- No `en.json` — the key itself IS the English text, Laravel falls back to it
- In the ServiceProvider use `loadJsonTranslationsFrom()` (not `loadTranslationsFrom()`)
- Entries where English = German (e.g., "Dashboard") can be omitted from de.json
- All JSON translations share a flat namespace across modules — avoid duplicate keys with different German values
- In YAML files use English text directly:
```yaml
title: Quotes
columns:
  - field: name
    label: Name
    width: 15
```
- In Blade files: `{{ __('Dashboard') }}`
- In PHP: `__('Invoice')`
- For German translations, de.json maps English to German: `"Invoice": "Rechnung"`
- Root `lang/` directory is ONLY for Laravel framework translations (e.g., validation messages)
@endverbatim
