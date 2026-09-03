# Registered Relation Field Types

Relation fields are registered types. Every relation is registered in a module service provider and
referenced in YAML with an explicit type such as `itemRelation`, `authorRelation` or
`pageRelation`. The generic `type: relation` is not supported — it and every unregistered
`*Relation` type throw explicitly during rendering.

## YAML Usage

```yaml
- name: detailData.item_id
  label: Item
  type: itemRelation
  colspan: 6
```

The registered definition supplies the list component, detail target and title resolution —
nothing else is configured in YAML.

## Registering a Relation Type

Register the type in the module that owns the target model:

```php
use Noerd\Services\RelationFieldRegistry;
use Noerd\Support\RelationFieldDefinition;
use Vendor\Inventory\Models\Item;

$relationFieldRegistry = $this->app->make(RelationFieldRegistry::class);

$relationFieldRegistry->register('itemRelation', RelationFieldDefinition::model(
    listComponent: 'inventory::items-list',
    detailComponent: 'inventory::item-detail',
    detailRoute: 'inventory.item.detail',
    modelClass: Item::class,
    titleResolver: 'name',
));
```

## Definition Parameters (`RelationFieldDefinition::model()`)

| Parameter | Description |
|-----------|-------------|
| `listComponent` | List opened in select mode (required) |
| `detailComponent` | Detail modal opened for existing values. Defaults to the singular of the list name (`inventory::items-list` → `inventory::item-detail`, `RelationFieldDefinition::getDetailComponent()`); `null` when the list name does not end in `-list` |
| `modelClass` | Model used to hydrate the saved relation value |
| `titleResolver` | Model attribute name or callback that returns the display title (default `'name'`) |
| `selectEvent` | Custom selection event name; defaults to the `{entity}Selected` convention derived from the list component without its namespace (`inventory::items-list` → `itemSelected`) |
| `detailRoute` | Named `Route::livewire()` route opened as a modal for existing values — preferred over `detailComponent` when the record is addressable (see [Modals](modal.md#route-modals)); `detailComponent` stays as the fallback when the route is not registered |
| `fieldComponent` | Livewire component rendering the field in the detail form; `null` (default) uses the generic `noerd-relation-field` input. See [Custom Renderer Component](#custom-renderer-component) |

## Custom Title Resolver

```php
$relationFieldRegistry->register('quoteRelation', RelationFieldDefinition::model(
    listComponent: 'quotes-list',
    detailComponent: 'quote-detail',
    modelClass: Quote::class,
    titleResolver: fn (Quote $quote): string => $quote->number . ' (' . \Number::currency($quote->total_net, in: 'EUR', locale: 'de') . ')',
));
```

## Custom Renderer Component

By default every relation type renders as the generic readonly input with a clear button and a
magnifier (`noerd-relation-field`). A module can replace that markup for a single relation type —
e.g. render an address as a clickable card — by passing its own Livewire component as
`fieldComponent`:

```php
$relationFieldRegistry->register('warehouseAddressCardRelation', RelationFieldDefinition::model(
    listComponent: 'inventory::warehouse-addresses-list',
    detailComponent: 'inventory::warehouse-address-detail',
    detailRoute: 'inventory.warehouse-address.detail',
    modelClass: WarehouseAddress::class,
    titleResolver: fn (WarehouseAddress $address): string => $address->label ?? '',
    fieldComponent: 'inventory::warehouse-address-card-field',
));
```

The component MUST extend `Noerd\Livewire\RelationFieldComponent` — it then inherits the complete
behaviour (mount hydration, the `noerdRelationSelected` round trip, `clear()`, `openDetail()`,
`setFieldValue` sync to the parent detail) and receives exactly the same props as the generic
renderer (`relationType`, `fieldName`, `label`, `value`, `required`, `readonly`, `helpText`,
`modelId`, `number`, `theme`, `owner`, `errorMessages`). Only the Blade markup differs. The
single-file component is two lines plus markup:

```blade
<?php

new class extends \Noerd\Livewire\RelationFieldComponent {}; ?>

<div>
    {{-- custom markup; open the picker exactly like the generic template: --}}
    {{-- @click="$modal('{{ $listComponent }}', {id: {{ $modelId ?: 'null' }}, context: '{{ $this->selectionContext() }}', listActionMethod: 'selectAction'})" --}}
</div>
```

For markup that shows more than the title, the base class exposes the related Eloquent record as
the computed property `$this->relatedModel` (resolved through the definition's `modelClass`; `null`
while the field is empty). Non-default themes resolve a `{component}-{theme}` sibling when it exists and fall back to
the component itself (see [Themes](themes.md)).

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
  (polymorphic types through `noerd-polymorphic-relation-field`), unless the definition names a
  custom `fieldComponent`
- Selection uses the generic event `noerdRelationSelected`; the `{entity}Selected` event (or the
  definition's `selectEvent`) is dispatched as well, so detail components can listen with
  `#[On('itemSelected')]`
- The field component itself writes the foreign key into the owning detail's `detailData` and the
  display title into its generic `relationTitles` array through the `setFieldValue` event
  (`field`, `value`, `relationTitle`, `owner`) — `NoerdDetail::setFieldValue()` only accepts
  `detailData.*` paths. A detail that needs side effects listens for the `{entity}Selected` event
  and works with `detailData`/`relationTitles` — never a separate display property
  (`$this->item`), and no extra YAML key:

  ```php
  #[On('itemSelected')]
  public function itemSelected(int $itemId): void
  {
      $item = Item::find($itemId);
      $this->detailData['unit_price'] = $item->price;
  }
  ```
- **Owner scoping:** the detail block passes the owning detail's Livewire id as `owner`. The
  picker context becomes `{fieldName}@{owner}` (`RelationFieldComponent::selectionContext()`) and
  `setFieldValue` carries the owner, so two stacked details sharing a field name never adopt each
  other's selection
- **Validation messages:** the field receives the owning detail's error bag entries for its
  `fieldName` as the reactive `errorMessages` prop, so a failed `store()` shows them without
  re-mounting the field
- `openDetail()` opens the record behind the current value via `Noerd::modalFor($detailRoute,
  $detailComponent, ['modelId' => $value])` — the route wins when registered, the component is the
  fallback (see [Modals](modal.md#route-modals))
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
