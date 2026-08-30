# Relation Forms

Detail YAMLs can bind fields into a RELATED model. A field named
`detailData.invoiceAddress.address_line_1` edits an attribute of the record
behind an Eloquent relation of the detail's `$detailModel` — the framework
hydrates the values on load and persists them after every save. The detail
component needs **no code** for this: no hydrate call, no strip, no persist,
no rehydrate.

## Declaring a relation form

Relation forms are strictly opt-in and declared on the MODEL (never sniffed
from field names — detailData keys are arbitrary strings). The model
implements `Noerd\Contracts\DeclaresRelationForms`:

```php
use Noerd\Contracts\DeclaresRelationForms;
use Noerd\Support\RelationFormDefinition;

class Customer extends Model implements DeclaresRelationForms
{
    public static function relationForms(): array
    {
        return [
            // form key in the YAML => definition
            'invoiceAddress' => RelationFormDefinition::make(
                relation: 'defaultInvoiceAddress',           // Eloquent relation method
                fields: ['address_line_1', 'postal_code'],   // attributes the form carries
            ),
        ];
    }
}
```

The form key is an alias — it does not have to match the relation name.
Subclasses inherit declarations (crm's `Account extends Customer` gets the
address form for free).

```yaml
fields:
  - name: detailData.invoiceAddress.address_line_1
    label: Address
    type: text
    required: true
```

Validation works as usual: `required: true` on a relation-form field becomes a
rule for the full dotted path via `validateFromLayout()`.

## Custom persistence

The default persister normalizes `''` to null and updates the related record
(creating and linking it for a BelongsTo, `updateOrCreate` for HasOne /
MorphOne). When the write is domain logic, declare it:

```php
RelationFormDefinition::make(relation: 'defaultInvoiceAddress', fields: [...])
    ->persistWhen(fn (array $data): bool => /* has data? default: any field non-empty */)
    ->persistUsing(function (Customer $customer, array $data): void {
        app(CustomerAddressService::class)->upsertFor(
            $customer, $data, asInvoiceDefault: true, asDeliveryDefault: true,
        );
    });
```

A `persistUsing` closure owns the write entirely, including its own
normalization. Definitions are rebuilt on every call and never serialized into
Livewire state.

## Conditional validation

Beyond the YAML `required: true` flags, a form may declare its own rules —
applied by `validateFromLayout()` exactly when the form carries data and would
be persisted (an entirely empty optional form is never validated):

```php
RelationFormDefinition::make(relation: 'sepaMandate', fields: ['account_holder', 'iban', 'bic'])
    ->validateUsing([
        'iban' => ['required', new ValidIban()],
        'bic' => ['nullable', 'string', 'min:8', 'max:11'],
    ], [
        'iban.required' => __('The IBAN is required.'),
    ]);
```

Rule and message keys use the bare field names; the framework prefixes them
with the full `detailData.{formKey}.` path.

## Extending a foreign model

A module may attach a relation form to a model it does not own through its
model SUBCLASS on the same table — the established shared-table pattern:
booking-members' `CustomerBooking extends Customer` overrides
`relationForms()` with `parent::relationForms() + ['sepaMandate' => ...]` and
its detail component declares `$detailModel = CustomerBooking::class`. The
backing relation itself may be registered dynamically
(`Customer::resolveRelationUsing('sepaMandate', ...)`) — dynamic relations are
inherited by subclasses.

## How it works (architecture)

Everything lives in the noerd core; never re-implement a piece of it per
component:

- **Hydration** — `NoerdDetail::ensureRelationFormsHydrated()`, called from
  `mountDetailComponent()` AND `renderingNoerdDetail()` so it survives custom
  `mount()`s that overwrite `$detailData`. For every declared form the active
  layout renders, the form array is filled from the related record (or with
  nulls for a new record, so nested `wire:model` bindings never silently drop
  updates). Keyed on `array_key_exists` — idempotent, at most one query per
  request.
- **Mass-assignment safety** — the default `store()` strips the form keys and
  the snake_case relation keys (eager-load leakage) via
  `RelationFormSync::strip()`. Components with a hand-rolled store that
  mass-assigns `$this->detailData` directly must build their payload with
  `RelationFormSync::strip($modelClass, $this->detailData)`.
- **Persistence** — `Noerd\Support\RelationFormPersistHook`, a global Livewire
  ComponentHook (registered in NoerdServiceProvider) that runs after every
  save action, whatever the component's `store()` does. It persists each form
  only when the ACTIVE layout (including a DB-driven layout override)
  renders the form's fields — a layout without them never overwrites the
  record with stale hydrated values — and rehydrates the form afterwards so
  the fields stay filled in the same response.
- **`Noerd\Support\DetailSaveHook`** is the reusable base for "after a detail
  saved" hooks: save-action detection (including event-dispatched saves, which
  arrive as `__dispatch` and are mapped to their listener method), a
  validation-error guard (nothing is written back after failed validation), a
  `canWriteObject()` guard, and fresh record loading. Extension packages build
  their own after-save hooks on the same base.

## Testing

Test the mechanics with synthetic fixtures (never against shipped YAMLs):
runtime `Livewire::component()` registration with a synthetic `$pageLayout`,
`Schema::create` fixture tables, and a fixture model implementing
`DeclaresRelationForms`. Reference: `app-modules/noerd/tests/Feature/RelationFormSyncTest.php`.
