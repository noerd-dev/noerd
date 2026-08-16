# Field Types Reference

This document provides a comprehensive reference for all available field types in YAML configuration files for **detail components**.

## Example yml File with all field types

```yaml
title: Example Detail
description: An example detail with all field types
tabs:
  - number: 1
    label: General
  - number: 2
    label: Details
  - number: 3
    label: Media
fields:
  # ===========================================
  # TAB 1: General (Basic Types)
  # ===========================================

  # text - Standard text input
  - name: detailData.name
    label: Name
    type: text
    colspan: 6
    required: true

  # phone - Phone input with tel: call button
  - name: detailData.phone
    label: Phone
    type: phone
    colspan: 6

  # email - Email input with mailto: button
  - name: detailData.email
    label: Email
    type: email
    colspan: 6

  # text (number) - Number input
  - name: detailData.quantity
    label: Quantity
    type: number
    colspan: 3

  # text (date) - Date picker
  - name: detailData.birth_date
    label: Birth Date
    type: date
    colspan: 3

  # text (time) - Time picker
  - name: detailData.start_time
    label: Start Time
    type: time
    colspan: 3

  # text (datetime-local) - Datetime picker
  - name: detailData.scheduled_at
    label: Scheduled At
    type: datetime-local
    colspan: 3

  # colorHex - Color picker with HEX value
  - name: detailData.color
    label: Color
    type: colorHex
    colspan: 3

  # textarea - Multi-line text
  - name: detailData.description
    label: Description
    type: textarea
    colspan: 12
    rows: 4

  # checkbox - Boolean toggle
  - name: detailData.is_active
    label: Active
    type: checkbox
    colspan: 3
    live: true

  # checkbox with showIf condition
  - name: detailData.notify_email
    label: Notify
    type: checkbox
    colspan: 3
    showIf: detailData.is_active

  # ===========================================
  # Selection Types
  # ===========================================

  # select - Static dropdown options
  - name: detailData.priority
    label: Priority
    type: select
    colspan: 4
    options:
      - value: low
        label: Low
      - value: medium
        label: Medium
      - value: high
        label: High

  # picklist - Dynamic dropdown from component method
  - name: detailData.warehouse_id
    label: Warehouse
    type: picklist
    picklistField: getWarehouseOptions
    colspan: 4

  # ===========================================
  # Relations
  # ===========================================

  # registered relation type
  - name: detailData.customer_id
    label: Customer
    type: customerRelation
    colspan: 6

  # belongsToMany - Tag-style many-to-many selection
  - name: tagIds
    label: Tags
    type: belongsToMany
    optionsMethod: getTagOptions
    colspan: 6

  # ===========================================
  # Special Selects
  # ===========================================

  # collection-select - CMS Collection dropdown
  - name: detailData.collection_id
    label: Collection
    type: collection-select
    colspan: 6

  # setupCollectionSelect - Setup Collection dropdown
  - name: detailData.country_id
    label: Country
    type: setupCollectionSelect
    collectionKey: countries
    displayField: name
    colspan: 6

  # ===========================================
  # TAB 2: Details (Rich Content & Translatable)
  # ===========================================

  # richText - WYSIWYG editor
  - name: detailData.content
    label: Content
    type: richText
    colspan: 12
    tab: 2

  # translatableText - Multi-language text
  - name: detailData.title
    label: Title
    type: translatableText
    colspan: 12
    tab: 2

  # translatableTextarea - Multi-language textarea
  - name: detailData.summary
    label: Summary
    type: translatableTextarea
    colspan: 12
    tab: 2

  # translatableRichText - Multi-language WYSIWYG
  - name: detailData.body
    label: Body
    type: translatableRichText
    colspan: 12
    tab: 2

  # ===========================================
  # TAB 3: Media & Actions
  # ===========================================

  # image - Media library image selection
  - name: detailData.image_id
    label: Image
    type: image
    colspan: 6
    tab: 3

  # file - File upload
  - name: document
    label: Document
    type: file
    accept: '.pdf,.doc,.docx'
    colspan: 6
    tab: 3

  # button - Action button
  - name: generateCode
    label: 'Generate Code'
    type: button
    colspan: 3
    tab: 3

  # text (readonly) - Read-only field showing generated value
  - name: detailData.code
    label: Code
    type: text
    colspan: 3
    readonly: true
    tab: 3

  # ===========================================
  # Block - Nested field container
  # ===========================================

  - type: block
    title: Address
    colspan: 12
    tab: 3
    fields:
      - name: detailData.street
        label: Street
        type: text
        colspan: 8
      - name: detailData.house_number
        label: House Number
        type: text
        colspan: 4
      - name: detailData.zip
        label: Zip Code
        type: text
        colspan: 4
      - name: detailData.city
        label: City
        type: text
        colspan: 8

  # ===========================================
  # Conditional Display Examples
  # ===========================================

  # showIf with boolean field
  - name: detailData.has_discount
    label: Has Discount
    type: checkbox
    colspan: 3
    live: true
    tab: 3
  - name: detailData.discount_percent
    label: Discount
    type: number
    colspan: 3
    showIf: detailData.has_discount
    tab: 3

  # showIf with value comparison
  - name: detailData.type
    label: Type
    type: select
    colspan: 3
    live: true
    tab: 3
    options:
      - value: private
        label: Private
      - value: business
        label: Business

  - name: detailData.company_name
    label: Company
    type: text
    colspan: 3
    tab: 3
    showIf:
      field: detailData.type
      value: business

  # showIfNot - Hide when condition is true
  - name: detailData.private_notes
    label: Private Notes
    type: textarea
    colspan: 6
    tab: 3
    showIfNot:
      field: detailData.type
      value: business
```

## Overview

| Type | Description | Component |
|------|-------------|-----------|
| `text` | Standard text input (also number, date, time, datetime-local) | `input.blade.php` |
| `phone` | Phone input with a `tel:` call button (opens the local phone app, e.g. FaceTime) | `phone.blade.php` |
| `email` | Email input with a `mailto:` button (opens the local mail client) | `email.blade.php` |
| `currency` | Amount input formatted with the tenant's currency (symbol, separators) | `input-currency.blade.php` |
| `colorHex` | Color picker with HEX value | `color-hex.blade.php` |
| `textarea` | Multi-line text field | `input-textarea.blade.php` |
| `select` | Dropdown with static options or a component method (`optionsMethod`) | `input-select.blade.php` |
| `picklist` | Dropdown with dynamic options (via Livewire method) | `picklist.blade.php` |
| `checkbox` | Boolean checkbox | `checkbox.blade.php` |
| `*Relation` | Registered relation field type such as `customerRelation` or `pageRelation` | `noerd-relation-field.blade.php` |
| `image` | Image selection from Media library | `image.blade.php` |
| `file` | File upload | `file.blade.php` |
| `richText` | TipTap WYSIWYG editor | `rich-text.blade.php` |
| `translatableText` | Multi-language text field | `translatable-text.blade.php` |
| `translatableTextarea` | Multi-language textarea | `translatable-textarea.blade.php` |
| `translatableRichText` | Multi-language rich text editor | `translatable-rich-text.blade.php` |
| `belongsToMany` | Many-to-many tag selection with search | `belongs-to-many.blade.php` |
| `collection-select` | CMS Collection selection | `input-collection-select.blade.php` |
| `setupCollectionSelect` | Setup Collection selection | `setup-collection-select.blade.php` |
| `button` | Action button | `button.blade.php` |
| `icon` | Heroicon picker (opens the icon-picker modal) | `icon.blade.php` |
| `spacer` | Empty grid cell reserving its `colspan` (deliberate blank column) | `spacer.blade.php` |
| `block` | Container for nested fields | (in `block.blade.php`) |

**Fallback behavior:** A `type` that is not registered (and does not end in `Relation`) renders as
a generic HTML input with that type attribute — this is how `number`, `date`, `time` and
`datetime-local` work. Unregistered `*Relation` types throw instead (see
[Relation Field Types](relation-field-types.md)).

## Common Options

These options are available for most field types:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | required | Property path (e.g., `detailData.email`, `detailData.customer_id`) |
| `label` | string | required | Translation key for the field label |
| `helpText` | string | - | Explanation shown as a tooltip behind a question-mark icon next to the label (translation key) |
| `type` | string | `text` | Field type |
| `colspan` | int | `3` | Width in grid columns (1-12) |
| `required` | bool | `false` | Show required indicator on label |
| `readonly` | bool | `false` | Make field read-only |
| `live` | bool | `false` | Enable real-time updates (`wire:model.live.debounce`) |
| `placeholder` | string | - | Placeholder text (translation key); supported by text-like inputs, selects and picklists |
| `tab` | int | `1` | Tab number for multi-tab forms |
| `showIf` | string/object | - | Condition to show the field |
| `showIfNot` | string/object | - | Condition to hide the field |
| `show` | bool | `true` | Statically show/hide the field |

### helpText

Any field may explain itself. `helpText` renders a small question-mark icon next to the label; hovering it
(or tapping / focussing it) shows the text as a tooltip. It is translated with `__()` and works with every
field type in every theme (`default`, `compact`, `numbered`).

```yaml
- name: detailData.key
  label: Key
  type: text
  colspan: 6
  required: true
  helpText: 'Technical identifier, uppercase without spaces (e.g. ADMIN).'
```

> Not to be confused with the block-level `description` (on `type: block`), which is a visible sub-heading.

---

## Basic Types

### text

Standard text input field. Also handles HTML5 input types like `number`, `date`, `time`, and `datetime-local`.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `type` | string | `text` | Input type: `text`, `number`, `date`, `time`, `datetime-local` |
| `readonly` | bool | `false` | Make field read-only |
| `live` | bool | `false` | Enable real-time updates |
| `required` | bool | `false` | Show required indicator |

**YAML Example:**

```yaml
# Simple text field
- name: detailData.name
  label: Name
  type: text
  colspan: 6

# Number field
- name: detailData.quantity
  label: Quantity
  type: number
  colspan: 3

# Date field
- name: detailData.birth_date
  label: Birth Date
  type: date
  colspan: 4

# Time field
- name: detailData.start_time
  label: Start Time
  type: time
  colspan: 4

# Datetime field
- name: detailData.scheduled_at
  label: Scheduled At
  type: datetime-local
  colspan: 6

# Read-only field with live updates
- name: detailData.code
  label: Code
  type: text
  colspan: 4
  readonly: true
  live: true
```

**Notes:**
- `date` type automatically truncates datetime values to date only (YYYY-MM-DD)
- `time` type automatically truncates to HH:MM format

---

### phone

Phone input (`<input type="tel">`) with a trailing call button that opens `tel:{number}` via the
local phone app (e.g. FaceTime on macOS). The link always uses the CURRENT input value — edited
but unsaved values included — and strips all formatting except digits and a leading `+`
(`+49 (0)171 / 123-456` → `tel:+490171123456`). The button is hidden while the field is empty and
stays clickable on read-only fields (calling is a read action).

**YAML Example:**

```yaml
- name: detailData.phone
  label: Phone
  type: phone
  colspan: 6
```

---

### email

Email input (`<input type="email">`) with a trailing button that opens `mailto:{address}` in the
local mail client (e.g. Apple Mail on macOS). The link always uses the CURRENT input value —
edited but unsaved values included. The button is hidden while the field is empty and stays
clickable on read-only fields.

`email` used to be an unregistered fallback type rendering a plain HTML email input; it is now a
registered field type, so existing YAMLs with `type: email` get the mailto button automatically —
no configuration change needed.

**YAML Example:**

```yaml
- name: detailData.email
  label: Email
  type: email
  colspan: 6
  required: true
```

---

### currency

Amount input formatted with the tenant's currency: the symbol and the decimal/thousands separators
come from the tenant setting in Setup (fallback: `config('noerd.currency')`). The value is stored
as a plain decimal.

**YAML Example:**

```yaml
- name: detailData.amount
  label: Amount
  type: currency
  colspan: 6
```

---

### colorHex

Color picker with HEX value input. Combines a text input for manual HEX entry with a native color picker for visual selection. Both inputs are synchronized.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `readonly` | bool | `false` | Make field read-only |
| `live` | bool | `false` | Enable real-time updates |
| `required` | bool | `false` | Show required indicator |

**YAML Example:**

```yaml
# Basic color picker
- name: detailData.color
  label: Color
  type: colorHex
  colspan: 4

# Color picker with live updates
- name: detailData.background_color
  label: Background Color
  type: colorHex
  colspan: 4
  live: true

# Read-only color display
- name: detailData.theme_color
  label: Theme Color
  type: colorHex
  colspan: 4
  readonly: true
```

**Database Value:**
```
#efefef
```

**Notes:**
- Stores the color as a 7-character HEX string (e.g., `#efefef`)
- The text input allows manual entry with a `#` prefix
- The color picker synchronizes with the text input in both directions
- Maximum length is 7 characters

---

### textarea

Multi-line text field.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `rows` | int | `8` | Number of visible text rows |
| `readonly` | bool | `false` | Make field read-only |
| `required` | bool | `false` | Show required indicator |

**YAML Example:**

```yaml
# Standard textarea
- name: detailData.description
  label: Description
  type: textarea
  colspan: 12

# Textarea with custom rows
- name: detailData.notes
  label: Notes
  type: textarea
  colspan: 12
  rows: 4

# Read-only textarea
- name: detailData.system_log
  label: Log
  type: textarea
  colspan: 12
  readonly: true
```

---

### checkbox

Boolean checkbox field.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `readonly` | bool | `false` | Disable the checkbox |
| `live` | bool | `false` | Enable real-time updates |

**YAML Example:**

```yaml
# Simple checkbox
- name: detailData.is_active
  label: Active
  type: checkbox
  colspan: 3

# Checkbox with live updates (useful for conditional fields)
- name: detailData.has_discount
  label: Has Discount
  type: checkbox
  colspan: 3
  live: true

# Disabled checkbox
- name: detailData.is_system
  label: System
  type: checkbox
  colspan: 3
  readonly: true
```

**Notes:**
- Handles both boolean values and string "1"/"0" values correctly
- Vertically aligns with other form elements

---

## Selection Types

### select

Dropdown with statically defined options in the YAML file, or options provided by a component
method.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `options` | array | required* | Array of options |
| `optionsMethod` | string | - | Alternative to `options`: name of a component method returning the options array |
| `live` | bool | `false` | Enable real-time updates |
| `required` | bool | `false` | Show required indicator |

*Either `options` or `optionsMethod` must be set.

**Option Format:**
```yaml
options:
  - value: key1
    label: Label 1
  - value: key2
    label: Label 2
# OR simple format (value = label):
options:
  - 'Option 1'
  - 'Option 2'
```

**YAML Example:**

```yaml
# Select with value/label pairs
- name: detailData.priority
  label: Priority
  type: select
  colspan: 6
  options:
    - value: low
      label: Low
    - value: medium
      label: Medium
    - value: high
      label: High

# Select with simple options
- name: detailData.status
  label: Status
  type: select
  colspan: 6
  options:
    - 'Draft'
    - 'Published'
    - 'Archived'

# Select with live updates
- name: detailData.category
  label: Category
  type: select
  colspan: 6
  live: true
  options:
    - value: a
      label: Category A
    - value: b
      label: Category B
```

---

### picklist

Dropdown with dynamically loaded options from a Livewire component method.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `picklistField` | string | required | Name of the option provider: a component method, or a name registered in the `PicklistRegistry` (the component method wins; see [Extension Registries](extension-registries.md)) |
| `live` | bool | `false` | Enable real-time updates |
| `required` | bool | `false` | Show required indicator |

**YAML Example:**

```yaml
- name: detailData.warehouse_id
  label: Warehouse
  type: picklist
  picklistField: getWarehouseOptions
  colspan: 6
```

**PHP Example (Livewire Component):**

```php
public function getWarehouseOptions(): array
{
    return Warehouse::where('tenant_id', auth()->user()->selected_tenant_id)
        ->pluck('name', 'id')
        ->toArray();
}
```

**Notes:**
- The method must return an associative array `[id => label, ...]`
- Useful when options depend on other model data or complex queries

---

## Relations

### Registered Relation Types

Relations must use explicit registered field types such as `customerRelation`, `vehicleRelation`, `authorRelation` or `pageRelation`.

**YAML Example:**

```yaml
- name: detailData.customer_id
  label: Customer
  type: customerRelation
  colspan: 6
```

**Important:**
- There is no generic `type: relation` — every relation field uses its registered type
- `modalComponent` and `relationField` are not YAML options for registered relation fields
- The list component, detail component and display title resolver are defined centrally in the module service provider
- Unregistered relation field types fail explicitly during rendering

See [relation-field-types.md](relation-field-types.md) for the full registration guide.

---

### belongsToMany

Tag-style selection for many-to-many relationships with search functionality.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `optionsMethod` | string | required | Component method returning available options |
| `required` | bool | `false` | Show required indicator |

**YAML Example:**

```yaml
- name: tagIds
  label: Tags
  type: belongsToMany
  optionsMethod: getTagOptions
  colspan: 12
```

**PHP Example (Livewire Component):**

```php
public array $tagIds = [];

public function mount(Article $article): void
{
    $this->tagIds = $article->tags->pluck('id')->toArray();
}

public function getTagOptions(): array
{
    return Tag::where('tenant_id', auth()->user()->selected_tenant_id)
        ->pluck('name', 'id')
        ->toArray();
}

public function store(): void
{
    $article = Article::updateOrCreate(
        ['id' => $this->modelId],
        $this->detailData
    );

    $article->tags()->sync($this->tagIds);
}
```

**Notes:**
- Features built-in search with keyboard navigation (arrow keys, Enter, Escape)
- Selected items appear as removable tags
- The component property must be an array of IDs (e.g., `$tagIds`)

---

## Media Types

### image

Image selection from the Media library.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | required | Field name (stores Media ID or URL) |
| `label` | string | required | Translation key |

**YAML Example:**

```yaml
- name: detailData.image_id
  label: Image
  type: image
  colspan: 6
```

**PHP Example (Livewire Component):**

```php
public function openSelectMediaModal(string $fieldName): void
{
    Noerd::modal('media-list', [
        'context' => $fieldName,
        'listActionMethod' => 'selectAction',
    ]);
}

#[On('mediaSelected')]
public function mediaSelected($mediaId, $context): void
{
    $this->detailData[$context] = $mediaId;
}

public function deleteImage(string $fieldName): void
{
    $this->detailData[str_replace('detailData.', '', $fieldName)] = null;
}
```

**Notes:**
- Shows a preview thumbnail when an image is selected
- Includes delete button with confirmation dialog
- Stores Media model ID as the value

---

### file

File upload field with Livewire integration.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `multiple` | bool | `false` | Allow multiple file selection |
| `accept` | string | - | Accepted file types (e.g., `.pdf,.doc`) |
| `live` | bool | `false` | Enable real-time upload |

**YAML Example:**

```yaml
# Single file upload
- name: document
  label: Document
  type: file
  colspan: 6

# Multiple files with type restriction
- name: attachments
  label: Attachments
  type: file
  multiple: true
  accept: '.pdf,.doc,.docx'
  colspan: 12

# Image upload with live preview
- name: photo
  label: Photo
  type: file
  accept: 'image/*'
  live: true
  colspan: 6
```

**PHP Example (Livewire Component):**

```php
use Livewire\WithFileUploads;

class DocumentDetail extends Component
{
    use WithFileUploads;

    public $document;

    public function store(): void
    {
        $this->validate([
            'document' => 'required|file|mimes:pdf|max:10240',
        ]);

        $path = $this->document->store('documents');
        // ...
    }
}
```

---

## Rich Text

### richText

TipTap WYSIWYG editor for formatted text content.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | required | Field name |
| `label` | string | required | Translation key |

**YAML Example:**

```yaml
- name: detailData.content
  label: Content
  type: richText
  colspan: 12
```

**Notes:**
- Uses TipTap editor with standard formatting options
- Content is stored as HTML
- Automatically retrieves content from `$detailData` array

---

## Translatable Fields

These field types store content as JSON objects with language keys (e.g., `{"de": "...", "en": "..."}`).

### translatableText

Multi-language single-line text field.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `readonly` | bool | `false` | Make field read-only |
| `live` | bool | `false` | Enable real-time updates |

**YAML Example:**

```yaml
- name: detailData.title
  label: Title
  type: translatableText
  colspan: 12
```

**Database Value:**
```json
{"de": "Deutscher Titel", "en": "English Title"}
```

---

### translatableTextarea

Multi-language multi-line text field.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `readonly` | bool | `false` | Make field read-only |
| `required` | bool | `false` | Show required indicator |

**YAML Example:**

```yaml
- name: detailData.description
  label: Description
  type: translatableTextarea
  colspan: 12
```

---

### translatableRichText

Multi-language WYSIWYG editor.

**YAML Example:**

```yaml
- name: detailData.body
  label: Body
  type: translatableRichText
  colspan: 12
```

**Notes:**
- All translatable fields react to `session('selectedLanguage')` (defaults to `'de'`)
- Language switching is handled globally by the application

---

## Special Types

### collection-select

Dropdown for selecting CMS Collection entries.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `readonly` | bool | `false` | Disable the select |
| `live` | bool | `false` | Enable real-time updates |
| `required` | bool | `false` | Show required indicator |

**YAML Example:**

```yaml
- name: detailData.collection_id
  label: Collection
  type: collection-select
  colspan: 6
```

**Notes:**
- Automatically loads all CMS Collections for the current tenant
- Includes a search button that opens a modal with collection entries

---

### setupCollectionSelect

Dropdown for selecting entries from a Setup Collection.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `collectionKey` | string | required | The setup collection key |
| `displayField` | string | `name` | Field to display as option label |
| `valueField` | string | entry id | Field stored as the value instead of the entry id |
| `live` | bool | `false` | Enable real-time updates |
| `required` | bool | `false` | Show required indicator |

**YAML Example:**

```yaml
- name: detailData.country_id
  label: Country
  type: setupCollectionSelect
  collectionKey: countries
  displayField: name
  colspan: 6
```

**Notes:**
- Supports translatable display fields
- Automatically handles locale fallback (current → 'de' → any available)

---

### icon

Heroicon picker: the field shows the current icon with its name and opens the searchable
`noerd::icon-picker` modal on click. The selected icon name is stored as a string.

**YAML Example:**

```yaml
- name: detailData.icon
  label: Icon
  type: icon
  colspan: 6
```

---

### spacer

Renders nothing but still occupies its `colspan`, reserving an empty grid cell — use it to keep a
deliberate blank column so the next field starts on a new row. Needs no `name`; only `type: spacer`
and `colspan` are relevant. The blank height follows the active theme (`spacerClass` in
`theme.yml`).

**YAML Example:**

```yaml
- name: detailData.name
  label: Name
  type: text
  colspan: 6
- type: spacer
  colspan: 6
- name: detailData.email
  label: Email
  type: text
  colspan: 6
```

---

### button

Action button that triggers a Livewire component method.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | required | Component method to call on click |
| `label` | string | required | Button text |

**YAML Example:**

```yaml
- name: generateCode
  label: 'Generate Code'
  type: button
  colspan: 3
```

**PHP Example (Livewire Component):**

```php
public function generateCode(): void
{
    $this->detailData['code'] = strtoupper(Str::random(8));
}
```

**Notes:**
- Button vertically aligns with input fields
- Uses primary button styling

---

### block

Container for grouping nested fields with an optional title.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `title` | string | - | Block title (translation key) |
| `description` | string | - | Block description |
| `fields` | array | required | Nested field definitions |
| `cols` | int | `12` | Grid columns for nested fields |
| `colspan` | int | `12` | Block width in parent grid |

**YAML Example:**

```yaml
- type: block
  title: Address
  colspan: 12
  fields:
    - name: detailData.street
      label: Street
      type: text
      colspan: 8
    - name: detailData.zip
      label: Zip Code
      type: text
      colspan: 4
    - name: detailData.city
      label: City
      type: text
      colspan: 8
    - name: detailData.country
      label: Country
      type: text
      colspan: 4

# Block without title (just for layout grouping)
- type: block
  colspan: 6
  fields:
    - name: detailData.first_name
      label: First Name
      type: text
      colspan: 12
    - name: detailData.last_name
      label: Last Name
      type: text
      colspan: 12
```

**Notes:**
- Blocks can be nested within blocks
- Useful for visual grouping and responsive layouts

---

## Conditional Display

Fields can be shown or hidden based on other field values using `showIf` and `showIfNot`.

### showIf

Show the field only when a condition is true.

**String Format (Boolean Check):**
```yaml
# Show when detailData.is_business is truthy
- name: detailData.company_name
  label: Company
  type: text
  showIf: detailData.is_business
```

**Object Format (Value Check):**
```yaml
# Show when detailData.type equals 'business'
- name: detailData.company_name
  label: Company
  type: text
  showIf:
    field: detailData.type
    value: business
```

### showIfNot

Hide the field when a condition is true.

**String Format:**
```yaml
# Hide when detailData.is_private is truthy
- name: detailData.public_notes
  label: Notes
  type: textarea
  showIfNot: detailData.is_private
```

**Object Format:**
```yaml
# Hide when detailData.status equals 'archived'
- name: detailData.edit_notes
  label: Notes
  type: textarea
  showIfNot:
    field: detailData.status
    value: archived
```

**Complete Example:**

```yaml
fields:
  # Checkbox with live updates to trigger conditional logic
  - name: detailData.is_business
    label: Business
    type: checkbox
    colspan: 12
    live: true

  # These fields only show when is_business is checked
  - name: detailData.company_name
    label: Company
    type: text
    colspan: 6
    showIf: detailData.is_business
  - name: detailData.vat_number
    label: VAT Number
    type: text
    colspan: 6
    showIf: detailData.is_business

  # These fields only show when is_business is NOT checked
  - name: detailData.first_name
    label: First Name
    type: text
    colspan: 6
    showIfNot: detailData.is_business
  - name: detailData.last_name
    label: Last Name
    type: text
    colspan: 6
    showIfNot: detailData.is_business
```

**Notes:**
- Uses Alpine.js `x-show` directive for client-side visibility
- For reactive conditional display, set `live: true` on the controlling field

---

## Component Locations

All form components are located in:
```
app-modules/noerd/resources/views/components/forms/
```

The main rendering logic is in:
```
app-modules/noerd/resources/views/components/detail/block.blade.php
```

## Custom Field Types

- Register shared field types in `NoerdServiceProvider` via `Noerd\Services\FieldTypeRegistry`
- Register module-specific field types in the module's own service provider, for example `CmsServiceProvider`
- In YAML you still only reference the field type:

```yaml
- name: detailData.custom_attributes.page_id
  label: booking_label_page
  type: pageRelation
```

Use `FieldTypeDefinition::include()` for Blade partials and `FieldTypeDefinition::livewire()` for dedicated Livewire field components.

Registered relation types are configured through `RelationFieldRegistry` and rendered by `noerd-relation-field`.
