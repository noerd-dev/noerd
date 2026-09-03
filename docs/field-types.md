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

  # registered relation type (see relation-field-types.md)
  - name: detailData.item_id
    label: Item
    type: itemRelation
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

| Type | Description | Element template |
|------|-------------|------------------|
| `text` | Standard text input (also `number`, `date`, `time`, `datetime-local` via the fallback below) | `input.blade.php` |
| `phone` | Phone input with a `tel:` call button (opens the local phone app, e.g. FaceTime) | `phone.blade.php` |
| `email` | Email input with a `mailto:` button (opens the local mail client) | `email.blade.php` |
| `currency` | Amount input formatted with the tenant's currency (symbol, separators) | `input-currency.blade.php` |
| `colorHex` | Color picker with HEX value | `color-hex.blade.php` |
| `textarea` | Multi-line text field | `input-textarea.blade.php` |
| `select` | Dropdown with static options or a component method (`optionsMethod`) | `input-select.blade.php` |
| `picklist` | Dropdown with dynamic options (via Livewire method) | `picklist.blade.php` |
| `checkbox` | Boolean checkbox | `checkbox.blade.php` |
| `*Relation` | Registered relation field type such as `itemRelation` or `pageRelation` | Livewire component `noerd-relation-field` (`components/noerd-relation-field.blade.php`), markup from the theme's `relation-field.blade.php` |
| `image` | Image selection from Media library | `image.blade.php` |
| `file` | File upload | `file.blade.php` |
| `richText` | TipTap WYSIWYG editor | `rich-text.blade.php` |
| `translatableText` | Multi-language text field | `translatable-text.blade.php` |
| `translatableTextarea` | Multi-language textarea | `translatable-textarea.blade.php` |
| `translatableRichText` | Multi-language rich text editor | `translatable-rich-text.blade.php` |
| `belongsToMany` | Many-to-many tag selection with search | `belongs-to-many.blade.php` |
| `setupCollectionSelect` | Setup Collection selection | `setup-collection-select.blade.php` |
| `button` | Action button | `button.blade.php` |
| `icon` | Heroicon picker (opens the icon-picker modal) | `icon.blade.php` |
| `spacer` | Empty grid cell reserving its `colspan` (deliberate blank column) | `spacer.blade.php` |
| `block` | Container for nested fields | (in `components/detail/block.blade.php`) |

Element templates live in the theme folders (`resources/views/themes/default/` for the built-in
defaults; see [Themes](themes.md)). Modules may register additional types through the
`FieldTypeRegistry` (see [Custom Field Types](#custom-field-types)).

**Fallback behavior:** A `type` that is not registered (and does not end in `Relation`) renders as
the theme's `input` element with that value as the HTML `type` attribute — this is how `number`,
`date`, `time` and `datetime-local` work (`date` values are truncated to `YYYY-MM-DD`, `time`
values to `HH:MM`). Unregistered `*Relation` types throw instead (see
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
| `default` | mixed | - | Value the form starts with while the field is null (see [Default Values](#default-values)) |
| `required` | bool | `false` | Show required indicator on label |
| `readonly` | bool | `false` | Make field read-only |
| `live` | bool | `false` | Enable real-time updates (`wire:model.live.debounce`) |
| `placeholder` | string | - | Placeholder text (translation key); supported by text-like inputs (`text`, `email`, `phone`, `textarea`, …), selects and picklists |
| `tab` | int | `1` | Tab number for multi-tab forms |
| `showIf` | string/object | - | Condition to show the field |
| `showIfNot` | string/object | - | Condition to hide the field |
| `show` | bool | `true` | Statically show/hide the field |
| `viewExists` | string | - | View name — the field is skipped when that view is not registered (safe reference to an optional module) |
| `quickCreate` | bool | `false` | Include the field in the quick-create dialog even though it is not `required` (see [Page View](page-view.md#quick-create-lifecycle)) |
| `theme` | string | - | Per-field theme override (see [Themes](themes.md)) |
| `number` | int | - | Explicit row number in the `numbered` theme (defaults to auto-increment) |

`readonly` is also forced onto every field while the user's object permission denies saving — see
[Read-Only Rendering](detail-view.md#read-only-rendering-on-write-denied-objects).

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


### Default Values

A form must never display a value it does not hold. A `<select>` bound to a null property has no
matching `<option>`, so the browser shows the first one by pure HTML fallback — the user sees
"Created", the component holds `null`, and `null` is what gets saved. Defaults therefore belong to
the layout, not to a component's `mount()`.

Two rules apply, in this order:

1. **`default:`** — any field may declare its initial value. It is used while the bound value is
   `null` (a missing key counts as null); `''`, `0` and `false` are answers and are never replaced.
2. **First option of a select** — a `type: select` whose `options` are written in the YAML starts on
   its first option. This is what makes the displayed value real, so it is persisted on save.

```yaml
- name: detailData.invoice_status
  label: Status
  type: select
  options:
    - value: created      # <- the default: shown AND saved

      label: Created
    - value: paid
      label: Paid

- name: detailData.priority
  label: Priority
  type: select
  default: normal         # <- an explicit default wins over the first option

  options:
    - value: low
      label: Low
    - value: normal
      label: Normal

- name: detailData.size_class
  label: Size Class
  type: select
  placeholder: '—'        # <- empty is a valid answer: no implicit default

  options:
    - value: micro
      label: Micro
    - value: large
      label: Large
```

The implicit first-option rule deliberately does **not** apply to:

- selects using `optionsMethod:`, where the list is built from data at runtime and "the first row
  wins" would be arbitrary (a staff list, a person picker),
- selects declaring `placeholder:`, which renders a leading empty option and states that no
  selection is a valid state.

Defaults are applied on mount and re-applied before every render, so they also fill an **existing**
record whose column is `NULL` — such a record adopts the default the next time it is saved. Never
re-implement this per component (`$this->detailData['status'] ??= …` in a custom `mount()`); it is
generic in `NoerdDetail::applyLayoutDefaults()`.

A select whose stored value matches none of its options renders that value as its own leading
option, so a list that has drifted out of sync with the data never disguises one status as another.

---

## Basic Types

### text

Standard text input field. The HTML5 input types `number`, `date`, `time` and `datetime-local`
render through the same element (see the fallback behavior in the [Overview](#overview)).

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `type` | string | `text` | Input type: `text`, `number`, `date`, `time`, `datetime-local` |
| `readonly` | bool | `false` | Make field read-only |
| `live` | bool | `false` | Enable real-time updates |
| `required` | bool | `false` | Show required indicator |
| `placeholder` | string | - | Placeholder text (translation key) |

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

Amount input in the tenant's currency (Setup → System Settings), written the way the current user's
locale writes it: symbol, decimal and thousands separators follow `FormatHelper::locale()`, so a
US reader types `1,234.56` and a German reader `1.234,56` for the same field. The value is stored
as a plain decimal. See [Currency, Numbers & Dates](formatting.md).

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
```text
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
| `live` | bool | `false` | Enable real-time updates |
| `placeholder` | string | - | Placeholder text (translation key) |

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
| `optionsMethod` | string | - | Alternative to `options`: name of a public component method returning a `value => label` array |
| `live` | bool | `false` | Enable real-time updates |
| `required` | bool | `false` | Show required indicator |
| `placeholder` | string | - | Label of the leading empty option — declares that "nothing selected" is a valid answer |

*Either `options` or `optionsMethod` must be set.

**Defaults:** a select whose options are written in the YAML starts on its **first option**, and that
value is persisted on the first save. Declare `placeholder:` when empty is a legitimate answer, or
`default:` to start on a different option. See [Default Values](#default-values).

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
use Noerd\Helpers\TenantHelper;

public function getWarehouseOptions(): array
{
    return Warehouse::where('tenant_id', TenantHelper::getSelectedTenantId())
        ->pluck('name', 'id')
        ->toArray();
}
```

**Notes:**
- The method must return an associative array `[id => label, ...]` and MUST declare the `: array`
  return type — `NoerdDetail::resolvePicklistOptions()` only invokes a component method that
  declares it (the name is client-callable, so `void` actions such as `store()` are never invoked);
  otherwise the `PicklistRegistry` provider of that name is used, and `[]` when neither exists
- Useful when options depend on other model data or complex queries

---

## Relations

### Registered Relation Types

Relations must use explicit registered field types such as `itemRelation`, `authorRelation` or
`pageRelation`.

**YAML Example:**

```yaml
- name: detailData.item_id
  label: Item
  type: itemRelation
  colspan: 6
```

**Important:**
- There is no generic `type: relation` — it throws during rendering; every relation field uses its registered type
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
<?php

use Livewire\Component;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public $detailModel = Article::class;

    public ?string $detailPrimary = 'articleId';

    /** The tags picked in the belongsToMany field. */
    public array $tagIds = [];

    public function mount(): void
    {
        $this->initDetail();

        if ($this->modelId) {
            $this->tagIds = Article::find($this->modelId)?->tags()->pluck('tags.id')->all() ?? [];
        }
    }

    public function getTagOptions(): array
    {
        return Tag::orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * The trait's default store() persists the form; the many-to-many tags are
     * the one thing the YAML cannot express, so they are synced here.
     */
    public function store(): void
    {
        if (! $this->canSaveObject()) {
            return;
        }

        $this->validateFromLayout();

        $article = Article::updateOrCreate(
            ['id' => $this->modelId],
            $this->writableDetailData(Article::class),
        );
        $article->tags()->sync($this->tagIds);

        $this->finishStore($article);
    }
}; ?>
```

**Notes:**
- Features built-in search with keyboard navigation (arrow keys, Enter, Escape)
- Selected items appear as removable tags
- The component property must be an array of IDs (e.g., `$tagIds`)
- The options method is resolved with `method_exists` on the detail component (no registry fallback)
- Reference: `demo/views/demo-customer-detail.blade.php` (the shipped demo app)

---

## Media Types

### image

Image selection from the media library, or a plain upload when no media library is installed.
The element resolves everything through `Noerd\Contracts\MediaResolverContract` (see
[Extension Registries](extension-registries.md#mediaresolvercontract)): `isAvailable()` decides
between the picker button and a plain file input, `getPreviewUrl()` renders the thumbnail of a
numeric value, a string value is used as the preview URL directly.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | required | Field name (stores the media ID or a URL) |
| `label` | string | required | Translation key |
| `readonly` | bool | `false` | Hide the picker, upload and delete affordances |

**YAML Example:**

```yaml
- name: detailData.image_id
  label: Image
  type: image
  colspan: 6
```

**PHP Example (Livewire Component):** the element calls three methods on the hosting detail —
`openSelectMediaModal($field)`, `deleteImage($field)` and, for the plain-upload fallback, a
`wire:model.live` binding to `imageUploads.{field}`. The picker is the component returned by
`MediaResolverContract::pickerComponent()`, opened with `selectMode`/`selectContext`/`selectToken`;
it answers with the `mediaSelected` event. The token ties the answer to the field that opened the
picker:

```php
use Livewire\Attributes\On;
use Noerd\Contracts\MediaResolverContract;
use Noerd\Facades\Noerd;

public function openSelectMediaModal(string $fieldName): void
{
    $picker = app(MediaResolverContract::class)->pickerComponent();
    if (! $picker) {
        return;
    }

    $token = uniqid('media_', true);
    $this->detailData['__mediaToken'] = $token;
    Noerd::modal($picker, ['selectMode' => true, 'selectContext' => $fieldName, 'selectToken' => $token]);
}

#[On('mediaSelected')]
public function mediaSelected(int $mediaId, ?string $fieldName = 'image', ?string $token = null): void
{
    if (($this->detailData['__mediaToken'] ?? null) !== $token) {
        return;
    }

    $this->detailData[$fieldName ?? 'image'] = $mediaId;
    unset($this->detailData['__mediaToken']);
}

public function deleteImage(string $fieldName): void
{
    $this->detailData[$fieldName] = null;
}
```

`$fieldName` arrives without the `detailData.` prefix. Store the media ID (as above) or the
relative URL (`$resolver->getRelativeUrl($mediaId)`) — both render a preview. Reference:
`resources/views/components/setup-collection-detail.blade.php` (stores the URL and handles the
plain-upload fallback via `storeUploadedFile()`).

**Notes:**
- Shows a preview thumbnail when an image is selected
- Includes a delete button with confirmation dialog
- Without a media library the element renders a plain `<input type="file">` bound to
  `imageUploads.{field}` — declare `public array $imageUploads = [];` with `WithFileUploads` and
  store the upload via `MediaResolverContract::storeUploadedFile()`

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
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;
    use WithFileUploads;

    public $detailModel = Document::class;

    public ?string $detailPrimary = 'documentId';

    /** Bound by the `file` field (`name: document`) — not part of detailData. */
    public $document = null;

    public function store(): void
    {
        if (! $this->canSaveObject()) {
            return;
        }

        $this->validateFromLayout();
        $this->validate([
            'document' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        if ($this->document) {
            $this->detailData['path'] = $this->document->store('documents');
        }

        $document = Document::updateOrCreate(
            ['id' => $this->modelId],
            $this->writableDetailData(Document::class),
        );

        $this->finishStore($document);
    }
}; ?>
```

The upload property is a plain component property (the field `name` has no `detailData.` prefix);
only the stored path is written into `detailData` and therefore persisted by
`writableDetailData()` — the YAML must bind `detailData.path` (e.g. with `readonly: true` or
`show: false`) for the key to be writable.

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
The active key is the language chosen in the language switcher; the available languages come from the
tenant's configured content languages — see [Languages](languages.md).

Translatable inputs are rendered with a **light blue frame**, and their label carries a language icon
whose tooltip explains that the value belongs to the selected language. The marker is part of every
theme (`default`, `compact`, `numbered`) and is derived from the field type — nothing to configure. In
a list, a column declaring `translatable: true` gets a subtle blue cell background instead.

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
- All translatable fields edit the language returned by `SetupLanguage::selectedCode()` — the
  session choice made in the language switcher, otherwise the tenant's default language
- Language switching is handled globally by the application

---

## Special Types

### setupCollectionSelect

Dropdown for selecting entries from a Setup Collection.

**Options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `collectionKey` | string | required | The setup collection key |
| `displayField` | string | `name` | Field to display as option label |
| `valueField` | string | entry id | Field stored as the value instead of the entry id |
| `live` | bool | `false` | Enable real-time updates |
| `readonly` | bool | `false` | Disable the select |
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
- Locale fallback for translatable display fields: the selected language → the tenant's default
  language (`SetupLanguage::defaultCode()`) → the first available translation
  (`SetupCollectionHelper::selectOptions()`)
- See [Setup Collections](setup-collections.md)

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

Paths are relative to the noerd package root:

- Element templates of the built-in themes: `resources/views/themes/default/` (plus the elements
  `compact/` and `numbered/` restyle) — see [Themes](themes.md)
- Registered renderer targets (`noerd::components.forms.*`): `resources/views/components/forms/`
  — thin shims that delegate to the theme element
- Relation fields: `resources/views/components/noerd-relation-field.blade.php` /
  `noerd-polymorphic-relation-field.blade.php` (Livewire components)
- The rendering logic: `resources/views/components/detail/block.blade.php`

## Custom Field Types

The YAML `type:` is resolved through the `Noerd\Services\FieldTypeRegistry` singleton — nothing
is hardcoded. The core registers its types in `Noerd\Providers\NoerdServiceProvider`; a module
registers additional types in its own service provider's `boot()`, and any detail YAML may then
use them:

```php
use Noerd\Services\FieldTypeRegistry;
use Noerd\Support\FieldTypeDefinition;

app(FieldTypeRegistry::class)->register('rating', FieldTypeDefinition::include(
    'inventory::components.forms.rating',
    resolver: fn(array $field, mixed $component, mixed $detailData, mixed $modelId): array => ['field' => $field],
));
```

```yaml
- name: detailData.rating
  label: Rating
  type: rating
```

- `FieldTypeDefinition::include()` registers a Blade partial; `FieldTypeDefinition::livewire()` a
  dedicated Livewire field component (with an optional `keyResolver`). The optional `resolver`
  computes the props per render from `(array $field, $component, $detailData, $modelId)`
- Include-kind types are themeable: a theme folder may ship an element named after the basename
  of the target (`inventory::components.forms.rating` → `rating.blade.php`), see
  [Themes → Element Resolution](themes.md#element-resolution)
- Relation types are registered through the `RelationFieldRegistry` (which registers the matching
  field type itself) and rendered by `noerd-relation-field` — see
  [Relation Field Types](relation-field-types.md)
- Further registries: [Extension Registries](extension-registries.md)
