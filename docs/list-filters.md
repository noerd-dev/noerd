# List Filters

Lists can be filtered using dropdown filters (`tableFilters`). Dropdown filters allow users to narrow results by selecting a value (e.g. year, language).

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

Example: `app-modules/order/src/Traits/YearFilterTrait.php`

```php
<?php

namespace Nywerk\Order\Traits;

use Nywerk\Order\Models\Order;

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
    $filter['label'] = __('cms_label_language');
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
use Nywerk\Order\Traits\YearFilterTrait;

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

You can derive filter values in the `with()` method and pass them directly to the query. This is useful when the dropdown value needs to be transformed (e.g. a year selection into a date range).

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
