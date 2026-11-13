# Setup Collections

Setup Collections allow you to create custom data lists in the Setup area of your application. They are ideal for managing simple lookup tables like countries, categories, or templates without writing any code.

## Quick Start

1. Create a YAML file in `app-configs/setup/collections/`
2. The collection automatically appears in the Setup navigation

That's it. No migrations, no models, no controllers required.

## YAML Structure

| Property | Required | Description |
|----------|----------|-------------|
| `title` | Yes* | Singular title (e.g., "Customer") |
| `titleList` | No | Plural title for the list view (e.g., "Customers"); defaults to the filename |
| `key` | No | Unique identifier in UPPERCASE (e.g., "CUSTOMERS"); defaults to the UPPERCASED filename (`Noerd\Support\SetupCollectionDefinitionData`) |
| `buttonList` | No | Button text for creating new entries (default `New Entry`). |
| `description` | No | Optional description shown in the detail view |
| `fields` | Yes* | Array of field definitions |

\* By convention — a missing `title` resolves to an empty string, missing `fields` to an empty form.

## Example: Simple Collection

**File:** `app-configs/setup/collections/customers.yml`

```yaml
title: Customer
titleList: Customers
key: CUSTOMERS
buttonList: 'New Entry'
description: ''
fields:
  - name: detailData.name
    label: Name
    type: text
    colspan: 6
```

## Where Definitions and Entries Live

Collection **schemas** (the definitions above) are YAML files of the installation in
`config('noerd.collections.setup_yaml_path')` (default `app-configs/setup/collections`) — committed
and deployed like every other app config. The entry **data** is stored in the database
(`setup_collections` / `setup_collection_entries`), per tenant.

## Using Collections in Other Components

### setupCollectionSelect Field Type

Use the `setupCollectionSelect` field type in your detail YAML files to create a dropdown that displays entries from a Setup Collection:

```yaml
- name: detailData.country_id
  label: Country
  type: setupCollectionSelect
  collectionKey: countries
  displayField: name
  colspan: 6
```

**Options:**

| Option | Required | Description |
|--------|----------|-------------|
| `collectionKey` | Yes | The collection filename without `.yml` extension |
| `displayField` | No | Field to display as option label (default: `name`); translatable values resolve to the selected language |
| `valueField` | No | Entry field stored as the option value (e.g. `code`); without it the entry id is stored |
| `live` | No | Enable real-time updates |
| `required` | No | Show required indicator |
| `readonly` | No | Renders the select disabled |

### SetupCollectionHelper

For programmatic access to collection data, use the `SetupCollectionHelper` class:

```php
use Noerd\Helpers\SetupCollectionHelper;

// Get field definitions for a collection
$fields = SetupCollectionHelper::getCollectionFields('customers');

// Get table column configuration
$tableColumns = SetupCollectionHelper::getCollectionTable('invoice_templates');

// Get all available collections
$allCollections = SetupCollectionHelper::getAllCollections();

// Select options of a collection's entries (value/label pairs)
$options = SetupCollectionHelper::selectOptions('countries', 'name', 'code');
```

**Available Methods:**

| Method | Returns | Description |
|--------|---------|-------------|
| `getCollectionFields(?string $collection)` | `?array` | Returns the full YAML configuration including fields |
| `getCollectionTable(string $collection)` | `array` | Returns column definitions for list display |
| `getAllCollections()` | `array` | Returns all collections with their metadata |
| `selectOptions(string $collectionKey, string $displayField = 'name', ?string $valueField = null)` | `array` | `[['value' => …, 'label' => …], …]` built from the current tenant's entries — the same resolution the `setupCollectionSelect` element and the list picklist badges use |

`selectOptions()` is memoized per request, tenant and language; call
`SetupCollectionHelper::clearSelectOptionsCache()` after writing entries (tests).

## Available Field Types

`noerd:make-collection` offers the curated list
`SetupCollectionHelper::FIELD_TYPES`: `text`, `textarea`, `translatableText`,
`translatableTextarea`, `translatableRichText`, `image`, `email`, `tel`, `checkbox`, `select`,
`date`, `datetime`, `number` — deliberately without structural and relation types. A hand-written
YAML (`yaml` mode) may use any registered type of the [Field Types Reference](field-types.md).

## Best Practices

1. **Scaffold with `noerd:make-collection`**: it writes the block-style YAML for you (see [Artisan Commands](artisan-commands.md#noerdmake-collection))
2. **Keep collections simple**: Setup Collections are best for lookup tables with a few fields; the
   filename is the collection identifier, the UPPERCASE `key` must be unique
