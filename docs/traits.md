# Reusable Traits

The noerd core ships a set of small traits for models, Livewire components and Artisan commands. The three big component traits are documented separately: `NoerdList` in [list-view.md](list-view.md), `NoerdDetail` in [detail-view.md](detail-view.md) and `NoerdPage` in [page-view.md](page-view.md).

## BelongsToTenant (Eloquent models)

`Noerd\Traits\BelongsToTenant` makes a model tenant-aware: every query is scoped to the logged-in user's selected tenant, and new records get their `tenant_id` filled automatically.

**What it does:**

- `bootBelongsToTenant()` adds the `Noerd\Scopes\TenantScope` global scope — when a user is authenticated and has a `selected_tenant_id`, every query gets `where {table}.tenant_id = selected_tenant_id`
- A `creating` hook sets `$model->tenant_id` from `Auth::user()->selected_tenant_id` when the model has none yet
- `initializeBelongsToTenant()` appends `tenant_id` to `$fillable` — only when the model explicitly defines a non-empty `$fillable`; models using `$guarded` (the noerd standard) are untouched
- `tenant(): BelongsTo` — relation to `Noerd\Models\Tenant`

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

- The scope only applies for authenticated users with a `selected_tenant_id` — unauthenticated contexts (queued jobs, commands) see all tenants; scope explicitly there
- Bypass the scope deliberately with `Model::withoutGlobalScopes()` plus an explicit `where('tenant_id', ...)`

## HasEmailPreview (Livewire components)

`Noerd\Traits\HasEmailPreview` adds an email preview modal and a rate-limited "send test email" action to a detail component that edits an email template (subject + markdown body with placeholders).

**Abstract methods** the component must implement:

| Method | Returns |
|--------|---------|
| `getEmailData(): array` | The edited data; the trait reads `send_email`, `email_subject`, `email_body` from it (typically `return $this->detailData;`) |
| `getEmailViewName(): string` | Markdown mail view used to render the preview (e.g. `cms::emails.form-confirmation`) |
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

Reference: `app-modules/cms/resources/views/components/form-type-detail.blade.php`.

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
| `this_week` | 7 days ago |
| `this_month` | Start of the current month |
| `last_month` | Start of the previous month |
| `this_quarter` | First day of the current quarter |
| `last_quarter` | First day of the previous quarter |
| `this_year` | Start of the current year |
| `one_week` | One week ago |
| `one_month` | One month ago |
| `one_year` | One year ago |
| anything else | Parsed as a date via `resolveCustomDate()` (`null` when unparseable) |

**Customizing the columns** — override the protected hooks (both default to `created_at`):

```php
use Noerd\Traits\NoerdList;
use Noerd\Traits\ShowFromFilterTrait;

new class extends Component {
    use NoerdList;
    use ShowFromFilterTrait;

    protected function getShowFromDateColumn(): string
    {
        return 'published_at';
    }
};
```

## TenantFilterTrait (list components)

`Noerd\Traits\TenantFilterTrait` provides a tenant dropdown for lists that show records across tenants (admin screens). `getTenantsListFilter(): array` returns a `Picklist` filter on the `tenant_id` column with one option per tenant from `Auth::user()->adminTenants`.

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

`Noerd\Traits\PublishesAuditMigration` is for install/update commands of modules that use `owen-it/laravel-auditing`. `publishAuditingMigrationIfNeeded(): void` checks `database_path('migrations')` for an existing `*_create_audits_table.php`; when none exists it runs `vendor:publish` with `--provider=OwenIt\Auditing\AuditingServiceProvider --tag=migrations` and reports the result on the command output. Re-running is a no-op.

```php
class MyModuleInstallCommand extends Command
{
    use PublishesAuditMigration;

    public function handle(): int
    {
        $this->publishAuditingMigrationIfNeeded();
        // ...

        return 0;
    }
}
```

## HasModuleInstallation / RequiresNoerdInstallation (install commands)

The traits behind every `noerd:install-{module}` command — covered in full in [creating-modules.md](creating-modules.md). A command implements the abstract getters (`getModuleName()`, `getModuleKey()`, `getDefaultAppTitle()`, `getAppIcon()`, `getAppRoute()`, `getSourceDir()`) and calls the trait helpers:

| Method | Description |
|--------|-------------|
| `runModuleInstallation(): int` | The whole flow: verifies noerd is installed, copies the app-config YAMLs, registers the tenant app, runs migrations; re-running switches to the update path |
| `publishMigration(): ?string` | Publishes the module's app-registration migration stub into the project |
| `ensureQuickMenuButton(array $button, array $legacyComponents = []): void` | Adds a quick-menu button when missing |
| `ensureDashboardWidget(array $widget, array $legacyComponents = []): void` | Adds a dashboard widget when missing |
| `ensureSetupNavigation(string $blockTitle, array $entry): void` | Adds an entry to the setup navigation when missing |

`RequiresNoerdInstallation` contributes `ensureNoerdInstalled(): bool` (aborts with a hint when `noerd:install` has not run) and `assignAppToTenants(string $appName): void`.
