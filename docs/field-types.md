# Field Types Reference

Every field type a YAML form may use. The same types and keys apply to detail YAMLs
([Detail View](detail-view.md)), page YAMLs with `fields:` ([Page View](page-view.md)) and settings
YAMLs ([Settings Pages](settings-page.md)) — all three render through the same detail block.

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
| `spacer` | Empty grid cell reserving its `colspan` (deliberate blank column) | `components/forms/spacer.blade.php` (no theme element) |
| `block` | Container for nested fields | (in `components/detail/block.blade.php`) |

Element templates live in the theme folders (`resources/views/themes/default/` for the built-in
defaults; see [Themes](themes.md)). Modules may register additional types through the
`FieldTypeRegistry` (see [Custom Field Types](#custom-field-types)).

**Fallback behavior:** A `type` that is not registered (and does not end in `Relation`) renders as
the theme's `input` element with that value as the HTML `type` attribute — this is how `number`,
`date`, `time` and `datetime-local` work (`date` values are truncated to `YYYY-MM-DD`, `time`
values to `HH:MM`). A bare `datetime` is understood by the `display` theme only — a control needs
`datetime-local`. Unregistered `*Relation` types throw instead (see
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
| `theme` | string | - | Per-field theme override (see [Themes](themes.md)); `display` renders the value as read-only text |
| `hideIfEmpty` | bool | `false` | Skip the field while its value is blank — honoured only in a text-only theme such as `display` (see [Display Theme](themes.md#display-theme-read-only-text)) |
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

## Type-Specific Keys

Keys a type reads on top of the [Common Options](#common-options). A type that is not listed has
none.

| Type | Key | Default | Description |
|------|-----|---------|-------------|
| `number` | `step` | - | The HTML `step` attribute (`0.01`, `any`) — without it a browser rejects decimals |
| `textarea` | `rows` | `8` | Number of visible text rows |
| `select` | `options` | - | List of `value`/`label` rows, or plain strings (value = label) |
| `select` | `optionsMethod` | - | Instead of `options`: public component method returning `value => label` |
| `picklist` | `picklistField` | required | Option provider: a component method or a `PicklistRegistry` name (the component method wins) |
| `belongsToMany` | `optionsMethod` | required | Component method returning the available options (`id => label`) |
| `file` | `multiple` | `false` | Allow several files |
| `file` | `accept` | - | The HTML `accept` attribute (`.pdf,.doc`, `image/*`) — a browser hint, NOT validation |
| `setupCollectionSelect` | `collectionKey` | required | Key of the setup collection |
| `setupCollectionSelect` | `displayField` | `name` | Entry field shown as the option label |
| `setupCollectionSelect` | `valueField` | entry id | Entry field stored instead of the entry id |
| `block` | `title`, `description` | - | Block heading and visible sub-heading (translation keys) |
| `block` | `fields` | required | The nested field definitions |
| `block` | `cols` | `12` | Grid columns of the nested grid (`colspan` defaults to `12` for a block) |

## Basic Types

### text

Standard text input. The HTML5 input types `number`, `date`, `time` and `datetime-local` render
through the same element (see the fallback behavior in the [Overview](#overview)).

An emptied `type: number` input is stored as `null`, never as `''`: the core normalises every
number field of the layout right before `store()` runs (custom overrides included), so a nullable
numeric column needs no empty-string handling in the component.

```yaml
- name: detailData.name
  label: Name
  type: text
  colspan: 6
- name: detailData.vat_rate
  label: VAT Rate
  type: number
  step: 0.01
  colspan: 3
- name: detailData.scheduled_at
  label: Scheduled At
  type: datetime-local
  colspan: 6
```

### phone

Phone input (`<input type="tel">`) with a trailing call button that opens `tel:{number}` via the
local phone app (e.g. FaceTime on macOS). The link always uses the CURRENT input value — edited
but unsaved values included — and strips all formatting except digits and a leading `+`
(`+49 (0)171 / 123-456` → `tel:+490171123456`). The button is hidden while the field is empty and
stays clickable on read-only fields (calling is a read action).

```yaml
- name: detailData.phone
  label: Phone
  type: phone
  colspan: 6
```

### email

Email input (`<input type="email">`) with a trailing button that opens `mailto:{address}` in the
local mail client. Same rules as `phone`: current input value, hidden while empty, clickable on
read-only fields.

```yaml
- name: detailData.email
  label: Email
  type: email
  colspan: 6
```

### currency

Amount input in the tenant's currency (Setup → System Settings), written the way the current user's
locale writes it: symbol, decimal and thousands separators follow `FormatHelper::locale()`, so a
US reader types `1,234.56` and a German reader `1.234,56` for the same field. The input parses the
notation in the browser and writes a plain decimal into the bound property — the component never
sees a locale string, so it needs no conversion of its own. See
[Currency, Numbers & Dates](formatting.md).

- **Nullable:** a `null` value renders an empty input and an emptied input writes `null` back —
  never `0`. An optional amount column stays `NULL`, and a form never shows a figure it does not
  hold. A typed `0` is a value and is kept.
- **Server changes are mirrored:** the input is `wire:ignore`d (a morph would drop the formatting),
  so the field watches the bound property itself. A value the component sets after mount — figures
  derived in `store()`, a proposal loaded into the form — appears in the input at once, unless the
  reader is typing in it at that moment.
- A percentage (VAT rate, discount) is NOT a currency: use `type: number` with `step:`.

```yaml
- name: detailData.amount
  label: Amount
  type: currency
  colspan: 6
```

### colorHex

A text input for manual HEX entry plus a native color picker, synchronized in both directions.
Stores a 7-character HEX string (`#efefef`).

```yaml
- name: detailData.color
  label: Color
  type: colorHex
  colspan: 4
```

### textarea

Multi-line text field.

```yaml
- name: detailData.notes
  label: Notes
  type: textarea
  rows: 4
  colspan: 12
```

### checkbox

Boolean checkbox; handles boolean values and the strings `"1"`/`"0"`. `readonly: true` disables it.
Set `live: true` when other fields depend on it (see [Conditional Display](#conditional-display)).

```yaml
- name: detailData.is_active
  label: Active
  type: checkbox
  colspan: 3
```

## Selection Types

### select

Dropdown with options written in the YAML (`options`), or provided by a component method
(`optionsMethod`) — one of the two must be set. `placeholder:` is the label of a leading empty
option and declares that "nothing selected" is a valid answer.

A select whose options are written in the YAML starts on its **first option**, and that value is
persisted on the first save; declare `placeholder:` or `default:` to change that — see
[Default Values](#default-values).

```yaml
- name: detailData.priority
  label: Priority
  type: select
  colspan: 6
  options:
    - value: low
      label: Low
    - value: high
      label: High
- name: detailData.status
  label: Status
  type: select
  colspan: 6
  options:
    - Draft          # simple format: value = label
    - Published
```

### picklist

Dropdown whose options come from a provider named by `picklistField`.

```yaml
- name: detailData.warehouse_id
  label: Warehouse
  type: picklist
  picklistField: getWarehouseOptions
  colspan: 6
```

```php
public function getWarehouseOptions(): array
{
    return Warehouse::orderBy('name')->pluck('name', 'id')->toArray();
}
```

The provider returns `[id => label, …]` and MUST be public, take no required arguments and declare
the `: array` return type — `NoerdDetail::resolvePicklistOptions()` only invokes a component method
that does (the name is client-callable, so `void` actions such as `store()` are never invoked).
Otherwise the `PicklistRegistry` provider of that name is used
([Extension Registries](extension-registries.md)), and `[]` when neither exists.

## Relations

### Registered Relation Types

A relation field uses an explicit, registered type such as `itemRelation`. There is no generic
`type: relation`, and an unregistered `*Relation` type throws during rendering. List component,
detail target and title resolver are defined once in the module's service provider — see
[Relation Field Types](relation-field-types.md).

```yaml
- name: detailData.item_id
  label: Item
  type: itemRelation
  colspan: 6
```

### belongsToMany

Tag-style selection for a many-to-many relationship: search with keyboard navigation (arrow keys,
Enter, Escape), selected items as removable tags (`readonly: true` hides the remove buttons and the
search).

```yaml
- name: tagIds
  label: Tags
  type: belongsToMany
  optionsMethod: getTagOptions
  colspan: 12
```

The field binds a plain component property holding an array of ids — not `detailData` — so the
detail loads and syncs it itself:

```php
public array $tagIds = [];

public function mount(): void
{
    $this->initDetail();
    $this->tagIds = Article::find($this->modelId)?->tags()->pluck('tags.id')->all() ?? [];
}

public function getTagOptions(): array
{
    return Tag::orderBy('name')->pluck('name', 'id')->all();
}

// In the custom store(), after the guarded save of $article:
$article->tags()->sync($this->tagIds);
```

The save itself follows [Custom Store / Delete Logic](detail-view.md#custom-store--delete-logic).
`optionsMethod` is resolved with `method_exists` on the detail component (no registry fallback).
Reference: `demo/views/demo-customer-detail.blade.php`.

## Media Types

### image

Image selection from the media library, or a plain upload when no media library is installed.
The element resolves everything through `Noerd\Contracts\MediaResolverContract` (see
[Extension Registries](extension-registries.md#mediaresolvercontract)): `isAvailable()` decides
between the picker button and a plain file input, `getPreviewUrl()` renders the thumbnail of a
numeric value, a string value is used as the preview URL directly. `readonly: true` hides the
picker, upload and delete affordances.

```yaml
- name: detailData.image_id
  label: Image
  type: image
  colspan: 6
```

The element calls `openSelectMediaModal($field)` and `deleteImage($field)` on the hosting detail.
The picker is the component returned by `MediaResolverContract::pickerComponent()`, opened with
`selectMode`/`selectContext`/`selectToken`; it answers with the `mediaSelected` event, and the
token ties the answer to the field that opened the picker:

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

- `$fieldName` arrives without the `detailData.` prefix.
- Store the media ID. A file's path changes when it is moved in the library, so resolve the URL when
  rendering: `getImageUrl($mediaId)` for visitors (a public website, an e-mail — a size-limited
  variant, never the oversized original), `getPreviewUrl()` for a backend tile,
  `getRelativeUrl()` for the original.
- Without a media library the element renders a plain `<input type="file">` bound to
  `imageUploads.{field}` — declare `public array $imageUploads = [];` with `WithFileUploads` and
  store the upload via `MediaResolverContract::storeUploadedFile()`. Reference:
  `resources/views/components/setup-collection-detail.blade.php`.

### file

Plain file input with Livewire upload (`live: true` uploads on selection). For drag and drop use
the dropzone instead ([Detail View](detail-view.md#further-ui-components)).

```yaml
- name: document
  label: Document
  type: file
  accept: '.pdf'
  colspan: 6
```

The field `name` has no `detailData.` prefix: the upload is a plain component property
(`WithFileUploads`), validated by the component — `accept` validates nothing:

```php
use WithFileUploads;

public $document = null;

public function store(): void
{
    $this->validate(['document' => 'nullable|file|mimes:pdf|max:10240']);

    if ($this->document) {
        $this->detailData['path'] = $this->document->store('documents');
    }

    // … continue with the guarded save, see detail-view.md → Custom Store / Delete Logic
}
```

Only the stored path is written into `detailData` and persisted by `writableDetailData()` — the YAML
must bind `detailData.path` (e.g. with `readonly: true` or `show: false`) for the key to be writable.

## Rich Text

### richText

TipTap WYSIWYG editor. `readonly: true` makes the editor non-editable.

```yaml
- name: detailData.content
  label: Content
  type: richText
  colspan: 12
```

- Content is stored as HTML; an empty editor stores an empty string (so `required: true` works).
- The editor hands its value to the component deferred — it is sent with the next request
  (e.g. `store()`), not on every keystroke; a value changed on the server after mount is pushed
  back into the editor.
- Render stored content with `<x-noerd::rich-text :content="…" />`, which passes it through
  `Noerd\Support\HtmlSanitizer` — never with `{!! !!}`.

## Translatable Fields

| Type | Control |
|------|---------|
| `translatableText` | Single-line input |
| `translatableTextarea` | Multi-line textarea |
| `translatableRichText` | TipTap WYSIWYG editor |

All three store a JSON object keyed by language code (`{"de": "Deutscher Titel", "en": "English Title"}`)
and edit the language returned by `SetupLanguage::selectedCode()` — the session choice made in the
language switcher, otherwise the tenant's default language. The available languages are the tenant's
content languages, see [Languages](languages.md).

Translatable inputs are rendered with a light blue frame and a language icon on the label whose
tooltip explains that the value belongs to the selected language — in every theme, derived from the
field type. In a list, a column declaring `translatable: true` gets a subtle blue cell background.

```yaml
- name: detailData.title
  label: Title
  type: translatableText
  colspan: 12
```

### translatableText

See [Translatable Fields](#translatable-fields).

### translatableTextarea

See [Translatable Fields](#translatable-fields).

### translatableRichText

See [Translatable Fields](#translatable-fields); editor behaviour as for [richText](#richtext).

## Special Types

### setupCollectionSelect

Dropdown over the entries of a [Setup Collection](setup-collections.md).

```yaml
- name: detailData.country_id
  label: Country
  type: setupCollectionSelect
  collectionKey: countries
  displayField: name
  colspan: 6
```

A translatable `displayField` falls back: selected language → the tenant's default language
(`SetupLanguage::defaultCode()`) → the first available translation
(`SetupCollectionHelper::selectOptions()`).

### icon

Heroicon picker: the field shows the current icon with its name and opens the searchable
`noerd::icon-picker` modal on click. The selected icon name is stored as a string.

```yaml
- name: detailData.icon
  label: Icon
  type: icon
  colspan: 6
```

### spacer

Renders nothing but still occupies its `colspan`, reserving an empty grid cell — use it to keep a
deliberate blank column so the next field starts on a new row. Needs no `name`; only `type: spacer`
and `colspan` are relevant. The blank height follows the active theme (`spacerClass` in
`theme.yml`).

```yaml
- name: detailData.name
  label: Name
  type: text
  colspan: 6
- type: spacer
  colspan: 6
```

### button

Button that calls the component method named by `name`; `label` is the button text. Rendered
disabled on a read-only form.

```yaml
- name: generateCode
  label: Generate Code
  type: button
  colspan: 3
```

```php
public function generateCode(): void
{
    $this->detailData['code'] = strtoupper(Str::random(8));
}
```

### block

Container grouping nested fields under an optional title; blocks may be nested. A block may carry
its own `theme:` (see [Themes](themes.md)).

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
```

## Conditional Display

`showIf` shows a field while a condition holds, `showIfNot` hides it while it holds. Both accept a
property path (truthy check) or an object with `field` and `value` (equality check). The condition
is an Alpine `x-show`, so it follows the form state in the browser; set `live: true` on the
controlling field when the server has to see the change as well.

```yaml
- name: detailData.is_business
  label: Business
  type: checkbox
  live: true
- name: detailData.company_name
  label: Company
  type: text
  showIf: detailData.is_business
- name: detailData.first_name
  label: First Name
  type: text
  showIfNot: detailData.is_business
- name: detailData.edit_notes
  label: Notes
  type: textarea
  showIfNot:
    field: detailData.status
    value: archived
```

Tabs and detail actions accept the same two keys — see
[Detail View](detail-view.md#tab-properties).

## Component Locations

Element templates live in the theme folders, the rendering logic in
`resources/views/components/detail/block.blade.php` — see
[Themes → Element Resolution](themes.md#element-resolution).

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
