# YAML Configuration

The Noerd Framework uses YAML files to define lists, forms, and navigation.

## Overview

YAML configurations are located in two places:

1. **`app-configs/{app-name}/`** - Project-specific configurations
2. **`app-modules/{module}/app-contents/{app-name}/`** - Module default configurations

### Directory Structure

```
app-configs/
└── {app-name}/
    ├── lists/           # Table configurations
    │   └── *.yml
    ├── details/         # Form configurations
    │   └── *.yml
    └── navigation.yml   # Navigation
```

## List Configuration

List configurations define tables and their columns.

### File Location

```
lists/{resource}-list.yml
```

### Basic Structure

```yaml
title: noerd_label_users
newLabel: noerd_label_new_user
component: user-detail
disableSearch: false
redirectAction: ''
description: ''
columns:
  - { field: email, label: noerd_label_email, width: 10 }
  - { field: name, label: noerd_label_name, width: 10 }
```

### Options

| Option | Type | Description |
|--------|------|-------------|
| `title` | string | Page title (translation key) |
| `newLabel` | string | Label for "Create new" button |
| `component` | string | Detail component for modal |
| `disableSearch` | bool | Disable search |
| `redirectAction` | string | Route after action |
| `description` | string | Description text |
| `columns` | array | Column definitions |

### Column Definition

```yaml
columns:
  # Simple column
  - { field: email, label: noerd_label_email, width: 10 }

  # Column with type
  - { field: created_at, label: noerd_label_created, width: 8, type: date }

  # Column with relationship
  - { field: category.name, label: noerd_label_category, width: 10 }

  # Action column
  - field: action
    width: 3
    actions:
      - { label: noerd_label_edit, heroicon: pencil, action: edit }
      - { label: noerd_label_delete, heroicon: trash, action: delete, confirm: noerd_confirm_delete }
```

### Column Options

| Option | Type | Description |
|--------|------|-------------|
| `field` | string | Model attribute or path (e.g., `category.name`) |
| `label` | string | Column header (translation key) |
| `width` | int | Column width (out of 24) |
| `type` | string | Display type (`text`, `date`, `boolean`, `badge`) |
| `sortable` | bool | Sortable (default: true) |

### Actions

```yaml
actions:
  - label: noerd_label_login_as_user
    heroicon: user
    action: loginAsUser
    confirm: noerd_confirm_login_as_user
```

| Option | Type | Description |
|--------|------|-------------|
| `label` | string | Action text |
| `heroicon` | string | Heroicon name |
| `action` | string | Method name in component |
| `confirm` | string | Confirmation text |

## Detail Configuration

Detail configurations define forms.

### File Location

```
details/{resource}-detail.yml
```

### Basic Structure

```yaml
title: noerd_label_user
description: ''
fields:
  - { name: user.email, label: noerd_label_email, type: text }
  - { name: user.name, label: noerd_label_name, type: text }
```

### Options

| Option | Type | Description |
|--------|------|-------------|
| `title` | string | Form title |
| `description` | string | Description text |
| `tabs` | array | Tab definitions |
| `fields` | array | Field definitions |

### Field Types

| Type | Description |
|------|-------------|
| `text` | Single-line text field |
| `textarea` | Multi-line text field |
| `richText` | Rich text editor |
| `translatableRichText` | Translatable rich text |
| `translatableTextarea` | Translatable textarea |
| `checkbox` | Checkbox |
| `select` | Dropdown select |
| `enum` | Enum select |
| `picklist` | Multi-select |
| `relation` | Relationship select |
| `belongsToMany` | Many-to-many relationship |
| `collection-select` | Collection select |
| `setupCollectionSelect` | Setup collection select |
| `image` | Image upload |
| `file` | File upload |
| `block` | Nested fields |

### Field Options

```yaml
fields:
  - name: model.email
    label: noerd_label_email
    type: text
    required: true
    colspan: 6
    placeholder: email@example.com

  - name: model.status
    label: noerd_label_status
    type: enum
    enumClass: App\Enums\Status
    colspan: 6

  - name: model.category_id
    label: noerd_label_category
    type: relation
    relation: categories
    displayField: name
    colspan: 12
```

| Option | Type | Description |
|--------|------|-------------|
| `name` | string | Model attribute path |
| `label` | string | Field label |
| `type` | string | Field type |
| `required` | bool | Required field |
| `colspan` | int | Width (1-12, grid system) |
| `placeholder` | string | Placeholder text |
| `tab` | int | Tab number (default: 1) |
| `showIf` | string | Condition to show |
| `showIfNot` | string | Condition to hide |

### Enum Fields

```yaml
- name: model.status
  label: noerd_label_status
  type: enum
  enumClass: App\Enums\OrderStatus
```

### Relation Fields

```yaml
- name: model.customer_id
  label: noerd_label_customer
  type: relation
  relation: customers
  displayField: name
```

### Select with Options

```yaml
- name: model.priority
  label: noerd_label_priority
  type: select
  options:
    - { value: low, label: Low }
    - { value: medium, label: Medium }
    - { value: high, label: High }
```

### Block Type (Nested Fields)

```yaml
- type: block
  title: noerd_label_address
  fields:
    - { name: model.street, label: noerd_label_street, type: text, colspan: 8 }
    - { name: model.zip, label: noerd_label_zip, type: text, colspan: 4 }
    - { name: model.city, label: noerd_label_city, type: text, colspan: 12 }
```

### Conditional Fields

```yaml
# Show field only when model.type == 'business'
- name: model.company_name
  label: noerd_label_company
  type: text
  showIf: model.type === 'business'

# Hide field when model.is_private == true
- name: model.public_notes
  label: noerd_label_notes
  type: textarea
  showIfNot: model.is_private
```

## Tabs

Forms can be organized in tabs.

### YAML Configuration

```yaml
title: noerd_label_item
tabs:
  - { number: 1, label: noerd_tab_general }
  - { number: 2, label: noerd_tab_settings }
fields:
  # Tab 1 (default)
  - { name: model.name, label: noerd_label_name, type: text, colspan: 6 }
  - { name: model.description, label: noerd_label_description, type: textarea, colspan: 6 }

  # Tab 2
  - { name: model.setting_a, label: noerd_label_setting_a, type: checkbox, colspan: 6, tab: 2 }
  - { name: model.setting_b, label: noerd_label_setting_b, type: text, colspan: 6, tab: 2 }
```

### Blade Implementation

```blade
<x-noerd::tabs :layout="$pageLayout" />

@foreach($pageLayout['tabs'] ?? [['number' => 1]] as $tab)
    <div x-show="currentTab === {{ $tab['number'] }}">
        @php
            $tabFields = array_filter(
                $pageLayout['fields'] ?? [],
                fn($field) => ($field['tab'] ?? 1) === $tab['number']
            );
            $tabLayout = array_merge($pageLayout, ['fields' => array_values($tabFields)]);
            if ($tab['number'] !== 1) {
                unset($tabLayout['title'], $tabLayout['description']);
            }
        @endphp
        @include('noerd::components.detail.block', $tabLayout)
    </div>
@endforeach
```

## Navigation

Navigation is defined in `navigation.yml`.

### Basic Structure

```yaml
- title: noerd_label_setup
  name: setup
  hidden: true
  route: setup
  block_menus:
    - title: noerd_nav_administration
      navigations:
        - { title: noerd_nav_users, route: users, heroicon: users }
        - { title: noerd_nav_user_roles, route: user-roles, heroicon: shield-check }
    - title: noerd_nav_data_management
      dynamic: setup-collections
```

### Options

| Option | Type | Description |
|--------|------|-------------|
| `title` | string | Menu title |
| `name` | string | Unique name |
| `route` | string | Target route |
| `hidden` | bool | Hide in menu |
| `heroicon` | string | Heroicon name |
| `block_menus` | array | Submenus |
| `navigations` | array | Navigation entries |
| `dynamic` | string | Dynamic navigation (`collections`, `setup-collections`) |

### Dynamic Navigation

```yaml
- title: noerd_nav_collections
  dynamic: collections
```

Automatically populated with entries from `/app-configs/cms/collections/*.yml`.

## StaticConfigHelper

The `StaticConfigHelper` class loads YAML configurations.

### Methods

```php
// Load detail configuration
$fields = StaticConfigHelper::getComponentFields('user-detail');

// Load list configuration
$config = StaticConfigHelper::getTableConfig('users-list');

// Load navigation
$nav = StaticConfigHelper::getNavigationStructure();

// Get current app
$app = StaticConfigHelper::getCurrentApp();
```

### Fallback System

Configuration search occurs in this order:

1. `app-configs/{current_app}/...`
2. `app-configs/{other_allowed_apps}/...`
3. `app-modules/{module}/app-contents/{app}/...`

## Best Practices

### Use Inline Style

Always use inline/flow style for columns and fields:

```yaml
# Correct
columns:
  - { field: name, label: noerd_label_name, width: 10 }

# Avoid
columns:
  - field: name
    label: noerd_label_name
    width: 10
```

### Translation Keys

Always use translation keys instead of hardcoded text:

```yaml
# Correct
label: noerd_label_email

# Avoid
label: E-Mail
```

### Modular Configuration

Keep configurations modular and reusable:

```yaml
# Base configuration in module
app-modules/customer/app-contents/customer/details/customer-detail.yml

# Project-specific customization
app-configs/customer/details/customer-detail.yml
```

## Next Steps

- [Creating Modules](creating-modules.md) - Develop your own modules
- [Artisan Commands](artisan-commands.md) - Available CLI commands
