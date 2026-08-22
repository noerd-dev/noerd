# Themes

A **theme** controls how detail forms (and the hand-written chrome around them, e.g. position
tables and buttons) are rendered. Noerd ships four built-in themes:

| Theme | Layout |
|-------|--------|
| `default` | Label on top of the input (also used when `theme` is absent or unknown) |
| `compact` | Label to the LEFT of the input with tighter vertical spacing |
| `numbered` | Numbered form rows in the style of official/tax forms: one field per full-width row (colspan is ignored), light gray row background, leading row number, right-aligned label, input on the right |
| `settings` | Internal (`hidden: true`): fields stacked vertically, full width — forced on [settings pages](settings-page.md), never selectable as a form theme |

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

An admin preconfigures the theme for the whole system under **Setup → Administration → System
Settings**: a *Theme* select plus an *Enforce in Setup* checkbox. The setting is stored per tenant
on `noerd_settings` (`detail_theme`, `detail_theme_enforced`); `config('noerd.theme')`
(`NOERD_THEME` / `NOERD_THEME_ENFORCED`) is the fallback for installations without a row.

| System setting | YAML declares `theme:` | Rendered theme |
|---|---|---|
| set, not enforced | no | the system theme |
| set, not enforced | yes | **the YAML theme** |
| set, **enforced** | yes / no | **the system theme — everywhere** |

With *Enforce in Setup* ticked the system theme also overrides every per-field and nested-block
`theme:` override, so the whole form renders in one theme.

The select is built from the `ThemeRegistry`, so every discovered theme shows up automatically
(labelled with its `theme.yml` `label`); a stored theme whose folder is gone falls back to
`default`.

The setting is applied in `StaticConfigHelper::getComponentFields()` / `getPageFields()`, i.e. it
reaches every layout that comes out of a detail or page YAML (including YAML-driven modals). List
configs are never affected — the `compact` flag on lists is an unrelated concept
(see [Compact Mode](list-view.md#compact-mode-embedded-lists)).

## Theme Folders

The built-in themes live in the noerd module:

```
app-modules/noerd/resources/views/themes/
  default/
    theme.yml
    input.blade.php
    input-select.blade.php
    input-textarea.blade.php
    input-currency.blade.php
    input-relation.blade.php
    checkbox.blade.php
    button.blade.php
    picklist.blade.php
    setup-collection-select.blade.php
    belongs-to-many.blade.php
    color-hex.blade.php
    file.blade.php … (every element)
    relation-field.blade.php
    polymorphic-relation-field.blade.php
  compact/
    theme.yml + the elements compact restyles
  numbered/
    theme.yml + the elements numbered restyles
```

A theme folder does **not** have to ship every element: a missing element falls back to the
`default` theme's template (and finally to the renderer registered on the field type). The element
file name is the basename of the registered renderer view — `noerd::components.forms.input-currency`
→ `input-currency.blade.php` — so themes can also skin include-kind field types registered by other
modules.

### theme.yml

Every key is optional; missing keys keep the `default` theme's values. The theme name is the
folder name.

```yaml
label: Compact                          # display label in System Settings
hidden: false                           # true: internal theme, excluded from the System
                                        # Settings theme picker (e.g. the settings theme)
gridClasses: 'pt-1 gap-x-6 gap-y-1.5'   # spacing classes on the form grid wrapper (no bottom
                                        # padding — the x-noerd::page chrome owns the gap above
                                        # the footer)
fullWidthRows: false                    # true: ignore per-field colspan (one field per row)
numbersRows: false                      # true: automatic row numbering (numbered theme)
spacerClass: h-7                        # height of the `spacer` field type
controlClasses: 'block h-7 w-full …'    # bare control inside a position row (x-noerd::forms.control)
controlSize: sm                         # size hint for modal chrome
buttonClasses: 'h-7 px-2.5 py-1 text-xs'  # default size of x-noerd::button under this theme
tableClasses: 'table w-full'            # the position <table>
headCellClasses: 'pr-2 pb-1 text-xs'    # position <th> padding
cellClasses: 'pr-2 pt-1 align-middle'   # position <td> padding
rowClasses: w-full                      # the position <tr> (e.g. gray banding)
sectionPadding: py-3                    # body padding of the position card
totalsPadding: pt-2                     # vertical rhythm of the totals footer
```

## Creating a New Theme

### In a project

1. Run `php artisan noerd:theme mytheme` — it copies the `default` theme folder to
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

Place the theme folder at `app-modules/{module}/resources/views/themes/{name}/` and register the
root in the module's service provider `boot()`:

```php
use Noerd\Services\ThemeRegistry;

app(ThemeRegistry::class)->registerPath(__DIR__ . '/../../resources/views/themes');
```

`registerPath()` takes an optional priority (project root = 100, noerd built-ins = 0, default = 50).
For a theme name that exists in several roots, the highest-priority `theme.yml` wins the metadata;
element templates resolve through the same root order, element by element.

`php artisan noerd:theme mytheme --module=mymodule` scaffolds the folder inside a module and prints
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

Read the theme once from the trait and hand it down:

```blade
@php $positionsTheme = $this->detailTheme(); @endphp

<x-noerd::positions.section :theme="$positionsTheme" title="Positions">
    <x-noerd::positions.table
        :theme="$positionsTheme"
        :columns="[['label' => 'Quantity', 'class' => 'w-32'], 'Name', '']"
    >
        @foreach($model->positions as $position)
            <livewire:module::position
                :key="$position->id"
                :$position
                :theme="$positionsTheme"
                :number="$loop->iteration"
            />
        @endforeach
    </x-noerd::positions.table>

    <x-noerd::positions.totals
        :theme="$positionsTheme"
        :net="$model->total_net"
        :gross="$model->total_gross"
        :taxes="$model->taxes"
    />
</x-noerd::positions.section>
```

See [Detail View](detail-view.md#position-tables) for the full positions component reference.

## Theme vs. Brand

Two orthogonal concepts:

- **Theme** (`noerd.theme`, `NOERD_THEME`): the FORM LAYOUT system documented here.
- **Brand** (`noerd.brand`, `NOERD_BRAND`): the color palette (sidebar, appbar, `brand-*` CSS
  variables), served by `Noerd\Services\BrandService` with the presets `default`, `sand`, `white`.

`NOERD_THEME` selects the form theme; `NOERD_BRAND` selects the color palette.
