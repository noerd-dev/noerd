# Setup Collections

Setup Collections allow you to create custom data lists in the Setup area of your application. They are ideal for managing simple lookup tables like countries, categories, or templates without writing any code.

## Quick Start

1. Create a YAML file in `app-configs/setup/collections/`
2. The collection automatically appears in the Setup navigation

That's it. No migrations, no models, no controllers required.

## YAML Structure

| Property | Required | Description |
|----------|----------|-------------|
| `title` | Yes | Singular title (e.g., "Customer") |
| `titleList` | Yes | Plural title for the list view (e.g., "Customers") |
| `key` | Yes | Unique identifier in UPPERCASE (e.g., "CUSTOMERS") |
| `buttonList` | No | Button text for creating new entries |
| `description` | No | Optional description shown in the detail view |
| `hasPage` | No | Whether collection entries have a CMS page (default: false) |
| `fields` | Yes | Array of field definitions |

## Example: Simple Collection

**File:** `app-configs/setup/collections/customers.yml`

```yaml
title: Kunde
titleList: Kunden
key: CUSTOMERS
buttonList: 'Neuer Eintrag'
description: ''
hasPage: false
fields:
  - name: model.name
    label: Name
    type: text
    colspan: 6
```

## Example: Collection with Multiple Fields

**File:** `app-configs/setup/collections/invoice_templates.yml`

```yaml
title: Rechnungsvorlage
titleList: Rechnungsvorlagen
key: INVOICE_TEMPLATES
buttonList: 'Neue Vorlage'
description: ''
fields:
  - name: model.name
    label: Name
    type: text
    colspan: 6
  - name: model.template_path
    label: Template Path
    type: text
    colspan: 6
```

## Using Collections in Other Components

### setupCollectionSelect Field Type

Use the `setupCollectionSelect` field type in your detail YAML files to create a dropdown that displays entries from a Setup Collection:

```yaml
- name: detailData.country_id
  label: noerd_label_country
  type: setupCollectionSelect
  collectionKey: countries
  displayField: name
  colspan: 6
```

**Options:**

| Option | Required | Description |
|--------|----------|-------------|
| `collectionKey` | Yes | The collection filename without `.yml` extension |
| `displayField` | No | Field to display as option label (default: `name`) |
| `live` | No | Enable real-time updates |
| `required` | No | Show required indicator |

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
```

**Available Methods:**

| Method | Returns | Description |
|--------|---------|-------------|
| `getCollectionFields(string $collection)` | `?array` | Returns the full YAML configuration including fields |
| `getCollectionTable(string $collection)` | `array` | Returns column definitions for list display |
| `getAllCollections()` | `array` | Returns all collections with their metadata |

## Available Field Types

All standard field types are supported in Setup Collections. See the [Field Types Reference](/docs/field-types) for the complete list, including:

- `text`, `email`, `number`, `date`, `time`, `datetime-local`
- `textarea`
- `select`, `picklist`
- `checkbox`
- `relation`
- `translatableText`, `translatableTextarea`
- And more...

## Best Practices

1. **Use UPPERCASE keys**: The `key` property should be UPPERCASE and unique (e.g., `CUSTOMERS`, `INVOICE_TEMPLATES`)
2. **Keep collections simple**: Setup Collections are best for lookup tables with a few fields
3. **Use meaningful names**: The filename becomes the collection identifier, so use clear, descriptive names
4. **Localize labels**: Use translation keys for field labels to support multiple languages
