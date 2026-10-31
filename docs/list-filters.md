# List Filters

Lists can be filtered using **header filters** — dropdowns in the filter row that narrow the rows
by a value (a status, a year, a language). They are declared in the list YAML (`filters:`) or on
the component, and `listQuery()` applies them — see [Header Filters](#header-filters) below.

In addition, every list gets Excel-style **column filters** automatically — see [Column Filters](#column-filters-excel-style) below.

## Column Filters (Excel-style)

Every filterable column shows a funnel icon in its header (revealed on header hover, always visible while active). Clicking it opens a popover where the user types a filter expression; the filter is applied on Enter or via the Apply button. This is a single generic feature in `NoerdList` + the list views — no per-list configuration or per-module duplication.

A list in [grid mode](list-view.md#grid-mode-card-layout) has no table header to hang the funnels on and renders the same popovers as labeled buttons in a **control bar above the cards** instead (which also carries the grid sort dropdown).

### Operator syntax

A filter expression may start with a comparison operator: `>=`, `<=`, `>`, `<`, `=`, `!=` (`<>` is accepted as `!=`). Without an operator the default depends on the column type:

| Column type | Popover UI | With operator | Without operator |
|-------------|-----------|---------------|------------------|
| `text` (default) | Text input | `=rot` exact, `!=rot` not equal, `>m` string comparison | `rot` → `LIKE %rot%` (wildcards in the value are escaped) |
| `number`, `currency` | Text input | `>0`, `<=10`, `!=5` (comma decimals accepted: `>=2,5`) | Exact match |
| `date`, `datetime` | Text input | `>=2026-01-01` (also German format `17.07.2026`) | That exact day (`whereDate =`) |
| `bool` | All / Yes / No buttons | — | — |
| `badge`/`select` (with `options`) | All, (Empty), (Not empty) + one button per option | — | Exact match on the option value |
| every type except `bool` | Empty / Not empty buttons | `=` alone → empty, `!=` / `<>` alone → not empty | — |

Invalid input (non-numeric value on a number column, unparseable date, a comparison operator such as `>=` without value) is silently ignored — the filter is a no-op, never an error.

### Empty / Not empty

Like Excel's "(Blanks)", every column with a filter popover — text, number, currency, date,
datetime, badge/select, JSON paths and relation paths — can be narrowed to empty or set values. The
popover of a text input column shows two buttons **Empty** and **Not empty** above the input; a
picklist/badge popover lists **(Empty)** and **(Not empty)** right after "All". Boolean columns keep
their All / Yes / No buttons.

There is no extra state: the buttons write a bare operator as the expression, so the URL
(`?cf[field]==`) and the session stay the same format as every other filter.

| Expression | Meaning |
|------------|---------|
| `=` | Empty |
| `!=` or `<>` | Not empty |

- **What counts as empty:** `NULL` — and additionally the empty string `''` for string-like targets:
  `text`, `badge` and `select` columns and every JSON path. Real `date`, `datetime`, `number`,
  `currency` and `bool` columns are checked for `NULL` only (comparing a MySQL `DATE` column with `''`
  is not portable). On a JSON path a missing key and a JSON `null` both count as empty.
- **Relation paths:** "Empty" also matches rows WITHOUT a related record
  (`whereDoesntHave(relation, value not empty)`); "Not empty" requires a related record with a set
  value (`whereHas(relation, value not empty)`).
- **Chips:** the header chip shows the translated "Empty" / "Not empty" instead of `=` / `!=`, and the
  popover input stays blank while such a filter is active.

### Which columns are filterable

A column is filterable when it is declared in the list YAML `columns`, is not `action`, and resolves
to one of three things:

1. a **real column** on the model's table (the same rule as sorting)
2. a **path into a JSON-cast column** (e.g. `custom_attributes.sap_number`): the segment before the
   first dot must be a real table column that the model casts to an array/object — the filter then
   applies through the JSON arrow operator (`custom_attributes->sap_number`). A JSON path has no DB
   schema type, so its filter type comes from the list column's explicit `type:`/`options` or the
   paired detail picklist, with `text` as the fallback
3. a **path through Eloquent relations** (e.g. `defaultDeliveryAddress.locality`): every segment
   before the last dot must be a public no-argument method returning a relation (nested paths work,
   exactly like `whereHas('a.b')`), and the last segment a real column on the related table. The
   filter applies as a `whereHas()` subquery on that column — a join would collide with the base
   table's column names. The filter type comes from the list column's explicit `type:`, else from
   the **related** table's schema, with `text` as the fallback

JSON paths take precedence over relation paths. A path that resolves to neither (a YAML typo, a
method that is not a relation, a missing column) is silently dropped: no funnel, no filter, never an
error. Dotted fields stay **unsortable and unsearchable** in every case — only filtering understands
them. Lists that build a fully custom query (never calling `listQuery()`) show no funnels and apply
no column filters.

### Behavior

- Multiple column filters combine with AND — and stack with the search field and the header filters (see [Header Filters](#header-filters))
- Setting or clearing a filter resets pagination to page 1
- Filters persist per component in the session (`listColumnFilters.{component}`), like sorting
- Filters are mirrored into the URL as `?cf[column]=expression` — a shared link reproduces the exact
  view. On mount the URL wins over the session state. The active list view (`?view=`, including
  `default`) and the filters are written into the URL on initial page load by the url-sync script in
  the list Blade (Livewire `#[Url]` bindings only write on updates); afterwards Livewire keeps them in
  sync. Only the page-level list writes the URL — modal, compact and picker lists never do
- Compact/embedded and minimal lists render no funnels and never apply column filters (a session filter must not invisibly hide rows of an embedded widget)
- The header "Clear all filters" button (`clearAllListFilters()`) clears the column filters too; each popover also offers a per-column clear
- CSV export respects active column filters when the export query builds on `listQuery()`

### Header chips

Every active column filter renders as a chip next to the list title, so the user always sees WHICH
field is filtered by WHICH value — e.g. `PLZ: 95028 ✕`. Each chip carries its own ✕ that clears
exactly that filter (`clearColumnFilter()`). The global "Clear all filters" ✕ only appears when more
than one chip is active or header `listFilters` are set on top — with a single chip its own ✕ suffices.

The chip resolves both parts for display via `NoerdList::activeColumnFilterChips()`:

- **Label**: the column's translated `label` from the list YAML (falls back to the field name)
- **Value**: bool columns show Yes/No instead of `1`/`0`; picklist/badge columns show the translated
  option label (from the list column's own `options` or the paired detail YAML, same resolution as
  the badge cells); everything else shows the expression as typed (`>=10`, `=Rot`, …)

The type resolution (explicit YAML type → DB schema type) mirrors `applyColumnFilters()`, so a chip
always describes the filter the query actually applies.

### Header layout (title row + filter row)

The generic list header is **two rows at every viewport width** — and neither of them ever wraps:

- **The title row** holds the list title with its record count (and the view switcher when the list
  ships several YAML views) on the left, and every button on the right: the `style: secondary`
  actions, CSV export and the primary "New …" actions. `x-noerd::modal-title` draws the grey
  separator below it.
- **The filter row** renders only when something belongs in it — a search field, header filters
  or active-filter chips, or registry list actions (e.g. a module's layout and object
  managers). Pagination alone never opens it. It has three zones:
  1. the search field, left, at a fixed width (`w-40 sm:w-56`), OUTSIDE the scrolling strip;
  2. the filters — YAML picklist/date filters, one chip per active column filter, the clear-all
     button — inside a horizontally **scrolling strip** (`flex-1 min-w-0 overflow-x-scroll`);
  3. on the right, the registry list actions followed by the pagination summary (`1-50 of 150`)
     with icon-only previous/next buttons — the same partial the footer renders
     (`noerd::components.table.list-pagination-nav`).

The strip is built like the quick-menu: every control is `shrink-0`, so an overflowing filter set
scrolls rather than squeezing, wrapping or collapsing, and the scrollbar track is permanently
reserved so nothing shifts when scrolling becomes possible. Popovers are safe inside it (the
picklist filter is a native `<select>`, the date dropdown anchors its panels with `x-anchor.fixed`).

There is no drawer, no funnel button and no breakpoint-specific stacking at any width. What a list
header renders is resolved ONCE by `NoerdList::headerControls()`; the header rows and
`x-noerd::modal-title` read that — never re-derive "does this list have a search field / a
secondary action" from `$listSettings` at a call site. It is a single generic feature of
`list-header.blade.php`: never rebuild a list header per module and never position header controls
with JavaScript.

### Architecture

- Expression parsing + query application: `Noerd\Services\ColumnFilterParser` (fixed operator set, values only ever bound as parameters — user input never reaches SQL text)
- State + whitelist: `NoerdList::$listColumnFilters`, `setColumnFilter()`, `clearColumnFilter()`, `filterableColumnFields()`, `applyColumnFilters()` (hooked inside `listQuery()`)
- Header UI: `noerd::components.table.column-filter`, included from `table-sort.blade.php`; active-filter chips: `NoerdList::activeColumnFilterChips()`, rendered in `noerd::components.table.list-header`
- Header rows: `noerd::components.table.list-header` (title row + filter row) including
  `list-controls-secondary` and `list-controls-primary` (title row), `list-search`, `list-filters`,
  `list-controls-registry` and `list-pagination-nav` (filter row); `NoerdList::headerControls()`
  resolves which of them exist. A list host with its own custom header slot gets the one-row
  `list-controls` injected by `x-noerd::modal-title` instead

## Header Filters

A header filter is a dropdown in the filter row. Its selection is stored in `$listFilters` (and in
the session, see [Session Persistence](#session-persistence)) and `listQuery()` applies it to the
query — a list never applies its header filters itself, and never overrides `listData()` just to
have them. A filter is declared in one of two places, and the two combine:

1. **In the list YAML** (`filters:`) — the normal case: a filter on a column of the table
2. **On the component** — a method `get{Name}ListFilter()` returning a `Noerd\Support\ListFilter`
   (or its array form), for a filter whose options or query need PHP

### Filters in the list YAML

```yaml
title: Invoices
filters:
  - label: Status
    column: status
    options:
      - value: open
        label: Open
      - value: paid
        label: Paid
    default: open
  - label: Year
    column: invoice_date
    type: years
  - label: Customer
    column: party_id
    type: model
    model: Noerd\Party\Models\Party
    labelColumn: name
  - label: Show from
    column: invoice_date
    type: dateFrom
```

| Key | Description |
|-----|-------------|
| `label` | The dropdown label (a translation key); also its "all" entry |
| `column` | The column the selection constrains |
| `type` | `picklist` (default): equality on `column`. `years`: the distinct years of the date column, newest first — a selection narrows to that calendar year (portable: no `YEAR()` in the list's SQL). `model`: one option per row of `model`, the id as value and `labelColumn` (default `name`) as label; the model's global scopes apply, so the options are the tenant's rows. `dateFrom` / `dateUntil`: a dropdown of relative periods (today, this week, this month, last month, this quarter, last quarter, this year) plus a date picker, compared with `>=` / `<=` |
| `options` | `picklist` only: the options, as in a detail select (`value` + `label` entries, labels are translation keys) or as a `value: label` map |
| `optionsMethod` | `picklist` only: the name of a [`PicklistRegistry`](extension-registries.md#picklistregistry) provider that builds the options |
| `default` | The selection assumed while the reader has not chosen anything (see [Defaults](#defaults)) |

Keep both synced copies of the list YAML in sync (`app-configs/` and the module's `app-configs/`).

### Filters on the component

`NoerdList::tableFilters()` collects the YAML filters and every `get*ListFilter()` method of the
component. A method returns a `ListFilter`:

```php
use Noerd\Support\ListFilter;

protected function getCategoryListFilter(): ListFilter
{
    return ListFilter::model(__('Category'), 'category_id', Category::class, 'name');
}

protected function getYearListFilter(): ListFilter
{
    return ListFilter::years(__('Year'), 'invoice_date');
}
```

| Constructor | Filter |
|-------------|--------|
| `ListFilter::options($label, $column, $options)` | A fixed list — `value => label` pairs or detail-select entries; a closure builds them at render time and receives the list's model class |
| `ListFilter::model($label, $column, Model::class, $labelColumn = 'name', ?Closure $scope = null)` | One option per model row; `$scope` narrows or orders the query |
| `ListFilter::years($label, $column)` | The distinct years of a date column |
| `ListFilter::dateFrom($label, $column)` / `dateUntil(...)` | The relative-period dropdown with a date picker |
| `->default($value)` | The initial selection |
| `->apply(fn (Builder $query, string $value) => …)` | The constraint of a virtual column (see below) |
| `->manual()` | The component applies the filter in its own query |

The array form of earlier versions (`['label' => …, 'column' => …, 'type' => 'Picklist', 'options' => […]]`,
also `ShowFrom` / `ShowUntil`) is still accepted — `ListFilter::fromArray()` turns it into a filter.

Extract a shared filter into a trait (`app-modules/{module}/src/Traits/{Name}FilterTrait.php`,
holding nothing but the `get{Name}ListFilter()` method) so several lists can use it. Override
`tableFilters()` only to show filters conditionally:

```php
#[Computed]
public function tableFilters(): array
{
    return Category::query()->count() < 2 ? [] : [$this->getCategoryListFilter()];
}
```

### Virtual columns

A filter does not have to sit on a column of the table. A `ListFilter` with an `apply()` closure
puts its own constraint on the query — a scope, a relation, an aggregate:

```php
protected function getReceiptListFilter(): ListFilter
{
    return ListFilter::options(__('Receipt'), 'receipt_status', [
        'missing' => __('Without receipt'),
        'present' => __('With receipt'),
    ])->apply(fn (Builder $query, string $value) => $query->hasReceipt($value === 'present'));
}
```

A list that reads the value in its own query (`$this->listFilters['tenant_id']` inside a
`whereHas()` the component builds anyway) marks the filter `->manual()`. Without a closure, a
filter on a column the table does not have is never written into the SQL — the dropdown renders,
the query stays untouched.

### Defaults

`default` (YAML) / `->default()` is the selection assumed while the filter has no stored value:
`loadListFilters()` writes it into `$listFilters` on mount, so the list opens narrowed (open
invoices, future bookings, active vouchers). The first change the reader makes is persisted like any
other selection; "Clear all filters" stores the empty selection, so "all" survives a reload. A
default is configuration — never set `$this->listFilters[...]` in `mount()` or `rendering()`.

## Session Persistence

`storeActiveListFilters()` is called by the filter UI on every change (`wire:change` on the
dropdown). The trait implementation writes the selection to the session (`session(['listFilters' => …])`)
and resets pagination to page 1, so the selection survives a reload. The bucket is deliberately
shared across lists: a detail page may seed it via `NoerdPage::setPreselect()` so a related list
opens pre-filtered — only whitelisted columns ever apply (see [Security](#security)).

Override it to add side effects — always keep the session write:

```php
public function storeActiveListFilters(): void
{
    session(['listFilters' => $this->listFilters]);
    $this->resetPage();

    if (! empty($this->listFilters['language'])) {
        session([SetupLanguage::SESSION_KEY => $this->listFilters['language']]);
    }
}
```

## Security

Only a declared filter is ever applied: `applyListFilters()` resolves each `$listFilters` key
against the declared filters (`listFilterDefinitions()`), so a client cannot constrain arbitrary
columns by writing into the array. The value is bound as a query parameter, never interpolated.

To allow additional keys (e.g. a filter seeded via `setPreselect()` that no dropdown declares),
override the whitelist on the component — such a key constrains the column of that name by
equality, provided the table has it:

```php
protected function getAllowedListFilterColumns(): array
{
    return [...parent::getAllowedListFilterColumns(), 'category_id'];
}
```

`applyListFilters()` runs once per builder: a component that still calls it on the query
`listQuery()` returned adds nothing a second time.
