# Reusable Traits

The noerd core ships a set of small traits for models, Livewire components and Artisan commands. The three big component traits are documented separately: `NoerdList` in [list-view.md](list-view.md), `NoerdDetail` in [detail-view.md](detail-view.md) and `NoerdPage` in [page-view.md](page-view.md).

## BelongsToTenant (Eloquent models)

`Noerd\Traits\BelongsToTenant` makes a model tenant-aware: every query is scoped to the logged-in user's selected tenant, and new records get their `tenant_id` filled automatically.

**What it does:**

- `bootBelongsToTenant()` adds the `Noerd\Scopes\TenantScope` global scope — when `TenantHelper::currentTenantId()` resolves a tenant (an authenticated noerd user with a selected tenant), every query gets `where {table}.tenant_id = {currentTenantId}`
- A `creating` hook stamps `$model->tenant_id` from the same `TenantHelper::currentTenantId()` when the model has none yet — scope and stamp share one resolver, so a record is never written with a tenant the scope would exclude
- `tenant(): BelongsTo` — relation to `Noerd\Models\Tenant`

The trait does not touch `$fillable`; use `$guarded` (the noerd standard).

```php
use Noerd\Traits\BelongsToTenant;

class Campaign extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
}

Campaign::create(['name' => 'Spring']);   // tenant_id set automatically
Campaign::all();                           // only the selected tenant's rows
```

**Important:**

- The scope only applies while a noerd user is authenticated with a selected tenant — unauthenticated contexts (queued jobs, commands) see all tenants; scope explicitly there. Only the noerd guard counts: a host guard's user (e.g. an admin panel on the `web` guard) never influences scoping (see [Authentication](auth.md))
- Bypass the scope deliberately with `Model::withoutGlobalScopes()` plus an explicit `where('tenant_id', ...)`

## HasEmailPreview (Livewire components)

`Noerd\Traits\HasEmailPreview` adds an email preview modal and a rate-limited "send test email" action to a detail component that edits an email template (subject + markdown body with placeholders).

**Abstract methods** the component must implement:

| Method | Returns |
|--------|---------|
| `getEmailData(): array` | The edited data; the trait reads `send_email`, `email_subject`, `email_body` from it (typically `return $this->detailData;`) |
| `getEmailViewName(): string` | Markdown mail view used to render the preview (e.g. `mymodule::emails.confirmation`) |
| `getEmailRateLimitPrefix(): string` | Cache-key prefix for the test-email cooldown (e.g. `'form-type:' . ($this->modelId ?? 'new')`) |
| `getSampleEmailData(): array` | `placeholder => sample value` map (e.g. `['{{form_title}}' => ...]`), replaced into subject and body |

**Provided API:**

| Member | Description |
|--------|-------------|
| `openPreview(): void` | Opens the `noerd::email-preview-modal` with the rendered HTML, subject and sample data |
| `sendTestEmail(): void` | Sends the rendered preview to the logged-in user's email address, then starts a 60-second cooldown |
| `$this->canShowPreview` (computed) | `true` when `send_email` is set and `email_body` is non-empty |
| `$this->canSendTestEmail` (computed) | `false` while the cooldown is active |
| `$this->testEmailCooldownSeconds` (computed) | Remaining cooldown seconds (0 when none) |
| `renderEmailPreview(): string` | Replaces the sample placeholders in `email_body` and renders it through the markdown mail view; falls back to `nl2br(e($body))` when rendering fails |

The rate limiting is cache-based: the cooldown key is `test-email-cooldown:{prefix}:{userId}`, stored for 60 seconds per user and prefix — no database involved.

```blade
<x-noerd::button variant="secondary" wire:click="openPreview"
                 x-show="$wire.canShowPreview">{{ __('Preview') }}</x-noerd::button>

<x-noerd::button variant="secondary" wire:click="sendTestEmail"
                 :disabled="! $this->canSendTestEmail">{{ __('Send test email') }}</x-noerd::button>
```

The preview modal is `noerd::email-preview-modal`; the test mail is `Noerd\Mail\EmailPreviewTestMail`.

## ShowFromFilterTrait (list components)

`Noerd\Traits\ShowFromFilterTrait` provides date-range header filters for lists: a "Show From" (rows on/after a date) and a "Show Until" (rows on/before a date) dropdown. `NoerdList::applyListFilters()` recognizes the filter types `ShowFrom`/`ShowUntil` and applies `>=` / `<=` on the configured columns.

**Filter methods** (auto-discovered by `NoerdList::tableFilters()` via the `get*ListFilter` convention):

- `getShowFromListFilter(): array` — type `ShowFrom`, column `show_from`
- `getShowUntilListFilter(): array` — type `ShowUntil`, column `show_until`

**Dropdown options** (`getDateFilterOptions()`): empty (no filter), `today`, `this_week`, `this_month`, `last_month`, `this_quarter`, `last_quarter`, `this_year`.

**Period resolution** (`resolveShowDate(string $value): ?string`) supports these values:

| Value | Resolves to |
|-------|-------------|
| `today` | Today |
| `this_week` | Start of the current week (`startOfWeek()`) |
| `this_month` | Start of the current month |
| `last_month` | Start of the previous month |
| `this_quarter` | First day of the current quarter |
| `last_quarter` | First day of the previous quarter |
| `this_year` | Start of the current year |
| anything else | Parsed with `Carbon::parse()` via `resolveCustomDate()` — a raw date string (`2026-01-01`) is a valid option value; unparseable → `null`, the filter is ignored |

**Customizing the columns** — declare the two properties on the component (both default to
`created_at`). Set BOTH: "Show From" and "Show Until" are independent filters, and a list that
moves only one of them keeps comparing the other boundary against the import timestamp:

```php
use Noerd\Traits\NoerdList;
use Noerd\Traits\ShowFromFilterTrait;

new class extends Component {
    use NoerdList;
    use ShowFromFilterTrait;

    protected string $showFromDateColumn = 'published_at';
    protected string $showUntilDateColumn = 'published_at';
};
```

The properties must be `protected`: a public property is part of the Livewire client payload, and
these values are written into the query as raw column names. The trait deliberately declares
neither property — PHP fatals when a class redeclares a trait property with a different default —
so the hooks `getShowFromDateColumn()` / `getShowUntilDateColumn()` read them through `??`. The
methods stay overridable as the escape hatch for a column that is only known at runtime; a method
override wins over the property.

## TenantFilterTrait (list components)

`Noerd\Traits\TenantFilterTrait` provides a tenant dropdown for lists that show records across tenants (admin screens). `getTenantsListFilter(): array` returns a `Picklist` filter on the `tenant_id` column with one option per tenant from `NoerdAuth::user()?->administeredTenants()` — every tenant for a super admin, the ADMIN-profile memberships otherwise (an empty picklist without an authenticated noerd user).

```php
use Noerd\Traits\NoerdList;
use Noerd\Traits\TenantFilterTrait;

new class extends Component {
    use NoerdList;
    use TenantFilterTrait;

    // getTenantsListFilter() is auto-discovered by tableFilters()
};
```

## SetupLanguageFilterTrait (list components)

`Noerd\Traits\SetupLanguageFilterTrait` provides language helpers for lists whose rows carry a `language` column, backed by the `Noerd\Models\SetupLanguage` model.

| Method | Description |
|--------|-------------|
| `hasMultipleLanguages(): bool` | `true` when more than one active language exists |
| `getLanguageListFilter(): array` | `Picklist` filter on the `language` column — one option per active language (`code => name`), default language first |
| `getDefaultLanguageCode(): string` | The default language code |
| `getActiveTenantLanguageCodes(): array` | All active language codes |

Show the filter only when it is useful by overriding `tableFilters()`:

```php
#[Computed]
public function tableFilters(): array
{
    if (! $this->hasMultipleLanguages()) {
        return [];
    }

    return [$this->getLanguageListFilter()];
}
```

## PublishesAuditMigration (Artisan commands)

`Noerd\Traits\PublishesAuditMigration` is for install/update commands of modules that use `owen-it/laravel-auditing`. `publishAuditingMigrationIfNeeded(): void` checks `database_path('migrations')` for an existing `*_create_audits_table.php` and the database for an existing `audits` table (a project that squashed its migrations into a schema dump has the table but no file); when neither exists it runs `vendor:publish` with `--provider=OwenIt\Auditing\AuditingServiceProvider --tag=migrations` and reports the result on the command output. Re-running is a no-op. The screen behind those records is the [Activity Log modal](audit-log.md).

```php
class MyModuleInstallCommand extends Command
{
    use HasModuleInstallation;
    use PublishesAuditMigration;

    // Before the migration prompt, on install and update.
    protected function publishModuleExtras(bool $update): void
    {
        $this->publishAuditingMigrationIfNeeded();
    }
}
```

## InstallsNoerdModule / HasModuleInstallation (install commands)

The traits behind every `noerd:install-{module}` / `noerd:update-{module}` command — covered in
full in [creating-modules.md](creating-modules.md). `InstallsNoerdModule` is the contract of every
module (a support module uses it directly and implements only `getModuleName()`);
`HasModuleInstallation` builds on it for a tenant app and adds the abstract getters
`getModuleKey()`, `getDefaultAppTitle()`, `getAppIcon()`, `getAppRoute()`, `getSourceDir()`.

| Method | Description |
|--------|-------------|
| `runModuleInstallation(): int` | Tenant app: verifies noerd is installed, installs `getRequiredModules()`, copies the app-config YAMLs, registers the tenant app, asks for the tenants, offers the migration, runs `ensureModuleSetup()`, offers the build; re-running switches to the update path |
| `runModuleUpdate(): int` | Tenant app: re-publishes the app-config YAMLs (creating a missing `app-configs/{module}/` folder) and the module resources, runs `ensureModuleSetup()`; never migrates, never asks about tenants |
| `runSupportModuleInstallation(): int` / `runSupportModuleUpdate(): int` | The same two flows for a module without a tenant app |
| `getConfigFiles()`, `getAppConfigsDir()`, `getAdditionalSubdirectories()`, `getRequiredModules()`, `publishModuleExtras()`, `ensureModuleSetup()` | What a module declares instead of coding it — see [creating-modules.md](creating-modules.md#install-and-update-commands-required) |
| `askForMigration(): void` | Offers `php artisan migrate`; skipped for a dependency install and in a non-interactive run without `--migrate` |

`Noerd\Commands\Concerns\WritesHostAppConfigs` (part of `InstallsNoerdModule`, so tenant apps and
support modules alike have it) contributes the idempotent host-YAML writers, called from
`ensureModuleSetup()`:

| Method | Description |
|--------|-------------|
| `ensureQuickMenuButton(array $button, array $legacyComponents = []): void` | Adds a quick-menu button when missing |
| `ensureDashboardWidget(array $widget, array $legacyComponents = []): void` | Adds a dashboard widget when missing |
| `ensureSetupNavigation(string $blockTitle, array $entry): void` | Adds an entry to the setup navigation when missing |

The other building blocks in `Noerd\Commands\Concerns` (`PublishesModuleConfig`, `PublishesSkills`,
`PublishesConfigDirectory`, `RegistersBoostPackage`, `RunsNpmBuild`, `GuardsPublishTargets`, …) can
be used on their own by a command that is not a module installer.

`RequiresNoerdInstallation` (part of `InstallsNoerdModule`, also usable alone) contributes
`ensureNoerdInstalled(?bool $autoInstall = null): bool`. On a project without `config/noerd.php` an
INSTALL command (`noerd:install-{module}`) runs `noerd:install` itself as a dependency — forwarding
`--force`, `--migrate`, `--build`, `--demo`; no demo question, no closing callout, npm deferred to
the module command — and aborts with the manual hint only when that installation did not complete.
Every other command (update, scaffold, demo) aborts with the hint. `$autoInstall` overrides the
decision `shouldAutoInstallNoerd()` otherwise derives from the command name.

## GuardedByObjectPermission (Eloquent models)

`Noerd\Traits\GuardedByObjectPermission` is the opt-in query-level read guard: while
`AccessHelper::canReadObject()` denies the model for the current user, a global scope makes EVERY
query on it yield nothing — hand-built dashboard counters, aggregates and relations included.
Console, queue and guest contexts are unaffected, and a system read lifts the scope explicitly with
`withoutGlobalScope(Model::OBJECT_READ_GUARD_SCOPE)`. It is deliberately per-model, never automatic —
see [Permissions → Query-level read guard](permissions.md#query-level-read-guard-opt-in-trait).

## AdministersNoerdUsers (internal)

`Noerd\Traits\AdministersNoerdUsers` is shared by the setup app's user screens
(`noerd::noerd-user-page` and the embedded `noerd::noerd-user-detail`) so both entry points to the
same account apply identical checks: `assignedToCurrentTenant()`, `authorizeTargetUser()` (the
edited account must belong to a tenant the caller administers — `$modelId` is URL-bound and can be
repointed after mount) and `deleteUserAccount()`. It is internal to those screens and not intended
for module use.

## Helpers

Static helpers under `Noerd\Helpers`; each is documented on its owning page:

- **`TenantHelper`** — the tenant/app session API. `currentTenantId()` is the authenticated noerd
  user's selected tenant — the single resolver `TenantScope` and the `BelongsToTenant` stamp read
  (`null` in console, queue and guest contexts). `getSelectedTenantId()` / `getSelectedTenant()`
  read the session selection (memoized per request), `setSelectedTenantId()` writes it and persists
  it on the user, `hasTenant()` checks it; `getSelectedApp()` / `setSelectedApp()` / `hasApp()`
  hold the app selected in the sidebar. `clear()` forgets both session keys, `clearCache()` drops
  the request memos (call it in tests after mutating tenants or tenant apps).
- **`NoerdAuth`** — guard-explicit access to the noerd user → [Authentication](auth.md)
- **`AccessHelper`** — every permission check → [Permissions](permissions.md)
- **`FormatHelper`**, **`CurrencyHelper`** — ICU formatting → [Currency, Numbers & Dates](formatting.md)
- **`ThemeHelper`** — the tenant's form theme → [Themes](themes.md)
- **`StaticConfigHelper`** — resolves every YAML config (lists, details, pages, settings,
  navigation, list views) →
  [Extension Registries](extension-registries.md#layout-overrides-noerdlayout-overrides-binding)
- **`SetupCollectionHelper`** — lookup tables and `selectOptions()` → [Setup Collections](setup-collections.md)
- **`KeyboardShortcutHelper::parse($configKey, $default)`** — a configured shortcut string as
  modifier/key parts → [Keyboard Shortcuts](keyboard-shortcuts.md)
- **`IconHelper::heroicons()`** — all outline heroicon names (used by the icon pickers)
