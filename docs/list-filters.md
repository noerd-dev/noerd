# List Filters

Lists can be filtered using dropdown filters (`tableFilters`). Dropdown filters allow users to narrow results by selecting a value (e.g. year, language).

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
| `badge`/`select` (with `options`) | All + one button per option | — | Exact match on the option value |

Invalid input (non-numeric value on a number column, unparseable date, operator without value) is silently ignored — the filter is a no-op, never an error.

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

- Multiple column filters combine with AND — and stack with the search field and the header `listFilters`
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

### Responsive header (the filter drawer)

The generic list header is **one non-wrapping row at every viewport width** — it never breaks onto a
second line, not even on a phone. Only two things are guaranteed a place on that row: the list title
(with its record count and, where present, the view switcher) and the primary YAML actions. Everything
else is *collapsible*:

- the header filters and the active-filter chips
- the search field
- CSV export and every YAML action marked `style: secondary`

Below `xl` the collapsible controls move into a drawer behind a funnel button next to the title;
from `xl` on they sit inline on the header row.

The switch is **pure CSS**. The controls are rendered exactly once: one container is the inline row
from `xl` on (`xl:flex-row`) and the drawer panel below it (`max-xl:fixed max-xl:inset-y-0
max-xl:right-0 max-xl:flex-col`). There is no second copy, so nothing has to keep two sets of
`wire:key`s, Alpine states or keyboard shortcuts apart — the only JavaScript involved is
`x-data="{ drawer: false }"`.

- The order differs per layout and is expressed with flex `order-*`: in the drawer the search comes
  first, then the filters, then the buttons; on the header row the filters lead and the search plus
  buttons are pushed right.
- The individual controls go full-width in the drawer through `max-xl:w-full`. `x-noerd::button`
  centres itself with `my-auto` for the header ROW, so a stacked button cancels it with
  `max-xl:!my-0` — in a flex COLUMN that auto margin would absorb the free vertical space.
- The funnel carries a count badge of everything the drawer is hiding: active header filters, active
  column-filter chips, and a non-empty search.
- A header with nothing collapsible renders no funnel button and no drawer.
- From `xl` on, a filter row that still outgrows its space scrolls horizontally
  (`xl:overflow-x-auto`) rather than being hidden.

What each list header actually renders is resolved ONCE by `NoerdList::headerControls()` (with
`hasCollapsibleControls()` / `hasHeaderControls()` on top). The header, the drawer and
`x-noerd::modal-title` all read that — never re-derive "does this list have a search field / a
secondary action" from `$listSettings` at a call site.

This is a single generic feature of `list-header.blade.php` — never rebuild a responsive header per
module, and never add breakpoint-specific stacking to a list header.

### Architecture

- Expression parsing + query application: `Noerd\Services\ColumnFilterParser` (fixed operator set, values only ever bound as parameters — user input never reaches SQL text)
- State + whitelist: `NoerdList::$listColumnFilters`, `setColumnFilter()`, `clearColumnFilter()`, `filterableColumnFields()`, `applyColumnFilters()` (hooked inside `listQuery()`)
- Header UI: `noerd::components.table.column-filter`, included from `table-sort.blade.php`; active-filter chips: `NoerdList::activeColumnFilterChips()`, rendered in `noerd::components.table.list-header`
- Responsive header: `noerd::components.table.list-header` (row + drawer) including `list-filters`,
  `list-search` and `list-controls-secondary` (the collapsing half) plus `list-controls-primary`
  (always visible); `NoerdList::headerControls()` resolves which of them exist. A list host with its
  own custom header slot gets the non-collapsing `list-controls` injected by `x-noerd::modal-title`
  instead
- Tests: `app-modules/noerd/tests/Unit/ColumnFilterParserTest.php`, `app-modules/noerd/tests/Feature/NoerdListColumnFilterTest.php`, `app-modules/noerd/tests/Components/ListHeaderCollapseTest.php`

## How Filters Work

1. A component defines filter options via `tableFilters()` (computed property)
2. The `list-header.blade.php` template renders the filter UI (dropdowns)
3. When a user selects a filter value, it is stored in `$listFilters`
4. The query is filtered via `applyListFilters($query)` or custom logic in `with()`

## Defining a Filter

Each filter is defined by a method following the naming convention `get{Name}ListFilter()`. The method must return an array with these keys:

| Key | Description |
|-----|-------------|
| `label` | Display label for the dropdown |
| `column` | Database column to filter on |
| `type` | Filter type (currently `Picklist`) |
| `options` | Associative array of `value => label` pairs |

Example:

```php
protected function getYearListFilter(): array
{
    $filter['label'] = 'Jahr';
    $filter['column'] = 'created_at';
    $filter['type'] = 'Picklist';
    $filter['options'] = [];

    $years = Order::selectRaw('YEAR(created_at) as year')
        ->where('tenant_id', auth()->user()->selected_tenant_id)
        ->distinct()
        ->orderBy('year', 'desc')
        ->pluck('year')
        ->toArray();

    foreach ($years as $year) {
        $filter['options']["{$year}-01-01"] = (string) $year;
    }

    return $filter;
}
```

## Creating a Filter Trait

Filters should be extracted into reusable traits so multiple list components can share them.

File location: `app-modules/{module}/src/Traits/{Name}FilterTrait.php`

Example: `app-modules/liefertool/src/Traits/YearFilterTrait.php`

The noerd module ships ready-made filter traits — `ShowFromFilterTrait` (date ranges),
`TenantFilterTrait` and `SetupLanguageFilterTrait` — see [Reusable Traits](traits.md).

```php
<?php

namespace Nywerk\Liefertool\Traits;

use Nywerk\Liefertool\Models\Order;

trait YearFilterTrait
{
    protected function getYearListFilter(): array
    {
        $filter['label'] = 'Jahr';
        $filter['column'] = 'created_at';
        $filter['type'] = 'Picklist';
        $filter['options'] = [];

        $years = Order::selectRaw('YEAR(created_at) as year')
            ->where('tenant_id', auth()->user()->selected_tenant_id)
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        foreach ($years as $year) {
            $filter['options']["{$year}-01-01"] = (string) $year;
        }

        return $filter;
    }
}
```

Another example: `app-modules/cms/src/Traits/LanguageFilterTrait.php`

```php
protected function getLanguageListFilter(): array
{
    $filter['label'] = __('Language');
    $filter['column'] = 'language';
    $filter['type'] = 'Picklist';
    $filter['options'] = [];

    $languages = CmsLanguage::where('tenant_id', auth()->user()->selected_tenant_id)
        ->where('is_active', true)
        ->orderBy('is_default', 'desc')
        ->orderBy('name', 'asc')
        ->get();

    foreach ($languages as $language) {
        $filter['options'][$language->code] = $language->name;
    }

    return $filter;
}
```

## Using the Filter in a Component

To add filters to a list component:

1. Use the filter trait
2. The `NoerdList` trait auto-discovers all methods matching `get*ListFilter` via its default `tableFilters()` implementation

```php
<?php

use Livewire\Component;
use Noerd\Traits\NoerdList;
use Nywerk\Liefertool\Traits\YearFilterTrait;

new class extends Component {
    use NoerdList;
    use YearFilterTrait;

    // tableFilters() is auto-discovered from the trait — no override needed
};
```

You only need to override `tableFilters()` if you want to conditionally show filters:

```php
#[Computed]
public function tableFilters(): array
{
    if (! $this->hasMultipleLanguages()) {
        return [];
    }

    return [$this->getLanguageListFilter()];
}
```

## Filter Preselection

You can derive filter values in the query-building method and pass them directly to the query. This is useful when the dropdown value needs to be transformed (e.g. a year selection into a date range). For model-backed lists this happens in a `listData()` override; the example below is a repository-backed list (no `$listModel`), which keeps its query in `with()`.

Example from `orders-list`: When a year is selected in the dropdown, the date range is derived and passed directly to the repository. If no year is selected, the current year is used as default.

```php
private function getDateRange(): array
{
    $date = isset($this->listFilters['created_at'])
        ? Carbon::parse($this->listFilters['created_at'])
        : Carbon::today();

    return [
        $date->startOfYear()->format('Y-m-d'),
        $date->copy()->endOfYear()->format('Y-m-d'),
    ];
}

public function with(): array
{
    [$dateFrom, $dateTo] = $this->getDateRange();

    $rows = $orderRepository->getOrders(
        Auth::user()->selected_tenant_id,
        $this->filter,
        $this->search,
        $this->sortField,
        $this->sortAsc,
        $this->customerId,
        $dateFrom,
        $dateTo,
    );

    return [
        'listConfig' => $this->buildList($rows),
    ];
}
```

## Session Persistence

By default, `storeActiveListFilters()` in `NoerdList` is empty. Override it in components where the selected filter should persist across page loads.

Example from `pages-list` (CMS language filter):

```php
public function storeActiveListFilters(): void
{
    session(['listFilters' => $this->listFilters]);

    // Sync with selectedLanguage for page-detail consistency
    if (! empty($this->listFilters['language'])) {
        session(['selectedLanguage' => $this->listFilters['language']]);
    }
}
```

The `storeActiveListFilters` method is called automatically by the UI via `wire:change="storeActiveListFilters"` on the filter dropdown.

## Security

The `NoerdList` trait extracts allowed columns from the `tableFilters()` output. Only columns returned by the filter methods can be filtered on, preventing users from manipulating filter parameters to query arbitrary columns.

You can also define `ALLOWED_TABLE_FILTERS` as a constant in your component for an explicit whitelist:

```php
protected const ALLOWED_TABLE_FILTERS = [
    'vehicle_id',
    'created_at',
];
```
