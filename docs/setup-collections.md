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
| `fields` | Yes | Array of field definitions |

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

## Example: Collection with Multiple Fields

**File:** `app-configs/setup/collections/invoice_templates.yml`

```yaml
title: Invoice Template
titleList: Invoice Templates
key: INVOICE_TEMPLATES
buttonList: 'New Template'
description: ''
fields:
  - name: detailData.name
    label: Name
    type: text
    colspan: 6
  - name: detailData.template_path
    label: Template Path
    type: text
    colspan: 6
```

## Storage Modes: YAML vs. Database

Where collection **schemas** (the definitions above) live is controlled by
`config('noerd.collections.mode')`:

| Config key | Env | Default | Description |
|------------|-----|---------|-------------|
| `collections.mode` | `NOERD_COLLECTIONS_MODE` | `yaml` | `yaml` or `database` |
| `collections.show_definitions_ui` | — | derived | `true` when mode is `database` |
| `collections.setup_yaml_path` | — | `app-configs/setup/collections` | YAML source directory |

- **`yaml` (default):** Schemas live as committed YAML files in `setup_yaml_path`. The definitions
  management UI is hidden — changes are deployed via files.
- **`database`:** Schemas live per tenant in the `setup_collection_definitions` table. The Setup
  area shows a management UI (routes `setup-collection-definitions` /
  `setup-collection-definition.detail`, gated by the `setup.collections.ui` middleware) where
  admins create and edit collection definitions at runtime.

`collections.show_definitions_ui` — the flag the setup navigation gates the management entry on —
is DERIVED from the mode when the service provider registers. Never set it in a config file: as a
key of its own it drifted apart from the mode (a published config edited without the env var), which
left the routes reachable while the navigation entry stayed hidden.

The mode applies to the **Setup** collections only. CMS collection definitions always live in the
database, regardless of this value.

The entry **data** is always stored in the database (`setup_collections` /
`setup_collection_entries`), regardless of the mode.

### Operating in Database Mode

In database mode a tenant without definition rows has no usable collections at all — an empty
"Data Management" sidebar, and no layout for the entries it may already hold. Two things follow:

- **New tenants are seeded automatically.** `Tenant::created` imports every definition from the YAML
  source for the new tenant (`Noerd\Support\SetupCollectionDefinitionImport`), so a fresh tenant
  starts with the same collections a YAML-mode installation has. Nothing happens in `yaml` mode.
- **Newly shipped YAML definitions are NOT imported automatically.** When a module update publishes
  a new collection YAML, run the import again — it is idempotent and updates existing rows in place:

  ```bash
  php artisan noerd:setup-collections:import-yaml --all-tenants
  ```

`php artisan noerd:make-collection` always writes a YAML file and therefore has no effect in
database mode; it warns about that. Create the collection in Setup → Collection Definitions instead,
or import the written file afterwards.

### Switching Modes

Two Artisan commands move definitions between the two storages (see
[Artisan Commands](artisan-commands.md)):

```bash
# yaml -> database
php artisan noerd:setup-collections:import-yaml --all-tenants

# database -> yaml
php artisan noerd:setup-collections:export-yaml --tenant-id=1
```

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

The helper reads from the active storage mode transparently — the same API works in `yaml` and
`database` mode.

## Available Field Types

All standard field types are supported in Setup Collections. See the
[Field Types Reference](field-types.md) for the complete list, including:

- `text`, `email`, `number`, `date`, `time`, `datetime-local`
- `textarea`
- `select`, `picklist`
- `checkbox`
- Registered relation types such as `customerRelation` (see [Relation Field Types](relation-field-types.md))
- `translatableText`, `translatableTextarea`
- And more...

## Best Practices

1. **Use UPPERCASE keys**: The `key` property should be UPPERCASE and unique (e.g., `CUSTOMERS`, `INVOICE_TEMPLATES`)
2. **Keep collections simple**: Setup Collections are best for lookup tables with a few fields
3. **Use meaningful names**: The filename becomes the collection identifier, so use clear, descriptive names
4. **Localize labels**: Use English text as labels — they double as translation keys (map them in `de.json`)
