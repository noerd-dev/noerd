# Themes

A **theme** controls how detail forms (and the hand-written chrome around them, e.g. position
tables and buttons) are rendered. Noerd ships four built-in themes:

| Theme | Layout |
|-------|--------|
| `default` | Label on top of the input (also used when `theme` is absent or unknown) |
| `compact` | Label to the LEFT of the input with tighter vertical spacing |
| `numbered` | Numbered form rows in the style of official/tax forms: one field per full-width row (colspan is ignored), light gray row background, leading row number, right-aligned label, input on the right |
| `settings` | Internal (`hidden: true`): fields stacked vertically, full width — forced on [settings pages](settings-page.md), never selectable as a form theme |
| `display` | Internal (`hidden: true`, `textOnly: true`): every field renders as TEXT — label left, value right, no control. Used per field or per nested block for read-only rows inside a form, or for a whole read-only block; see [Display Theme](#display-theme-read-only-text) |

A theme is a **self-contained folder**: all element blade templates (input, select, textarea,
checkbox, button, relation field, …) plus a `theme.yml` metadata file. Creating a new theme means
copying a folder and editing its `theme.yml` — no PHP required.

## Selecting a Theme

### Per detail YAML

```yaml
title: Account
theme: compact
fields:
  - name: detailData.name
    label: Name
    type: text
    colspan: 6
  - name: detailData.notes
    label: Notes
    type: textarea
    colspan: 12
    theme: default   # per-field override
```

The theme is inherited by nested `type: block` fields; a single field (or nested block) may
override it with its own `theme:` key.

**Numbered theme:** rows are numbered automatically per block (nested blocks restart at 1);
`type: spacer` rows render as a blank line and consume NO number. A field may pin its number with
an explicit `number:` key — numbers may repeat, like on tax forms. The shared row chrome (gray row,
number cell, right-aligned label) lives in `<x-noerd::detail.numbered-row>`; the per-field
templates in `themes/numbered/` only provide the bare control.

### System-wide default (Setup → System Settings)

An admin preconfigures the theme for the whole system under **Setup → System Settings**
(`noerd::system-settings-page`, a [settings page](settings-page.md)): a *Theme* select plus an
*Enforce in Setup* checkbox. The setting is stored per tenant on `noerd_settings`
(`detail_theme`, `detail_theme_enforced`); the config array `noerd.theme` —
`config('noerd.theme.default')` (`NOERD_THEME`) and `config('noerd.theme.enforced')`
(`NOERD_THEME_ENFORCED`) — is the fallback for installations without a row.

| System setting | YAML declares `theme:` | Rendered theme |
|---|---|---|
| set, not enforced | no | the system theme |
| set, not enforced | yes | **the YAML theme** |
| set, **enforced** | yes / no | **the system theme — everywhere** |

With *Enforce in Setup* ticked the system theme also overrides every per-field and nested-block
`theme:` override, so the whole form renders in one theme. The one exception is a text-only theme
(`display`): it is a rendering mode, not a look, and stays wherever the YAML put it — enforcing
would otherwise turn read-only text rows back into inputs.

The select is built from the `ThemeRegistry`, so every discovered theme shows up automatically
(labelled with its `theme.yml` `label`); a stored theme whose folder is gone falls back to
`default`.

The setting is applied in `StaticConfigHelper::getComponentFields()` / `getPageFields()`, i.e. it
reaches every layout that comes out of a detail or page YAML (including YAML-driven modals). List
configs are never affected — the `compact` flag on lists is an unrelated concept
(see [Compact Mode](list-view.md#compact-mode-embedded-lists)).

## Theme Folders

The built-in themes live in the noerd package under `resources/views/themes/`:

```text
resources/views/themes/
  default/
    theme.yml
    input.blade.php
    input-select.blade.php
    input-textarea.blade.php
    input-currency.blade.php
    checkbox.blade.php
    button.blade.php
    picklist.blade.php
    setup-collection-select.blade.php
    belongs-to-many.blade.php
    color-hex.blade.php
    email.blade.php, phone.blade.php, file.blade.php, image.blade.php, icon.blade.php,
    rich-text.blade.php, translatable-*.blade.php … (every element)
    relation-field.blade.php
    polymorphic-relation-field.blade.php
  compact/
    theme.yml + the elements compact restyles
  numbered/
    theme.yml + the elements numbered restyles
  settings/
    theme.yml only (see settings-page.md)
  display/
    theme.yml + one-line elements including noerd::components.detail.display-value
```

## Display Theme (read-only text)

`display` renders a field's VALUE as plain text — the label left (fixed width, truncated with the
full text as tooltip), the value right — instead of a control. It is the way to show read-only
information inside a form: a row of facts above the inputs, a customer block in an ordering modal,
a preview. Three ways to use it, all through the ordinary `theme:` key:

```yaml
title: Order
fields:
  - type: block                     # 1. a read-only row above the inputs
    theme: display
    colspan: 12
    fields:
      - name: detailData.customer_name
        label: Customer
        colspan: 4
      - name: detailData.customer_phone
        label: Phone
        type: phone
        colspan: 4
      - name: detailData.total
        label: Total
        type: currency
        colspan: 4
  - name: detailData.created_at    # 2. a single read-only field between inputs
    label: Created
    type: datetime
    theme: display
    colspan: 6
  - name: detailData.note
    label: Note
    type: textarea
    colspan: 12
```

```yaml
theme: display                      # 3. the whole layout as text (e.g. a page YAML with fields)
fields:
  - name: detailData.email
    label: Email
    type: email
    hideIfEmpty: true
```

- The field `type` still decides HOW the value is written: `currency` through `CurrencyHelper`,
  `date`/`datetime`/`time` through `FormatHelper` in the reader's locale, `number` as a quantity,
  `checkbox` as Yes/No, `select`/`picklist`/`setupCollectionSelect` as the option's translated
  label, `phone` as a `tel:` link, `email` as a `mailto:` link, `textarea` with preserved line
  breaks, relation fields as their resolved title. Every other type (text, and the types the
  theme ships no element for — `image`, `richText`, `translatable*`, `file`, `button`) shows the
  raw value, or falls back to the default theme's read-only control.
- `hideIfEmpty: true` drops a field whose value is blank, so the grid closes up (a customer without
  a phone number shows no "Phone" row). The key is honoured ONLY in a text-only theme — an input
  never disappears because it is empty.
- `highlight` and `previousValue` (see [Detail View](detail-view.md#highlighted-fields)) work
  unchanged; the tint sits on the field wrapper.
- The theme is `hidden` (never offered in System Settings) and `textOnly` (see below). An enforced
  system theme leaves `display` alone.
- The row markup lives ONCE in `noerd::components.detail.display-value`; the theme's element
  templates are one-line includes handing it a `format`. A project theme that wants another look
  for read-only rows copies the folder, keeps `textOnly: true` and restyles the partial include.
- What the display theme is NOT: a permission. The value is not editable because there is no
  control, but a `store()` that mass-assigns `detailData` still writes whatever the payload holds.
  The security boundary stays the `store()`/`delete()` guards.

A theme folder does **not** have to ship every element: a missing element falls back to the
`default` theme's template (and finally to the renderer registered on the field type). The element
file name is the basename of the registered renderer view — `noerd::components.forms.input-currency`
→ `input-currency.blade.php` — so themes can also skin include-kind field types registered by other
modules.

**Fallback chrome for missing elements.** When the fallback template is rendered in a theme that
lays its rows out differently, the detail block (`resources/views/components/detail/block.blade.php`)
wraps it in the theme's row chrome so the form keeps its rhythm: in a theme with `numbersRows` the
default element renders inside `<x-noerd::detail.numbered-row>` (row number, right-aligned label),
in `compact` inside the label-left row (`w-36` truncated label, input in the remaining width). The
chrome renders the label; the fallback template contributes only the control (its own label and
help text are suppressed).
`spacer` and `checkbox` never receive this chrome. A theme therefore only needs to ship the
elements it really restyles.

### theme.yml

The theme name is the folder name. Every key is optional: a missing `label` shows the folder
name as headline in System Settings, every other missing key keeps the value of the `default`
theme (the constructor defaults of `Noerd\Support\ThemeDefinition`):

```yaml
label: Compact                          # display label in System Settings
hidden: false                           # true: internal theme, excluded from the System
                                        # Settings theme picker (e.g. the settings theme)
textOnly: false                         # true: the theme renders values as text, not controls
                                        # (display theme) — an enforced system theme leaves it
                                        # alone and `hideIfEmpty` is honoured
gridClasses: 'pt-1 gap-x-6 gap-y-1.5'   # spacing classes on the form grid wrapper (no bottom
                                        # padding — the x-noerd::page chrome owns the gap above
                                        # the footer)
fullWidthRows: false                    # true: ignore per-field colspan (one field per row)
numbersRows: false                      # true: automatic row numbering (numbered theme)
spacerClass: h-7                        # height of the `spacer` field type
controlClasses: 'block h-7 w-full …'    # bare control inside a position row (x-noerd::forms.control)
controlSize: sm                         # size hint for modal chrome (`sm` | `md`)
buttonClasses: 'h-7 px-2.5 py-1 text-xs'  # default size of x-noerd::button under this theme
                                        # (unset: buttons render like the default theme)
tableClasses: 'table w-full'            # the position <table>
headCellClasses: 'pr-2 pb-1 text-xs'    # position <th> padding
cellClasses: 'pr-2 pt-1 align-middle'   # position <td> padding
rowClasses: w-full                      # the position <tr> (e.g. gray banding)
sectionPadding: py-3                    # body padding of the position card
totalsPadding: pt-2                     # vertical rhythm of the totals footer
```

The minimal `theme.yml` of the built-in `settings` theme is three keys (`label`, `hidden`,
`fullWidthRows`) — everything else comes from the defaults.

## Creating a New Theme

### In a project

1. Run `php artisan noerd:make-theme mytheme` — it copies the `default` theme folder to
   `resources/views/themes/mytheme/` (or copy the folder yourself).
2. Edit `resources/views/themes/mytheme/theme.yml` (at least the `label`).
3. Adapt the element templates you want to change; delete the ones you keep unchanged
   (they fall back to the `default` theme).
4. The theme now appears in **Setup → System Settings** and can be used as `theme: mytheme` in any
   detail YAML.

The project root `resources/views/themes/` is registered automatically with the highest priority —
a project theme folder named like a built-in (e.g. `compact/`) **overrides** it. Even without an
own `theme.yml`, a single element file at `resources/views/themes/compact/input.blade.php`
overrides just that element of the built-in compact theme.

### In a module

Place the theme folder at `resources/views/themes/{name}/` inside the module and register the
root in the module's service provider `boot()`:

```php
use Noerd\Services\ThemeRegistry;

app(ThemeRegistry::class)->registerPath(__DIR__ . '/../../resources/views/themes');
```

`registerPath()` takes an optional priority (project root = 100, noerd built-ins = 0, default = 50).
For a theme name that exists in several roots, the highest-priority `theme.yml` wins the metadata;
element templates resolve through the same root order, element by element.

`php artisan noerd:make-theme mytheme --module=mymodule` scaffolds the folder inside a module and prints
the `registerPath()` snippet.

### Programmatic registration (escape hatch)

For dynamically built definitions a `ThemeDefinition` can still be registered directly — it wins
over a discovered `theme.yml` of the same name:

```php
use Noerd\Services\ThemeRegistry;
use Noerd\Support\ThemeDefinition;

app(ThemeRegistry::class)->register(new ThemeDefinition(
    name: 'table',
    gridClasses: 'py-2 gap-0',
    fullWidthRows: true,
));
```

Prefer the folder + `theme.yml` approach — it is the documented, copyable mechanism.

## Element Resolution

For a field of type X with registered renderer target T under active theme θ:

- **include-kind field types** (the normal case): `themes::{θ}.{element}` →
  `themes::default.{element}` → T. The `themes::` view namespace walks the registered roots
  (project → modules → noerd).
- **livewire-kind field types**: themes cannot hold Livewire components, so a `{name}-{θ}`
  sibling component wins when it exists (namespace-aware: `mod::name` resolves
  `mod::components.name-{θ}`). This suffix convention is only needed for third-party livewire
  field types.
- **Relation fields** are the exception that proves the rule: the two Livewire components
  (`noerd-relation-field`, `noerd-polymorphic-relation-field`) delegate their markup to the theme
  templates `relation-field.blade.php` / `polymorphic-relation-field.blade.php`, so a copied theme
  folder restyles them like any other element.
- Unknown theme names silently fall back to `default` — a YAML typo never breaks a detail page.

The grid wrapper emits `data-theme="{theme}"` for non-default themes. The resolution lives in
`Noerd\Support\ThemeElementResolver`; discovery and metadata in `Noerd\Services\ThemeRegistry`
(a singleton — themes are discovered lazily and cached per request).

Every element template supports a `readonly` state (`$field['readonly']`): the detail block forces
it onto all fields when the hosting component's object permission denies writing, so a custom theme
must honor it too (readonly attribute on inputs, `disabled` on selects/checkboxes, hidden picker
and upload affordances). See "Read-Only Rendering on Write-Denied Objects" in
[detail-view.md](detail-view.md).

## Buttons Follow the Theme

`<x-noerd::button>` without an explicit `size` follows the active theme: the rendering detail/page
component (and the detail block) set the current theme in `Noerd\Support\ThemeContext`, and the
button applies the theme's `buttonClasses` (e.g. `h-7 px-2.5 py-1 text-xs` under `compact`). This
covers footer bars (`x-noerd::delete-save-bar`), YAML detail actions (`x-noerd::detail-actions`)
and any other button in the form chrome — without touching the call sites.

- An explicit `size="sm|md|lg"` always wins over the theme.
- An explicit `theme="…"` prop pins the theme for a single button.
- Icon-only variants (`icon`, `control`) keep their fixed square size.
- A theme without `buttonClasses` renders buttons exactly like the default theme.
- `buttonClasses` may include a corner rounding (e.g. `rounded-none` in the numbered theme for
  square buttons) — the button then skips its default `rounded-sm`.
- The context lives exactly as long as the render: `renderingNoerdPage()` sets it, `renderedNoerdPage()`
  restores whatever was active before. Nesting therefore works (an embedded detail hands the context
  back to its hosting page, whose footer still renders in the page theme), while chrome rendered
  AFTER the page — the layout's app bar and quick-menu buttons — stays on the default theme instead
  of inheriting a form theme it never belonged to.

The `button` **field type** (`type: button` in a YAML) is a normal theme element
(`themes/{name}/button.blade.php`) and restyles per theme like any input.

## Position Tables

Hand-written position (line item) tables follow the theme through the `x-noerd::positions.*`
components and `x-noerd::forms.control` — their class strings come from the `theme.yml` values
(`tableClasses`, `rowClasses`, `controlClasses`, …), so a theme gets position styling for free.
The component reference and the Blade example live in
[Detail View → Position Tables](detail-view.md#position-tables); which columns a table shows is
configured per installation, see [Configurable Columns](detail-view.md#configurable-columns).

## Theme vs. Brand

Two orthogonal concepts:

- **Theme** (`noerd.theme.default`, `NOERD_THEME`): the FORM LAYOUT system documented here.
- **Brand** (`noerd.brand.active`, `NOERD_BRAND`): the color palette (sidebar, appbar, `brand-*`
  CSS variables), served by `Noerd\Services\BrandService` with the presets `default`, `sand`,
  `white` (see [Brand](brand.md)).

`NOERD_THEME` selects the form theme; `NOERD_BRAND` selects the color palette.
