# Registered Relation Field Types

Relations no longer use the generic `relation` field type.

Every relation must be registered in a module service provider and referenced in YAML with an explicit type such as `customerRelation`, `vehicleRelation`, `authorRelation` or `pageRelation`.

## YAML Usage

```yaml
- name: detailData.customer_id
  label: customer_label_customer
  type: customerRelation
  colspan: 6
```

`modalComponent` and `relationField` are no longer configured in YAML for registered relation fields.

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

## Required Definition Data

- `type`: explicit YAML field type, for example `customerRelation`
- `listComponent`: list opened in select mode
- `detailComponent`: detail modal opened for existing values
- `modelClass`: model used to hydrate the saved relation value
- `titleResolver`: model attribute or callback that returns the display title

## Custom Title Resolver

```php
$relationFieldRegistry->register('quoteRelation', RelationFieldDefinition::model(
    listComponent: 'quotes-list',
    detailComponent: 'quote-detail',
    modelClass: Quote::class,
    titleResolver: fn (Quote $quote): string => $quote->number . ' (' . \Number::currency($quote->total_net, in: 'EUR', locale: 'de') . ')',
));
```

## Runtime Behaviour

- All registered relation types render through the shared Livewire component `noerd-relation-field`
- Selection uses the generic event `noerdRelationSelected`
- The legacy `{entity}Selected` event is still dispatched for compatibility
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

## Migration Rule

- `type: relation` is forbidden
- New relations must be registered first and only then referenced in YAML
