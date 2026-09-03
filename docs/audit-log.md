# Activity Log (Audit Trail)

noerd ships a ready-made **Activity Log** modal (`noerd::audit-modal`) that lists every recorded
change of a single record: when it happened, who made it, and which field went from which value to
which. The audit records themselves are produced by
[`owen-it/laravel-auditing`](https://github.com/owen-it/laravel-auditing) — noerd only renders them.

## Requirement

The package is a **suggestion**, not a dependency: the core works without it, and the modal is only
reachable for models that are actually auditable. Install it in the host project:

```bash
composer require owen-it/laravel-auditing
```

## Making a model auditable

A model qualifies for the modal when it satisfies **all three** conditions:

1. it is an Eloquent model,
2. it implements `OwenIt\Auditing\Contracts\Auditable` (using the package's `Auditable` trait),
3. it uses `Noerd\Traits\BelongsToTenant` — the audit list is tenant data and is only shown for
   tenant-scoped records.

```php
use Illuminate\Database\Eloquent\Model;
use Noerd\Traits\BelongsToTenant;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Invoice extends Model implements Auditable
{
    use AuditableTrait;
    use BelongsToTenant;

    protected $guarded = [];
}
```

## Publishing the audits table

The `audits` table comes from the auditing package. A module's install or update command publishes
its migration through the `PublishesAuditMigration` trait (see
[Reusable Traits](traits.md#publishesauditmigration-artisan-commands)) — the call is idempotent and
skips when `database_path('migrations')` already holds a `*_create_audits_table.php`:

```php
use Illuminate\Console\Command;
use Noerd\Traits\HasModuleInstallation;
use Noerd\Traits\PublishesAuditMigration;

class InvoicingInstallCommand extends Command
{
    use HasModuleInstallation;
    use PublishesAuditMigration;

    public function handle(): int
    {
        $this->publishAuditingMigrationIfNeeded();

        return $this->runModuleInstallation();
    }
}
```

Run `php artisan migrate` afterwards.

## Opening the modal

The modal is an action dialog, not an addressable record — it opens by **component**
(see [Modal System](modal.md)). It takes two arguments:

| Argument | Description |
|----------|-------------|
| `modelClass` | Fully-qualified class string of the audited model |
| `modelId` | Primary key of the record whose history is shown |

**From a Livewire method:**

```php
use Noerd\Facades\Noerd;

public function openActivityLog(): void
{
    Noerd::modal('noerd::audit-modal', [
        'modelClass' => Invoice::class,
        'modelId' => $this->modelId,
    ]);
}
```

**Purely from the detail YAML** — `modalComponent` opens a modal without any method on the detail
component, and `arguments` accepts static values next to the `$modelId` token
(see [Detail Actions](detail-view.md#detail-actions)):

```yaml
actions:
  - label: Activity Log
    heroicon: clock
    modalComponent: noerd::audit-modal
    arguments:
      modelClass: 'Noerd\Invoicing\Models\Invoice'
      modelId: $modelId
```

## What the table shows

The modal renders `<x-noerd::audit-table>` with the record's audits, newest first
(`$model->audits()->latest('id')`). One row per audit entry:

| Column | Content |
|--------|---------|
| Date | `FormatHelper::date()` of the audit's `created_at` — the reader's locale |
| Time | `H:i` of the audit's `created_at` |
| User | E-mail of the `NoerdUser` behind `user_id` (blank for system/console changes) |
| Change | One line per changed field: the field name, the old value, `to`, the new value |

The table component can also be embedded on its own — it resolves the user e-mails itself when the
caller passes only `audits`:

```blade
<x-noerd::audit-table :audits="$audits" />
```

## Validation performed by the modal

`modelClass` and `modelId` arrive from the client (the modal stack takes its arguments from the
browser), so both are `#[Locked]` and `mount()` re-checks the target before touching the database.
The modal aborts with **404** unless `modelClass`

- is a non-empty, existing class,
- is a subclass of `Illuminate\Database\Eloquent\Model`,
- implements `OwenIt\Auditing\Contracts\Auditable`, and
- uses `Noerd\Traits\BelongsToTenant`.

The record itself is then loaded with `findOrFail()` through the model's tenant scope — a record of
another tenant is a 404 as well. Mechanics: `tests/Feature/DynamicMountTest.php`.

## Next Steps

- [Detail View](detail-view.md) — adding the action button to a record form
- [Modal System](modal.md) — component modals vs. route modals
- [Reusable Traits](traits.md) — `PublishesAuditMigration`, `BelongsToTenant`
