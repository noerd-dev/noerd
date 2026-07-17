<?php

namespace Noerd\Traits;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use LogicException;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Scopes\SearchScope;
use Noerd\Scopes\SortScope;
use Noerd\Services\ColumnFilterParser;
use Noerd\Services\ListQueryContext;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

trait NoerdList
{
    use WithoutUrlPagination;
    use WithPagination;

    protected const COLUMN_TYPE_MAP = [
        'tinyint' => 'bool',
        'boolean' => 'bool',
        'integer' => 'number',
        'bigint' => 'number',
        'smallint' => 'number',
        'decimal' => 'number',
        'float' => 'number',
        'double' => 'number',
        'date' => 'date',
        'datetime' => 'datetime',
        'timestamp' => 'datetime',
    ];

    public int $perPage = 50;

    public $lastChangeTime;

    public string $search = '';

    public string $sortField = 'id';

    public bool $sortAsc = false;

    public string $listActionMethod = 'listAction';

    public ?string $selectListConfig = null;

    /**
     * Active alternate list view key (the "--{key}" YAML suffix); null renders the
     * base YAML. Persisted per component in session ('listView.{component}') — as a
     * composite '{app}::{key}' when the view belongs to another app.
     */
    public ?string $listView = null;

    /**
     * Source-app folder (lowercase) of the active list view when it belongs to an
     * app other than the session's current one; null = current app. The session
     * app itself is never changed by switching views.
     */
    public ?string $listViewApp = null;

    /**
     * URL representation of the active list view (?view=…) so a shared link opens
     * the same view. Carries the dropdown key — plain ('vip'), composite with
     * '--' as the app separator ('gastro--vip', keeps '%3A%3A' out of the URL)
     * or 'default' for the standard view; null only on single-view and embedded
     * lists (param omitted). On mount the URL takes precedence over the
     * session-saved view.
     */
    #[Url(as: 'view')]
    public ?string $listViewParam = null;

    public string $listId = '';

    #[Url]
    public ?string $filter = null;

    public array $listFilters = [];

    /**
     * Excel-style per-column header filters: raw user input keyed by column field,
     * e.g. ['price' => '>=10', 'is_active' => '1', 'status' => 'open']. Applied by
     * listQuery() and persisted per component in session ('listColumnFilters.{component}').
     * Mirrored into the URL (?cf[price]=>=10) so a shared link reproduces the exact
     * view; on mount the URL wins over the session state. The initial-load URL write
     * happens in the list Blade (url-sync script) — Livewire only syncs on updates.
     */
    #[Url(as: 'cf')]
    public array $listColumnFilters = [];

    public mixed $context = '';

    public bool $disableModal = false;

    public bool $compact = false;

    /**
     * Opt-in multi-select mode: renders a leading checkbox column and a confirm
     * bar so the list can be opened as a picker that hands a set of ids back to
     * the opener (see confirmRecordSelection()). Off by default — existing lists
     * are unaffected.
     */
    public bool $multiSelect = false;

    /** @var array<int, int> Ids ticked while the list is in multi-select mode. */
    public array $selectedRecordIds = [];

    /**
     * Opt-in Excel-style row numbers: renders a leading number column that restarts
     * at 1 on every pagination page. Enabled per list via `showLineNumbers: true` in
     * the list YAML (or this property as a tag attribute). Off by default.
     */
    public bool $showLineNumbers = false;

    /**
     * Picker mode: the list was opened to hand a selection back to an opener — it
     * dispatches recordsSelected on confirm and a row click ticks the row instead
     * of opening it. When false the list is a normal page whose selection drives
     * the YAML-defined bulk actions instead.
     */
    public bool $returnsSelection = false;

    public bool $minimal = false;

    /** @var array<int, string> Field names to render in minimal mode, in order. */
    public array $minimalColumns = [];

    public int $minimalLimit = 5;

    public ?string $showMoreComponent = null;

    /** @var array<string, mixed> */
    public array $showMoreArguments = [];

    public bool $enableCsvExport = false;

    /** The model class behind this list, remembered from the last listQuery() call. */
    protected ?string $resolvedModelClass = null;

    /**
     * Custom list-config name handed to listQuery(), for lists whose YAML does not
     * follow the component-name convention (e.g. 'accounting-customers-list').
     * Search, column filters and the filterable-column whitelist all resolve
     * through it; null = the component's own config.
     */
    protected ?string $listQueryConfigName = null;

    /** @var array<string, array<string, array<int, array{value: mixed, label: string}>>> */
    protected array $picklistOptionCache = [];

    /** @var array<int, string>|null Request cache for filterableColumnFields(). */
    protected ?array $filterableColumnCache = null;

    private static array $schemaColumnCache = [];

    /**
     * Whether a column field addresses a nested path rather than a real DB column — e.g. a custom
     * attribute (`custom_attributes.sap_number`) or a relation (`customer.name`). Such fields resolve at
     * render time via data_get(); the database cannot sort or search on them.
     */
    public static function isDottedField(string $field): bool
    {
        return str_contains($field, '.');
    }

    public function mount(): void
    {
        $this->mountList();
    }

    public function mountList(): void
    {
        $this->listId = Str::random();
        $this->perPage = session('listPerPage', 50);
        $this->loadListFilters();

        // Column filters: a ?cf[...] URL param (shared link) wins over the session
        // state and is persisted. Embedded lists (compact/picker) never apply it —
        // the param addresses the page-level list, but Livewire hydrates it on
        // nested lists too.
        $urlColumnFilters = (! $this->compact && ! $this->returnsSelection)
            ? array_filter($this->listColumnFilters, fn($value): bool => is_string($value) && trim($value) !== '')
            : [];
        if ($urlColumnFilters !== []) {
            $this->listColumnFilters = $urlColumnFilters;
            session(["listColumnFilters.{$this->componentName()}" => $urlColumnFilters]);
        } else {
            $this->listColumnFilters = session("listColumnFilters.{$this->componentName()}", []);
        }

        $savedSort = session("listSort.{$this->componentName()}");
        if ($savedSort) {
            $this->sortField = $savedSort['field'];
            $this->sortAsc = $savedSort['asc'];
        }

        $savedView = session("listView.{$this->componentName()}");

        // A ?view= URL param (shared link) wins over the session-saved view.
        // Embedded lists (compact/picker) never apply it — the param addresses
        // the page-level list, but Livewire hydrates it on nested lists too.
        $urlView = (! $this->compact && ! $this->returnsSelection) ? $this->listViewParam : null;
        if ($urlView !== null && $urlView !== '') {
            // The URL carries '--' instead of '::' as the app separator (no
            // '%3A%3A' noise); decode it back to the composite key. A hand-typed
            // legacy '::' still parses as-is.
            if (! str_contains($urlView, '::') && str_contains($urlView, '--')) {
                $urlView = Str::replaceFirst('--', '::', $urlView);
            }
            [$urlApp, $urlKey] = StaticConfigHelper::parseListViewKey($urlView);
            if ($urlApp !== null && $urlApp === StaticConfigHelper::getCurrentApp()) {
                $urlView = $urlKey;
            }
            if ($urlView === 'default' || array_key_exists($urlView, $this->availableListViews)) {
                $savedView = $urlView;
                session(["listView.{$this->componentName()}" => $urlView]);
            }
        }

        if ($savedView) {
            // A composite key whose app has become the current app collapses to
            // its plain form (selected elsewhere, reopened inside that app).
            [$savedApp, $savedKey] = StaticConfigHelper::parseListViewKey($savedView);
            if ($savedApp !== null && $savedApp === StaticConfigHelper::getCurrentApp()) {
                $savedView = $savedKey;
            }
            if ($savedView !== 'default' && array_key_exists($savedView, $this->availableListViews)) {
                $this->applyListViewKey($savedView);
            }
        }

        // The base view can be hidden from this user (a role-restricted
        // 'default') or exist only in another allowed app: fall to the first
        // view they are allowed to see. When every view is filtered away, the
        // base stays — fail open.
        if ($this->listView === null && $this->listViewApp === null
            && $this->availableListViews !== []
            && ! array_key_exists('default', $this->availableListViews)) {
            $this->applyListViewKey(array_key_first($this->availableListViews));
        }

        $this->syncListViewParam();
    }

    /**
     * Mirror the resolved view state into the ?view= URL param — including
     * 'default', so a shared link pins the standard view too. Composite keys are
     * written with '--' instead of '::' so the URL stays free of '%3A%3A'
     * encoding. The param stays null on single-view lists (no switcher, nothing
     * to share) and always null on embedded lists, which must never write the
     * page-level param.
     */
    private function syncListViewParam(): void
    {
        if ($this->compact || $this->returnsSelection || count($this->availableListViews) < 2) {
            $this->listViewParam = null;

            return;
        }

        $activeKey = StaticConfigHelper::composeListViewKey($this->listViewApp, $this->listView);
        $this->listViewParam = str_replace('::', '--', $activeKey);
    }

    /**
     * All views available for this list across every allowed app: per app the
     * base config ('default') plus every "--{key}" sibling YAML. Current-app
     * entries carry plain keys, other apps' entries composite '{app}::{key}'
     * keys. Memoised per request (discovery globs directories).
     *
     * @return array<string, array{key: string, app: string, appLabel: string, title: string}>
     */
    #[Computed]
    public function availableListViews(): array
    {
        return StaticConfigHelper::getListViews($this->getDetailComponent());
    }

    /**
     * Switch the active list view and remember it per component in the session.
     * Unknown keys are ignored (e.g. a stale dropdown after a view YAML was removed).
     */
    public function switchListView(string $key): void
    {
        if (! array_key_exists($key, $this->availableListViews)) {
            return;
        }

        $this->applyListViewKey($key);
        session(["listView.{$this->componentName()}" => $key]);
        $this->syncListViewParam();
        $this->selectedRecordIds = [];
        $this->resetPage();
        $this->syncListQueryContext();
    }

    /**
     * Set the active view state ($listViewApp + $listView) from a dropdown key —
     * plain ('vip') or composite ('gastro::vip').
     */
    private function applyListViewKey(string $key): void
    {
        [$app, $viewKey] = StaticConfigHelper::parseListViewKey($key);
        $this->listViewApp = $app;
        $this->listView = $viewKey === 'default' ? null : $viewKey;
    }

    public function updatedPerPage(): void
    {
        session(['listPerPage' => $this->perPage]);
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->syncListQueryContext();
    }

    public function updatedSortField(): void
    {
        $this->syncListQueryContext();
    }

    public function updatedSortAsc(): void
    {
        $this->syncListQueryContext();
    }

    public function sortBy(string $field): void
    {
        // A dotted field is not a real column (e.g. `custom_attributes.sap_number`, `customer.name`), so
        // the query could not order by it — listQuery() would silently fall back to `id`. Refuse it here
        // rather than let the header appear to sort and do nothing.
        if (self::isDottedField($field)) {
            return;
        }

        $listConfig = $this->getListConfig();
        $notSortable = $listConfig['notSortableColumns'] ?? [];
        if (in_array($field, $notSortable)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortAsc = ! $this->sortAsc;
        } else {
            $this->sortAsc = true;
        }
        $this->sortField = $field;
        $this->syncListQueryContext();

        session(["listSort.{$this->componentName()}" => [
            'field' => $this->sortField,
            'asc' => $this->sortAsc,
        ]]);
    }

    public function loadListFilters(): void
    {
        $this->listFilters = session('listFilters', []);
    }

    public function storeActiveListFilters(): void
    {
        session(['listFilters' => $this->listFilters]);
    }

    public function clearAllListFilters(): void
    {
        $this->listFilters = [];
        session(['listFilters' => []]);

        $this->listColumnFilters = [];
        session()->forget("listColumnFilters.{$this->componentName()}");
        $this->resetPage();
    }

    /**
     * Set or replace the Excel-style header filter of one column. An empty value
     * clears it. The raw expression is stored as typed ('>=10', 'rot'); parsing
     * happens at query time.
     */
    public function setColumnFilter(string $field, ?string $value): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            unset($this->listColumnFilters[$field]);
        } else {
            $this->listColumnFilters[$field] = $value;
        }

        session(["listColumnFilters.{$this->componentName()}" => $this->listColumnFilters]);
        $this->resetPage();
    }

    public function clearColumnFilter(string $field): void
    {
        $this->setColumnFilter($field, null);
    }

    public function findListAction(int|string $id): void
    {
        $this->syncListQueryContext();
        $withData = $this->with();
        $listData = $withData['listConfig']['rows'] ?? [];
        // In picker mode a row click (or Enter) ticks the row instead of opening it.
        $method = $this->returnsSelection ? 'toggleRecordSelection' : $this->listActionMethod;

        if (is_array($listData)) {
            $item = $listData[$id] ?? null;
            if ($item) {
                $this->{$method}($item['id']);
            }

            return;
        }

        $item = $listData->getCollection()->get($id);
        if (! $item) {
            return;
        }
        $itemId = is_array($item) ? ($item['id'] ?? null) : $item->id;
        if ($itemId === null) {
            return;
        }
        $this->{$method}($itemId);
    }

    /**
     * Handle select action - dispatch selection event and close modal.
     */
    public function selectAction(mixed $modelId = null, array $relations = []): void
    {
        $this->dispatchSelectionEvents($modelId);
    }

    /**
     * Toggle a single row in the multi-select set. Wired as the listActionMethod
     * in multi-select mode, so a row click (or Enter) toggles it.
     */
    public function toggleRecordSelection(int|string $id): void
    {
        $id = (int) $id;

        if (in_array($id, $this->selectedRecordIds, true)) {
            $this->selectedRecordIds = array_values(array_filter(
                $this->selectedRecordIds,
                fn(int $selected): bool => $selected !== $id,
            ));

            return;
        }

        $this->selectedRecordIds[] = $id;
    }

    /**
     * Toggle every row on the current page on/off in one go.
     */
    public function toggleSelectAllVisible(): void
    {
        $ids = $this->visibleRowIds();
        if ($ids === []) {
            return;
        }

        $allSelected = array_diff($ids, $this->selectedRecordIds) === [];

        $this->selectedRecordIds = $allSelected
            ? array_values(array_diff($this->selectedRecordIds, $ids))
            : array_values(array_unique(array_merge($this->selectedRecordIds, $ids)));
    }

    /**
     * Hand the selected ids back to the opener and close the picker modal.
     */
    public function confirmRecordSelection(): void
    {
        $this->dispatch('recordsSelected', ids: $this->selectedRecordIds, context: $this->context);
        $this->dispatch('closeTopModal');
    }

    /**
     * Generic bulk action: delete every selected record. Wire it up from a list's
     * YAML `bulkActions` (with a `confirm:` for the confirmation prompt) — no
     * per-list delete method is needed. Deletes go through the tenant-scoped query
     * and fire model events so observers/auditing still run.
     */
    public function deleteSelected(): void
    {
        if ($this->selectedRecordIds === []) {
            return;
        }

        // Building the list query once populates resolvedModelClass for this request.
        $this->with();

        if ($this->resolvedModelClass !== null) {
            $this->resolvedModelClass::query()
                ->whereIn('id', $this->selectedRecordIds)
                ->get()
                ->each(fn($model) => $model->delete());
        }

        $this->selectedRecordIds = [];
        $this->resetPage();
    }

    /**
     * Ids of the rows currently rendered (current page).
     *
     * @return array<int, int>
     */
    public function visibleRowIds(): array
    {
        $rows = $this->with()['listConfig']['rows'] ?? null;
        if ($rows === null) {
            return [];
        }

        $collection = is_array($rows) ? collect($rows) : $rows->getCollection();

        return $collection
            ->map(fn($row): int => (int) (is_array($row) ? ($row['id'] ?? 0) : $row->id))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get the component name (alias for getName).
     */
    public function getComponentName(): string
    {
        return $this->getName();
    }

    public function updateRow(): void {}

    #[Computed]
    public function tableFilters(): array
    {
        $filters = [];
        foreach (get_class_methods($this) as $method) {
            if (preg_match('/^get.+ListFilter$/', $method)) {
                $filter = $this->{$method}();
                if ($filter !== null) {
                    $filters[] = $filter;
                }
            }
        }

        return $filters;
    }

    public function states(): void {}

    public function listFilters(): array
    {
        return [];
    }

    public function listStates(): array
    {
        return [];
    }

    public function filters(): void {}

    public function refreshList(): void
    {
        $this->dispatch('$refresh');
    }

    public function renderingNoerdList(): void
    {
        if ($this->minimal) {
            $this->perPage = $this->minimalLimit;
        }

        $this->syncListQueryContext();
    }

    public function exportCsv(): StreamedResponse
    {
        [$query, $columns, $filename] = $this->prepareCsvExport();

        return response()->streamDownload(function () use ($query, $columns): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_map(
                fn(array $column): string => __($column['label'] ?? $column['field'] ?? ''),
                $columns,
            ), ';');

            $query->lazy(200)->each(function ($row) use ($handle, $columns): void {
                $this->prepareExportRow($row);
                $line = [];
                foreach ($columns as $column) {
                    $line[] = $this->formatCsvValue(
                        data_get($row, $column['field'] ?? ''),
                        $column,
                    );
                }
                fputcsv($handle, $line, ';');
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Set the default sort field and direction.
     * Call this in mount() to configure initial sorting.
     */
    protected function setDefaultSort(string $field, bool $ascending = false): void
    {
        $savedSort = session("listSort.{$this->componentName()}");
        if ($savedSort) {
            $this->sortField = $savedSort['field'];
            $this->sortAsc = $savedSort['asc'];
        } else {
            $this->sortField = $field;
            $this->sortAsc = $ascending;
        }
        $this->syncListQueryContext();
    }

    protected function getAllowedListFilterColumns(): array
    {
        if (defined('static::ALLOWED_TABLE_FILTERS') && ! empty(static::ALLOWED_TABLE_FILTERS)) {
            return static::ALLOWED_TABLE_FILTERS;
        }

        return collect($this->tableFilters)->pluck('column')->filter()->toArray();
    }

    protected function applyListFilters($query): void
    {
        if (! $this->listFilters) {
            return;
        }

        $allowed = $this->getAllowedListFilterColumns();
        $filterTypes = collect($this->tableFilters())->pluck('type', 'column')->toArray();

        foreach ($this->listFilters as $key => $value) {
            if (! in_array($key, $allowed) || ! $value) {
                continue;
            }

            $type = $filterTypes[$key] ?? '';

            if ($type === 'ShowFrom' && method_exists($this, 'resolveShowDate')) {
                $date = $this->resolveShowDate($value);
                if ($date) {
                    $query->where($this->getShowFromDateColumn(), '>=', $date);
                }
            } elseif ($type === 'ShowUntil' && method_exists($this, 'resolveShowDate')) {
                $date = $this->resolveShowDate($value);
                if ($date) {
                    $query->where($this->getShowUntilDateColumn(), '<=', $date);
                }
            } else {
                $query->where($key, $value);
            }
        }
    }

    /**
     * Get the detail component name.
     * Uses DETAIL_COMPONENT constant if defined, otherwise derives from component name.
     */
    protected function getDetailComponent(): string
    {
        if (defined('static::DETAIL_COMPONENT')) {
            return static::DETAIL_COMPONENT;
        }

        return $this->getName();
    }

    /**
     * Get the list component name.
     * Uses LIST_COMPONENT constant if defined, otherwise derives from detail component name.
     * 'customer-detail' → 'customers-list'
     */
    protected function getListComponent(): string
    {
        if (defined('static::LIST_COMPONENT')) {
            return static::LIST_COMPONENT;
        }

        $name = $this->getName();

        // If this is already a list component, return as-is
        if (Str::endsWith($name, '-list')) {
            return $name;
        }

        // Extract entity: 'customer-detail' → 'customer'
        $entity = Str::before($name, '-detail');

        // Pluralize and add -list: 'customer' → 'customers-list'
        return Str::plural($entity) . '-list';
    }

    protected function componentName(): string
    {
        return defined('static::COMPONENT') ? static::COMPONENT : $this->getName();
    }

    /**
     * Get the event name for select mode.
     * Derives from COMPONENT: 'customers-list' -> 'customerSelected'
     * Strips any Livewire namespace prefix: 'booking-members::customers-list' -> 'customerSelected'
     */
    protected function getSelectEvent(): string
    {
        $name = $this->componentName();

        if (str_contains($name, '::')) {
            $name = Str::afterLast($name, '::');
        }

        if (str_contains($name, '.')) {
            $name = Str::afterLast($name, '.');
        }

        $entity = Str::singular(Str::before($name, '-list'));

        return Str::camel($entity) . 'Selected';
    }

    protected function dispatchSelectionEvents(mixed $modelId = null): void
    {
        $this->dispatch('noerdRelationSelected', $modelId, $this->context);
        $this->dispatch($this->getSelectEvent(), $modelId, $this->context);
        $this->dispatch('closeTopModal');
    }

    protected function syncListQueryContext(): void
    {
        app(ListQueryContext::class)->set(
            $this->search,
            $this->sortField,
            $this->sortAsc,
        );
    }

    /**
     * Build a query with search, sort and column filters applied based on YAML
     * columns. Pass $configName when the list renders a custom YAML config
     * (the same name handed to buildList()).
     */
    protected function listQuery(string $modelClass, ?string $configName = null): Builder
    {
        $this->resolvedModelClass = $modelClass;
        $this->listQueryConfigName = $configName;
        $this->filterableColumnCache = null;

        $query = $modelClass::query()
            ->withoutGlobalScope(SearchScope::class)
            ->withoutGlobalScope(SortScope::class);

        $listConfig = $this->getListConfig($configName);

        if (! empty($this->search)) {
            $searchableFields = ! empty($listConfig['searchableColumns'])
                ? $listConfig['searchableColumns']
                : collect($listConfig['columns'] ?? [])->pluck('field')->filter()->toArray();

            $table = (new $modelClass())->getTable();
            $validFields = array_filter($searchableFields, fn($f) => Schema::hasColumn($table, $f));

            if (! empty($validFields)) {
                $search = $this->search;
                $query->where(function (Builder $q) use ($validFields, $search): void {
                    foreach (array_values($validFields) as $index => $field) {
                        $index === 0
                            ? $q->where($field, 'like', '%' . $search . '%')
                            : $q->orWhere($field, 'like', '%' . $search . '%');
                    }
                });
            }
        }

        $this->applyColumnFilters($query, $modelClass);

        $table = (new $modelClass())->getTable();
        $sortField = Schema::hasColumn($table, $this->sortField) ? $this->sortField : 'id';
        $query->orderBy($sortField, $this->sortAsc ? 'asc' : 'desc');

        return $query;
    }

    /**
     * Fields the user may filter on via the header funnel: every YAML column that
     * is not dotted, not 'action', and a real column on the resolved model's
     * table — the same rule as sorting. Empty until listQuery() has resolved the
     * model class, so lists with fully custom queries get no funnels.
     *
     * @return array<int, string>
     */
    protected function filterableColumnFields(): array
    {
        if ($this->resolvedModelClass === null) {
            return [];
        }

        if ($this->filterableColumnCache !== null) {
            return $this->filterableColumnCache;
        }

        $table = (new $this->resolvedModelClass())->getTable();

        return $this->filterableColumnCache = collect($this->getListConfig($this->listQueryConfigName)['columns'] ?? [])
            ->pluck('field')
            ->filter(fn($field): bool => is_string($field)
                && $field !== 'action'
                && ! self::isDottedField($field)
                && Schema::hasColumn($table, $field))
            ->values()
            ->all();
    }

    /**
     * Apply every active Excel-style column filter (AND-combined, stacking with
     * search and the header listFilters). Skipped for compact/minimal embedded
     * lists, which render no filter UI — a session-stored filter must not
     * invisibly hide their rows.
     */
    protected function applyColumnFilters(Builder $query, string $modelClass): void
    {
        if ($this->listColumnFilters === [] || $this->compact || $this->minimal) {
            return;
        }

        $allowed = $this->filterableColumnFields();
        if ($allowed === []) {
            return;
        }

        $table = (new $modelClass())->getTable();
        $schemaTypes = $this->schemaColumnTypeMap($table);
        $yamlTypes = collect($this->getListConfig($this->listQueryConfigName)['columns'] ?? [])
            ->filter(fn($column): bool => isset($column['field'], $column['type']))
            ->pluck('type', 'field')
            ->toArray();
        $picklistFields = array_keys($this->picklistOptionsFromDetail());

        foreach ($this->listColumnFilters as $field => $raw) {
            if (! in_array($field, $allowed, true) || ! is_string($raw) || trim($raw) === '') {
                continue;
            }

            // Same type resolution as the rendered header cell: explicit YAML type,
            // else a detail-picklist column counts as badge, else the schema type.
            $type = $yamlTypes[$field] ?? null;
            if (($type === null || $type === 'text') && in_array($field, $picklistFields, true)) {
                $type = 'badge';
            }
            $type ??= $schemaTypes[$field] ?? 'text';

            ColumnFilterParser::apply($query, $field, $type, $raw);
        }
    }

    /**
     * Auto-detect column types from database schema for columns without explicit type in YAML.
     */
    protected function applyAutoColumnTypes(array $listSettings, mixed $rows): array
    {
        // With zero rows (e.g. a column filter matching nothing) no model instance can
        // be pulled from the result set — fall back to the class listQuery() resolved,
        // so bool/date columns keep their type and the filter popover keeps its UI.
        $model = $this->resolveModelFromRows($rows)
            ?? ($this->resolvedModelClass !== null ? new $this->resolvedModelClass() : null);
        if (! $model) {
            return $listSettings;
        }

        $columnTypeMap = $this->schemaColumnTypeMap($model->getTable());

        foreach ($listSettings['columns'] ?? [] as $i => $column) {
            if (isset($column['type'])) {
                continue;
            }
            $field = $column['field'] ?? null;
            if ($field && isset($columnTypeMap[$field])) {
                $listSettings['columns'][$i]['type'] = $columnTypeMap[$field];
            }
        }

        // Auto-align number/currency columns to the right (matching cell alignment)
        foreach ($listSettings['columns'] ?? [] as $i => $column) {
            if (isset($column['align'])) {
                continue;
            }
            $type = $column['type'] ?? 'text';
            if (in_array($type, ['number', 'currency'])) {
                $listSettings['columns'][$i]['align'] = 'right';
            }
        }

        return $listSettings;
    }

    /**
     * Map of column name => noerd column type for the given table, derived from
     * the DB schema. Shared by auto column typing and the column filters.
     *
     * @return array<string, string>
     */
    protected function schemaColumnTypeMap(string $table): array
    {
        $schemaColumns = self::$schemaColumnCache[$table]
            ??= Schema::getColumns($table);

        $columnTypeMap = [];
        foreach ($schemaColumns as $col) {
            $normalized = mb_strtolower(preg_replace('/\(.*\)/', '', $col['type_name']));
            if (isset(self::COLUMN_TYPE_MAP[$normalized])) {
                $columnTypeMap[$col['name']] = self::COLUMN_TYPE_MAP[$normalized];
            }
        }

        return $columnTypeMap;
    }

    protected function resolveModelFromRows(mixed $rows): ?Model
    {
        if ($rows instanceof LengthAwarePaginator
            || $rows instanceof Paginator) {
            $first = $rows->getCollection()->first();
        } elseif ($rows instanceof Collection) {
            $first = $rows->first();
        } else {
            return null;
        }

        return $first instanceof Model ? $first : null;
    }

    /**
     * Build complete list configuration including rows and table state.
     * Returns all data needed for the list.index DETAIL_COMPONENT.
     *
     * @param  LengthAwarePaginator|array  $rows
     */
    protected function buildList(mixed $rows, string|array|null $config = null): array
    {
        $listSettings = is_array($config)
            ? $config
            : $this->getListConfig($config);

        $listSettings = $this->applyAutoColumnTypes($listSettings, $rows);
        $listSettings = $this->applyPicklistBadges($listSettings);

        return [
            'listId' => $this->listId,
            'sortField' => $this->sortField,
            'sortAsc' => $this->sortAsc,
            'notSortableColumns' => $listSettings['notSortableColumns'] ?? [],
            'rows' => $rows,
            'listSettings' => $listSettings,
            'listColumnFilters' => $this->listColumnFilters,
            'filterableColumns' => $this->filterableColumnFields(),
        ];
    }

    /**
     * Render columns that mirror a detail picklist (a `type: select` field with
     * inline options) as translated badges. The option labels are read from the
     * paired detail YAML — no per-list configuration is needed. A column that
     * already declares an explicit type or its own options is left untouched, so a
     * list can still opt in manually with `type: badge` + `options`.
     */
    protected function applyPicklistBadges(array $listSettings): array
    {
        $optionsByField = $this->picklistOptionsFromDetail();
        if ($optionsByField === []) {
            return $listSettings;
        }

        foreach ($listSettings['columns'] ?? [] as $i => $column) {
            $field = $column['field'] ?? null;
            if ($field === null || isset($column['options'])) {
                continue;
            }

            $type = $column['type'] ?? null;
            if ($type !== null && $type !== 'text') {
                continue;
            }

            if (isset($optionsByField[$field])) {
                $listSettings['columns'][$i]['type'] = 'badge';
                $listSettings['columns'][$i]['options'] = $optionsByField[$field];
            }
        }

        return $listSettings;
    }

    /**
     * Map of `field => options` for every `type: select` field (with inline
     * options) declared in this list's paired detail YAML. Memoised per request.
     *
     * @return array<string, array<int, array{value: mixed, label: string}>>
     */
    protected function picklistOptionsFromDetail(): array
    {
        $detailComponent = $this->pairedDetailComponent();
        if ($detailComponent === null) {
            return [];
        }

        if (array_key_exists($detailComponent, $this->picklistOptionCache)) {
            return $this->picklistOptionCache[$detailComponent];
        }

        $fields = StaticConfigHelper::tryGetComponentFields($detailComponent)['fields'] ?? [];
        $map = [];
        $this->collectSelectOptions($fields, $map);

        return $this->picklistOptionCache[$detailComponent] = $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, array<int, array{value: mixed, label: string}>>  $map
     */
    protected function collectSelectOptions(array $fields, array &$map): void
    {
        foreach ($fields as $field) {
            if (($field['type'] ?? null) === 'block') {
                $this->collectSelectOptions($field['fields'] ?? [], $map);

                continue;
            }

            if (($field['type'] ?? null) !== 'select' || empty($field['options']) || ! isset($field['name'])) {
                continue;
            }

            $key = Str::after($field['name'], 'detailData.');
            $map[$key] = $field['options'];
        }
    }

    /**
     * The detail component paired with this list by convention
     * (`{x}-list` → `{x}-detail`, preserving any dotted subfolder), or null when
     * this component is not a standard list.
     */
    protected function pairedDetailComponent(): ?string
    {
        $name = Str::afterLast($this->componentName(), '::');
        $prefix = Str::contains($name, '.') ? Str::beforeLast($name, '.') . '.' : '';
        $last = Str::afterLast($name, '.');

        if (! Str::endsWith($last, '-list')) {
            return null;
        }

        return $prefix . Str::singular(Str::before($last, '-list')) . '-detail';
    }

    /**
     * Get list configuration from YAML.
     * Uses self::DETAIL_COMPONENT by default, or a custom name if provided.
     * In select mode, uses selectListConfig if set.
     */
    protected function getListConfig(?string $customName = null): array
    {
        // List YAML almost never declares a `model:` key, so the resolved model class is handed to the
        // config layer explicitly. It is populated by listQuery(); calls that run before it (e.g. the
        // sortable-column check) simply pass null and resolve the plain YAML.
        $modelClass = $this->resolvedModelClass;

        if ($customName === null && $this->listActionMethod === 'selectAction' && $this->selectListConfig) {
            return StaticConfigHelper::getListConfig($this->selectListConfig, $modelClass);
        }

        $name = $customName ?? $this->getDetailComponent();

        // An active alternate view only applies to this component's own config,
        // never to an explicitly requested custom config. A view from another
        // app resolves via explicit-app lookup; the session app stays untouched.
        if ($customName === null && ($this->listView !== null || $this->listViewApp !== null)) {
            $viewName = $this->listView !== null ? "{$name}--{$this->listView}" : $name;
            $config = $this->listViewApp !== null
                ? StaticConfigHelper::getListConfigForApp($this->listViewApp, $viewName, $modelClass)
                : StaticConfigHelper::getListConfig($viewName, $modelClass);
            if ($config !== []) {
                return $config;
            }
            // The view's YAML disappeared mid-session — fall back to the default view.
            $this->listView = null;
            $this->listViewApp = null;
            $this->syncListViewParam();
        }

        return StaticConfigHelper::getListConfig($name, $modelClass);
    }

    /**
     * Override in the component to enable CSV export.
     *
     * @return array{0: Builder, 1: array, 2: string}
     */
    protected function prepareCsvExport(): array
    {
        throw new LogicException('Override prepareCsvExport() to enable CSV export.');
    }

    protected function prepareExportRow(mixed $row): void {}

    protected function formatCsvValue(mixed $value, array $column): string
    {
        $type = $column['type'] ?? 'text';

        return match ($type) {
            'bool', 'boolean' => $value ? __('Yes') : __('No'),
            'date' => $value ? Carbon::parse($value)->format('d.m.Y') : '',
            'datetime' => $value ? Carbon::parse($value)->format('d.m.Y H:i') : '',
            'currency', 'number' => is_numeric($value)
                ? number_format((float) $value, 2, ',', '.')
                : (string) ($value ?? ''),
            'badge' => __($this->badgeLabel($value, $column['options'] ?? [])),
            default => (string) ($value ?? ''),
        };
    }

    /**
     * Resolve a picklist value to its option label (untranslated); falls back to
     * the raw value when no option matches.
     *
     * @param  array<int, array{value: mixed, label: string}>  $options
     */
    protected function badgeLabel(mixed $value, array $options): string
    {
        $value = $value instanceof BackedEnum ? $value->value : ($value instanceof UnitEnum ? $value->name : $value);

        foreach ($options as $option) {
            if (isset($option['value']) && (string) $option['value'] === (string) $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return (string) ($value ?? '');
    }

    /**
     * Get the event listeners for the component.
     * Dynamically registers the refreshList listener based on detail component name.
     */
    protected function getListeners(): array
    {
        $name = $this->getDetailComponent();
        $stripped = Str::afterLast($name, '.');

        $listeners = ['refreshList-' . $name => 'refreshList'];

        if ($name !== $stripped) {
            $listeners['refreshList-' . $stripped] = 'refreshList';
        }

        return $listeners;
    }
}
