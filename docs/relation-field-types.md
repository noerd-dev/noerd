# Registered Relation Field Types

Relation fields are registered types. Every relation is registered in a module service provider and
referenced in YAML with an explicit type such as `customerRelation`, `vehicleRelation`,
`authorRelation` or `pageRelation`. The generic `type: relation` is not supported — an unregistered
relation type fails explicitly during rendering.

## YAML Usage

```yaml
- name: detailData.customer_id
  label: customer_label_customer
  type: customerRelation
  colspan: 6
```

The registered definition supplies the list component, detail target and title resolution —
nothing else is configured in YAML.

## Registering a Relation Type

Register the type in the module that owns the target model:

```php
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;
use Noerd\Customer\Models\Customer;

$relationFieldRegistry = $this->app->make(RelationFieldRegistry::class);

$relationFieldRegistry->register('customerRelation', RelationFieldDefinition::model(
    listComponent: 'customers-list',
    detailComponent: 'customer-detail',
    modelClass: Customer::class,
    titleResolver: 'name',
));
```

## Definition Parameters (`RelationFieldDefinition::model()`)

| Parameter | Description |
|-----------|-------------|
| `listComponent` | List opened in select mode (required) |
| `detailComponent` | Detail modal opened for existing values |
| `modelClass` | Model used to hydrate the saved relation value |
| `titleResolver` | Model attribute name or callback that returns the display title (default `'name'`) |
| `selectEvent` | Custom selection event name; defaults to the `{entity}Selected` convention derived from the list component |
| `detailRoute` | Named `Route::livewire()` route opened as a modal for existing values — preferred over `detailComponent` when the record is addressable (see [Modals](modal.md#route-modals)); `detailComponent` stays as the fallback when the route is not registered |

## Custom Title Resolver

```php
$relationFieldRegistry->register('quoteRelation', RelationFieldDefinition::model(
    listComponent: 'quotes-list',
    detailComponent: 'quote-detail',
    modelClass: Quote::class,
    titleResolver: fn (Quote $quote): string => $quote->number . ' (' . \Number::currency($quote->total_net, in: 'EUR', locale: 'de') . ')',
));
```

## Polymorphic Relation Fields

For a field that may point at one of several relation types (e.g. an invoice source that is either
an order or a quote), register a polymorphic type with the allowed relation types:

```php
$relationFieldRegistry->registerPolymorphic('invoiceSourceRelation', [
    'orderRelation',
    'quoteRelation',
]);
```

Each allowed type must itself be a registered relation type. The YAML field additionally names the
column that stores the selected type:

```yaml
- name: detailData.source_id
  typeField: detailData.source_type
  label: Source
  type: invoiceSourceRelation
  colspan: 6
```

Polymorphic fields render through the shared Livewire component
`noerd-polymorphic-relation-field`, which shows a type selector next to the relation input.

## Runtime Behaviour

- All registered relation types render through the shared Livewire component `noerd-relation-field`
  (polymorphic types through `noerd-polymorphic-relation-field`)
- Selection uses the generic event `noerdRelationSelected`; the `{entity}Selected` event (or the
  definition's `selectEvent`) is dispatched as well, so detail components can listen with
  `#[On('customerSelected')]`
- The handler sets the foreign key in `detailData` and the display value in the generic
  `relationTitles` array — never a separate display property (`$this->customer`); the YAML field
  references it with `relationField: relationTitles.customer_id`:

  ```php
  #[On('customerSelected')]
  public function customerSelected(int $customerId): void
  {
      $customer = Customer::find($customerId);
      $this->detailData['customer_id'] = $customer->id;
      $this->relationTitles['customer_id'] = $customer->name;
  }
  ```
- Registering a relation type automatically registers a matching field type in the
  `FieldTypeRegistry` — no separate field-type registration is needed
- Unregistered relation types fail explicitly during rendering

### Theme Templates

The two Livewire components delegate their markup to the active theme's templates
`relation-field.blade.php` / `polymorphic-relation-field.blade.php` (see [Themes](themes.md)). The
detail block passes the field's theme as the `theme` prop; a theme without an own relation template
falls back to the default theme's.

The behaviour lives once in the abstract `Noerd\Livewire\RelationFieldComponent` /
`Noerd\Livewire\PolymorphicRelationFieldComponent`; a copied theme folder restyles relation fields
by editing the two templates — no PHP is duplicated.

The numbered templates render inside `<x-noerd::detail.numbered-row>` and need the row number:
`RelationFieldRegistry` puts `number` into the component props whenever the detail block numbered
the field (i.e. only in a theme with `numbersRows`), and the base class exposes it as
`$this->numberedRowField()`.
