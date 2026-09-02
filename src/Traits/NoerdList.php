<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use LogicException;
use Noerd\Facades\Noerd;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\FormatHelper;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Services\ColumnFilterParser;
use Noerd\Services\HeaderActionsRegistry;
use Noerd\Services\RelationTitleResolver;
use Noerd\Support\LayoutFields;
use Noerd\Support\ListCellFormatter;
use Noerd\Support\SchemaColumnCache;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

trait NoerdList
{
    use NoerdComponentShared;
    use RoutedModal;
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

    protected const MAX_PER_PAGE = 200;

    public int $perPage = 50;

    public string $search = '';

    public string $sortField = 'id';

    public bool $sortAsc = false;

    public string $listActionMethod = 'listAction';

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

    /**
     * Free-form list filter carried in the URL (?filter=…). The trait itself
     * never reads it — consuming lists seed their own listFilters from it in
     * mount(), which makes it part of the public API of this trait.
     */
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

    public bool $compact = false;

    /**
     * Opt-in multi-select mode: renders a leading checkbox column and a confirm
     * bar so the list can be opened as a picker that hands a set of ids back to
     * the opener (see confirmRecordSelection()). Off by default — existing lists
     * are unaffected.
     */
    public bool $multiSelect = false;

    /** @var array<int, int|string> Ids ticked while the list is in multi-select mode (string for composite ids of manual-row lists). */
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

    /**
     * Named route of the full list opened by "show more". Preferred over
     * $showMoreComponent, which stays as the fallback. The browser URL is NOT
     * rewritten — the modal shows a list narrowed by $showMoreArguments, which a
     * plain list route cannot express.
     */
    public ?string $showMoreRoute = null;

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

    /**
     * Request cache for relationColumnPath(), keyed by column field. Keeps the
     * relation-method probing (one model instantiation per segment) to a single
     * run per field and request.
     *
     * @var array<string, array{relation: string, column: string, table: string}|null>|null
     */
    protected ?array $relationColumnPathCache = null;

    /** @var array<string, mixed>|null Last buildList() result, memoized per request. */
    protected ?array $builtListConfigCache = null;

    /** @var array<string, mixed>|null */
    protected ?array $headerControlsCache = null;

    /** @var array<string, bool>|null Request cache for isJsonColumnPath(), keyed by column field. */
    protected ?array $jsonColumnPathCache = null;

    /** @var array<string, array<string, mixed>> Request memo for getListConfig() (incl. layout overrides). */
    protected array $listConfigMemo = [];

    /** One reusable instance of the resolved model, for table/cast introspection. */
    private ?Model $resolvedModelInstanceMemo = null;

    /**
     * Whether a column field addresses a nested path rather than a real DB column — e.g. a custom
     * attribute (`custom_attributes.sap_number`) or a relation (`customer.name`). Such fields resolve at
     * render time via data_get(); the database cannot sort or search on them. Column filters are the
     * exception: a path into a JSON-cast column filters via the arrow operator (see isJsonColumnPath()).
     */
    public static function isDottedField(string $field): bool
    {
        return str_contains($field, '.');
    }

    /**
     * Whether the list may be ORDERED BY this column — the single rule behind the table header's
     * sort button and the grid list's sort dropdown. A dotted field resolves out of a JSON column
     * or a relation at render time, so the query cannot order by it (listQuery() would silently
     * fall back to `id`); `action` is a button column; `notSortableColumns` is the YAML opt-out.
     *
     * Callers pass notSortableColumns explicitly: the render path has them from the BUILT config,
     * which is also correct for array configs (buildList($rows, $config)), alternate list views and
     * custom config names — unlike a fresh getListConfig() lookup, which would resolve to an empty
     * config there and wrongly offer every column as sortable.
     *
     * @param  array<int, string>  $notSortableColumns
     */
    public function isSortableColumn(string $field, array $notSortableColumns = []): bool
    {
        return $field !== 'action'
            && ! self::isDottedField($field)
            && ! in_array($field, $notSortableColumns, true);
    }

    public function mount(): void
    {
        $this->mountList();
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
        return StaticConfigHelper::getListViews($this->listConfigComponent());
    }

    /**
     * Lists may declare `public ?string $detailRoute = '...';` (a named Livewire
     * route of the detail full page, e.g. 'crm.account.detail') INSTEAD of a
     * $detailComponent. Noerd::modalRoute() opens the component behind the route
     * and rewrites the browser URL to it (+ ?modal=true); closing the modal
     * restores the previous list URL. Reloading the rewritten URL reopens the
     * record as a modal over the previously visited page (see
     * RoutedModal::redirectToRoutedModal()). Opt-in — lists without the property
     * keep the plain query-param behavior.
     *
     * $detailComponent stays alongside as the fallback: it opens when the route
     * name is not registered, so a list may reference a detail route owned by an
     * optional module.
     */
    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        Noerd::modalFor(
            property_exists($this, 'detailRoute') ? $this->detailRoute : null,
            property_exists($this, 'detailComponent') ? $this->detailComponent : null,
            ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    public function listData(): array
    {
        $rows = $this->listQuery($this->listModel)->paginate($this->clampPerPage($this->perPage));

        return $this->buildList($rows);
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
    }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->clampPerPage($this->perPage);
        session(["listPerPage.{$this->componentName()}" => $this->perPage]);
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        // A new search shrinks the result set — staying on page 3 would show an
        // empty table even though there are matches on page 1.
        $this->resetPage();
    }


    public function sortBy(string $field): void
    {
        // Refuse a column the query could not order by rather than let the header appear
        // to sort and do nothing (see isSortableColumn()).
        if (! $this->isSortableColumn($field, $this->getListConfig()['notSortableColumns'] ?? [])) {
            return;
        }

        $this->sortAsc = $this->sortField === $field ? ! $this->sortAsc : true;
        $this->sortField = $field;
        $this->persistListSort();
    }

    /**
     * Set the sort direction without changing the column — the grid list's sort dropdown offers it
     * as two explicit entries. Deliberately NOT routed through sortBy(): direction is always a safe
     * operation, also for a $sortField that is no sortable column at all (the technical default
     * `id`, or a stale sort restored from the session after a YAML change).
     */
    public function setSortDirection(bool $ascending): void
    {
        if ($this->sortAsc === $ascending) {
            return;
        }

        $this->sortAsc = $ascending;
        $this->persistListSort();
    }

    public function storeActiveListFilters(): void
    {
        session(['listFilters' => $this->listFilters]);
        $this->resetPage();
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
        $value = mb_trim((string) $value);

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

    /**
     * Active Excel-style column filters resolved for display in the list header:
     * one chip per filter carrying the translated column label and a
     * human-readable value (option labels for picklist/badge columns, Yes/No for
     * booleans, the raw expression as typed otherwise).
     *
     * @return array<int, array{field: string, label: string, value: string}>
     */
    #[Computed]
    public function activeColumnFilterChips(): array
    {
        $active = array_filter(
            $this->listColumnFilters,
            fn($value): bool => is_string($value) && mb_trim($value) !== '',
        );

        if ($active === []) {
            return [];
        }

        $columns = collect($this->getListConfig($this->listQueryConfigName)['columns'] ?? [])
            ->filter(fn($column): bool => isset($column['field']))
            ->keyBy('field');
        $picklistOptions = $this->picklistOptionsFromDetail();
        $model = $this->resolvedModelInstance();
        $schemaTypes = $model !== null
            ? $this->schemaColumnTypeMap($model->getTable())
            : [];

        $chips = [];
        foreach ($active as $field => $raw) {
            $column = $columns->get($field, []);
            $type = $column['type'] ?? $schemaTypes[$field] ?? null;
            $options = $column['options'] ?? $picklistOptions[$field] ?? [];

            $value = mb_trim($raw);
            if (in_array($type, ['bool', 'boolean', 'inversebool'], true)) {
                $value = $value === '1' ? __('Yes') : __('No');
            } else {
                foreach ($options as $option) {
                    if ((string) ($option['value'] ?? '') === $value) {
                        $value = __($option['label'] ?? $value);
                        break;
                    }
                }
            }

            $chips[] = [
                'field' => $field,
                'label' => __($column['label'] ?? $field),
                'value' => $value,
            ];
        }

        return $chips;
    }

    /**
     * The generic header controls this list actually renders, resolved ONCE so the
     * header row, the filter drawer and modal-title cannot disagree about what
     * exists. Every consumer reads this — never re-derive "does the list have a
     * search field / secondary action" from $listSettings at the call site.
     *
     * The action indices are preserved on purpose: splitting the YAML actions into
     * a primary and a secondary group must not renumber them, because the position
     * in the YAML is what assigns the keyboard shortcut.
     *
     * @return array{search: bool, csv: bool, secondary: array<int, array<string, mixed>>, primary: array<int, array<string, mixed>>, registry: array<int, string>}
     */
    public function headerControls(): array
    {
        if ($this->headerControlsCache !== null) {
            return $this->headerControlsCache;
        }

        $config = $this->builtListConfig();
        $settings = $config['listSettings'] ?? [];

        // A picker is reduced to selecting rows: it offers neither the YAML actions
        // nor the module-contributed ones. A read-denied list keeps its header but
        // loses the data affordances (buildList() already stripped the actions).
        $isPicker = $this->returnsSelection;
        $actions = $isPicker ? [] : ($settings['actions'] ?? []);

        return $this->headerControlsCache = [
            'search' => ! ($settings['disableSearch'] ?? false) && ! ($config['objectAccessDenied'] ?? false),
            'csv' => $this->enableCsvExport,
            'secondary' => array_filter($actions, static fn(array $action): bool => ($action['style'] ?? '') === 'secondary'),
            'primary' => array_filter($actions, static fn(array $action): bool => ($action['style'] ?? '') !== 'secondary'),
            'registry' => $isPicker ? [] : app(HeaderActionsRegistry::class)->listActions(),
        ];
    }

    /**
     * Whether anything renders in the half of the header that collapses into the
     * filter drawer below `lg` (search, CSV export, `style: secondary` actions).
     */
    public function hasCollapsibleControls(): bool
    {
        $controls = $this->headerControls();

        return $controls['search'] || $controls['csv'] || $controls['secondary'] !== [];
    }

    /**
     * Whether the header renders any generic control at all — the question
     * modal-title asks before it injects them into a custom header slot.
     */
    public function hasHeaderControls(): bool
    {
        $controls = $this->headerControls();

        return $this->hasCollapsibleControls() || $controls['primary'] !== [] || $controls['registry'] !== [];
    }

    /**
     * Open a row by its POSITION in the current page — the keyboard path only
     * (arrow keys track a positional index, Enter submits it). Resolving the
     * position costs a full listData() round; mouse clicks know the model id
     * and go straight to openListRow().
     */
    public function findListAction(int|string $id): void
    {

        $listData = $this->resolvedListConfig()['rows'] ?? [];

        $item = is_array($listData) ? ($listData[$id] ?? null) : $listData->getCollection()->get($id);
        if (! $item) {
            return;
        }

        $itemId = is_array($item) ? ($item['id'] ?? null) : $item->id;
        if ($itemId === null) {
            return;
        }

        $this->openListRow($itemId);
    }

    /**
     * Open a row by its model id — the wire:click target for row and cell
     * clicks. In picker mode the click ticks the row instead. The re-render is
     * skipped when the action is the trait's own dispatch-only listAction: the
     * response would re-send the unchanged list HTML just to fire the modal
     * event, and the row highlight is Alpine state that survives without it.
     */
    public function openListRow(int|string $modelId): void
    {
        // Rows without an id (aggregated/grouped lists) render an empty id —
        // a click on them stays a no-op, exactly like the index path resolves it.
        if ($modelId === '') {
            return;
        }

        if ($this->returnsSelection) {
            $this->toggleRecordSelection($modelId);

            return;
        }

        $this->callRowAction($modelId);

        if ($this->listActionMethod === 'listAction' && $this->usesTraitListAction()) {
            $this->skipRender();
        }
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
        $id = $this->normalizeRecordId($id);

        if (in_array($id, $this->selectedRecordIds, true)) {
            $this->selectedRecordIds = array_values(array_filter(
                $this->selectedRecordIds,
                fn(int|string $selected): bool => $selected !== $id,
            ));

            return;
        }

        $this->selectedRecordIds[] = $id;
    }

    /**
     * Numeric row ids stay ints (the storage type of every model-backed list);
     * non-numeric ids (composite string ids of manual-row lists) stay strings, so
     * strict comparisons work for both.
     */
    public function normalizeRecordId(mixed $id): int|string
    {
        return is_numeric($id) ? (int) $id : (string) $id;
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

        // Building the list query once populates resolvedModelClass and
        // listQueryConfigName for the checks below.
        $this->resolvedListConfig();

        // The method is public (Livewire-callable), but must only act when the
        // list's YAML actually declares this bulk action — a list that never
        // offered it can never be bulk-deleted through it.
        if (! $this->listDeclaresBulkAction('deleteSelected')) {
            return;
        }

        // Server-side guard: the bulk-delete button is hidden for delete-denied
        // users, but the method stays directly invokable.
        if (! AccessHelper::canDeleteObject($this->objectPermissionModelClass())) {
            return;
        }

        if ($this->resolvedModelClass !== null) {
            // Only rows THIS list yields may be deleted. A bare
            // Model::query()->whereIn($selectedRecordIds) ignored the list's own
            // narrowing (tenant scope, column filters, and any constraint a
            // component adds), so a crafted id list reached records the list
            // would never show.
            $query = $this->listQuery($this->resolvedModelClass, $this->listQueryConfigName)
                ->whereIn('id', $this->selectedRecordIds);

            // listQuery() cannot see constraints a component adds in its own
            // listData() override (e.g. "users of MY tenants"), so those lists
            // are additionally limited to the rows currently rendered.
            if (! $this->usesTraitListData()) {
                $query->whereIn('id', $this->visibleRowIds() ?: [0]);
            }

            $query->get()->each(fn($model) => $model->delete());
        }

        $this->selectedRecordIds = [];
        // The guard checks above resolved (and memoized) the pre-delete list —
        // drop it so the re-render queries the surviving rows.
        $this->builtListConfigCache = null;
        $this->headerControlsCache = null;
        $this->resetPage();
    }

    /**
     * Ids of the rows currently rendered (current page).
     *
     * @return array<int, int|string>
     */
    public function visibleRowIds(): array
    {
        $rows = $this->resolvedListConfig()['rows'] ?? null;
        if ($rows === null) {
            return [];
        }

        $collection = is_array($rows) ? collect($rows) : $rows->getCollection();

        return $collection
            ->map(fn($row): int|string => $this->normalizeRecordId(is_array($row) ? ($row['id'] ?? 0) : $row->id))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Inline cell editing (editable list columns dispatch
     * `updateRow(id, column, value)` via wire:change). The base implementation
     * is a deliberate no-op — a list opts into persistence by overriding this
     * method with the same signature.
     */
    public function updateRow(int|string|null $id = null, ?string $column = null, mixed $value = null): void {}

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

    public function renderingNoerdList(): void
    {
        if ($this->minimal) {
            $this->perPage = $this->minimalLimit;
        }

    }

    public function exportCsv(): StreamedResponse
    {
        // Populates resolvedModelClass so the read check sees the actual model.
        $this->resolvedListConfig();
        abort_unless(AccessHelper::canReadObject($this->objectPermissionModelClass()), 403);

        [$query, $columns, $filename] = $this->prepareCsvExport();

        return response()->streamDownload(function () use ($query, $columns): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            $delimiter = FormatHelper::csvDelimiter();
            fputcsv($handle, array_map(
                fn(array $column): string => __($column['label'] ?? $column['field'] ?? ''),
                $columns,
            ), $delimiter);

            $query->lazy(200)->each(function ($row) use ($handle, $columns, $delimiter): void {
                $this->prepareExportRow($row);
                $line = [];
                foreach ($columns as $column) {
                    $line[] = $this->formatCsvValue(
                        data_get($row, $column['field'] ?? ''),
                        $column,
                    );
                }
                fputcsv($handle, $line, $delimiter);
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The buildList() result, computed at most once per request. The header
     * chrome (modal-title's generic list controls) and the list body both read
     * this — for with()-style components Livewire evaluates with() before the
     * view renders, so the cache is already populated when a custom header
     * slot executes; slim components compute it lazily here.
     *
     * @return array<string, mixed>
     */
    public function builtListConfig(): array
    {
        return $this->builtListConfigCache ??= $this->resolvedListConfig();
    }

    /**
     * The table's schema columns keyed by column name, introspected at most once
     * per table and process (see SchemaColumnCache) — on an 8-column list the
     * uncached Schema::hasColumn() calls used to mean a two-digit number of
     * metadata queries per render.
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function tableColumns(string $table): array
    {
        return SchemaColumnCache::columns($table);
    }

    /**
     * Cached replacement for Schema::hasColumn() (see tableColumns()).
     */
    protected static function tableHasColumn(string $table, string $column): bool
    {
        return SchemaColumnCache::hasColumn($table, $column);
    }

    protected function mountList(): void
    {
        $this->listId = Str::random();

        // A list that IS a record (opened by route as a modal, e.g. the object of
        // the custom-attribute manager) reopens as a modal over the previously
        // visited page when its ?modal=true URL is loaded directly. Plain lists
        // are never routed that way and fall straight through.
        if ($this->redirectToRoutedModal()) {
            return;
        }

        $this->perPage = session("listPerPage.{$this->componentName()}", 50);
        $this->loadListFilters();

        // Column filters: a ?cf[...] URL param (shared link) wins over the session
        // state and is persisted. Embedded lists (compact/picker) never apply it —
        // the param addresses the page-level list, but Livewire hydrates it on
        // nested lists too.
        $urlColumnFilters = (! $this->compact && ! $this->returnsSelection)
            ? array_filter($this->listColumnFilters, fn($value): bool => is_string($value) && mb_trim($value) !== '')
            : [];
        if ($urlColumnFilters !== []) {
            $this->listColumnFilters = $urlColumnFilters;
            session(["listColumnFilters.{$this->componentName()}" => $urlColumnFilters]);
        } else {
            $this->listColumnFilters = session("listColumnFilters.{$this->componentName()}", []);
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

        // The base view can be hidden from this user (a restricted 'default')
        // or exist only in another allowed app: fall to the first
        // view they are allowed to see. When every view is filtered away, the
        // base stays — fail open.
        if ($this->listView === null && $this->listViewApp === null
            && $this->availableListViews !== []
            && ! array_key_exists('default', $this->availableListViews)) {
            $this->applyListViewKey(array_key_first($this->availableListViews));
        }

        $this->syncListViewParam();

        // The sort restore runs AFTER the view restore on purpose: with no
        // session-saved sort the default comes from the ACTIVE view's YAML
        // (`defaultSort:`), so an alternate list view brings its own order.
        $savedSort = session("listSort.{$this->componentName()}");
        if ($savedSort) {
            $this->sortField = $savedSort['field'];
            $this->sortAsc = $savedSort['asc'];
        } else {
            $this->applyDefaultSortFromConfig();
        }

        // Deep-link support: ?{entity}Id=5 opens the record's modal over the
        // list, ?create=1 the create modal. Mount-only BY DESIGN — Livewire
        // updates POST to /livewire/update and carry no page query, and the
        // former `rendering()` hook was both accidental-initial-load-only and
        // silently disabled by any component defining its own rendering().
        $deepLinkId = (int) request()->query($this->getDeepLinkParam());
        if ($deepLinkId) {
            $this->listAction($deepLinkId);
        } elseif (request()->query('create')) {
            $this->listAction();
        }
    }

    /**
     * The header filters live in ONE session bucket shared across lists ON
     * PURPOSE: NoerdPage::setPreselect() seeds it from detail pages so a
     * related list opens pre-filtered, and preselect() reads it back. Only
     * columns whitelisted per list (getAllowedListFilterColumns) ever apply.
     */
    protected function loadListFilters(): void
    {
        $this->listFilters = session('listFilters', []);
    }

    /**
     * Dispatch a row click to the configured listActionMethod. The method name
     * is a mount argument (pickers pass `selectAction`), so it is resolved by
     * name — but only PUBLIC methods are Livewire actions, and only those may
     * be reached this way. Anything else is ignored instead of exposing the
     * component's protected internals to a client-chosen name.
     */
    protected function callRowAction(int|string $modelId): void
    {
        $method = $this->listActionMethod;

        if (! method_exists($this, $method) || ! (new ReflectionMethod($this, $method))->isPublic()) {
            return;
        }

        $this->{$method}($modelId);
    }

    /**
     * Keep the client-controlled page size within sane bounds — an unbounded
     * ->paginate($perPage) is a memory-exhaustion vector.
     */
    protected function clampPerPage(int $perPage): int
    {
        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * Whether the active list config declares a bulk action running the given
     * method — the authorization for invoking that bulk action server-side.
     */
    protected function listDeclaresBulkAction(string $action): bool
    {
        foreach ($this->getListConfig($this->listQueryConfigName)['bulkActions'] ?? [] as $bulkAction) {
            if (($bulkAction['action'] ?? null) === $action) {
                return true;
            }
        }

        return false;
    }

    /**
     * The list config regardless of the component's style: lists declaring
     * $listModel build it via listData(), lists with a custom with() already
     * return it as view data. Lets generic trait features (row click, select-all,
     * bulk delete) work for both without assuming one style.
     *
     * @return array<string, mixed>
     */
    protected function resolvedListConfig(): array
    {
        // Memoized per request: actions like toggleSelectAllVisible() resolve the
        // list before the render does — without the memo each resolution runs the
        // full paginated query again. Mutating actions (deleteSelected) clear the
        // cache so their re-render queries fresh rows.
        if ($this->builtListConfigCache !== null) {
            return $this->builtListConfigCache;
        }

        if (property_exists($this, 'listModel') && $this->listModel) {
            return $this->listData();
        }

        return method_exists($this, 'with') ? ($this->with()['listConfig'] ?? []) : [];
    }

    /**
     * Columns the header filters (listFilters) may constrain — every declared
     * table filter column. Override to allow additional keys.
     */
    protected function getAllowedListFilterColumns(): array
    {
        return collect($this->tableFilters)->pluck('column')->filter()->toArray();
    }

    protected function applyListFilters(Builder $query): void
    {
        if (! $this->listFilters) {
            return;
        }

        $allowed = $this->getAllowedListFilterColumns();
        $filterTypes = collect($this->tableFilters)->pluck('type', 'column')->toArray();

        foreach ($this->listFilters as $key => $value) {
            if (! in_array((string) $key, array_map('strval', $allowed), true) || ! $value) {
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
     * The name this list's YAML config resolves under — the component's own
     * name. Override when a component renders another list's YAML.
     */
    protected function listConfigComponent(): string
    {
        return $this->componentName();
    }

    /**
     * Get the event name for select mode.
     * Derives from COMPONENT: 'customers-list' -> 'customerSelected'
     * Strips any Livewire namespace prefix: 'booking-members::customers-list' -> 'customerSelected'
     */
    protected function getSelectEvent(): string
    {
        return Str::camel($this->getListEntity()) . 'Selected';
    }

    /**
     * Get the URL query parameter that deep-links a record of this list.
     * Derives from COMPONENT: 'products-list' -> 'productId'
     */
    protected function getDeepLinkParam(): string
    {
        return Str::camel($this->getListEntity()) . 'Id';
    }

    /**
     * Get the singular entity name of this list.
     * Derives from COMPONENT: 'customers-list' -> 'customer'
     * Strips any Livewire namespace prefix: 'booking-members::customers-list' -> 'customer'
     */
    protected function getListEntity(): string
    {
        $name = $this->componentName();

        if (str_contains($name, '::')) {
            $name = Str::afterLast($name, '::');
        }

        if (str_contains($name, '.')) {
            $name = Str::afterLast($name, '.');
        }

        return Str::singular(Str::before($name, '-list'));
    }

    protected function dispatchSelectionEvents(mixed $modelId = null): void
    {
        $this->dispatch('noerdRelationSelected', $modelId, $this->context);
        $this->dispatch($this->getSelectEvent(), $modelId, $this->context);
        $this->dispatch('closeTopModal');
    }

    /**
     * Build a query with search, sort and column filters applied based on YAML
     * columns. Pass $configName when the list renders a custom YAML config
     * (the same name handed to buildList()).
     */
    protected function listQuery(string $modelClass, ?string $configName = null): Builder
    {
        if ($this->resolvedModelClass !== $modelClass || $this->listQueryConfigName !== $configName) {
            $this->filterableColumnCache = null;
            $this->relationColumnPathCache = null;
            $this->jsonColumnPathCache = null;
            $this->resolvedModelInstanceMemo = null;
        }
        $this->resolvedModelClass = $modelClass;
        $this->listQueryConfigName = $configName;

        $query = $modelClass::query();

        // Read-denied objects yield no rows in ANY rendering mode (page, embedded,
        // picker, minimal) — restricted data must never leave the database.
        if (! AccessHelper::canReadObject($modelClass)) {
            $query->whereRaw('1 = 0');
        }

        $listConfig = $this->getListConfig($configName);
        $table = $this->resolvedModelInstance()->getTable();

        if (! empty($this->search)) {
            $searchableFields = ! empty($listConfig['searchableColumns'])
                ? $listConfig['searchableColumns']
                : collect($listConfig['columns'] ?? [])->pluck('field')->filter()->toArray();

            $validFields = array_filter($searchableFields, fn($f) => is_string($f) && self::tableHasColumn($table, $f));

            if (! empty($validFields)) {
                // Contains match with escaped LIKE wildcards — same rule and
                // driver-portable escape as ColumnFilterParser.
                $search = $this->search;
                $query->where(function (Builder $q) use ($validFields, $search): void {
                    foreach (array_values($validFields) as $index => $field) {
                        ColumnFilterParser::applyLikeContains($q, $field, $search, $index === 0 ? 'and' : 'or');
                    }
                });
            }
        }

        $this->applyColumnFilters($query, $modelClass);
        $this->eagerLoadRelationColumns($query);

        // $sortField is client-writable (sortBy() enforces isSortableColumn(),
        // a raw property update does not). Ordering by a column the model hides
        // — password, remember_token, api_token — turns the list into an
        // oracle over that value, so hidden attributes are never sortable.
        $hidden = $this->resolvedModelInstance()?->getHidden() ?? [];
        $sortField = self::tableHasColumn($table, $this->sortField)
            && ! in_array($this->sortField, $hidden, true)
                ? $this->sortField
                : 'id';
        $query->orderBy($sortField, $this->sortAsc ? 'asc' : 'desc');

        return $query;
    }

    /**
     * Eager-load the relations behind dotted relation columns
     * (`customer.name`, `defaultDeliveryAddress.locality`) so rendering them does
     * not lazy-load once per row. JSON-column paths (custom_attributes.x) resolve
     * in memory and are skipped. Relation paths are validated by relationColumnPath().
     */
    protected function eagerLoadRelationColumns(Builder $query): void
    {
        $relations = [];

        foreach ($this->getListConfig($this->listQueryConfigName)['columns'] ?? [] as $column) {
            $field = $column['field'] ?? null;
            if (! is_string($field) || ! self::isDottedField($field) || $this->isJsonColumnPath($field)) {
                continue;
            }

            $path = $this->relationColumnPath($field);
            if ($path !== null) {
                $relations[$path['relation']] = true;
            }
        }

        if ($relations !== []) {
            $query->with(array_keys($relations));
        }
    }

    /**
     * Fields the user may filter on via the header funnel: every YAML column that
     * is not 'action' and either a real column on the resolved model's table
     * (the same rule as sorting), a path into a JSON-cast column
     * (`custom_attributes.x`) or a path through Eloquent relations
     * (`defaultDeliveryAddress.locality`). Empty until listQuery() has resolved
     * the model class, so lists with fully custom queries get no funnels.
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

        $table = $this->resolvedModelInstance()->getTable();

        return $this->filterableColumnCache = collect($this->getListConfig($this->listQueryConfigName)['columns'] ?? [])
            ->pluck('field')
            ->filter(fn($field): bool => is_string($field)
                && $field !== 'action'
                && (self::isDottedField($field)
                    ? ($this->isJsonColumnPath($field) || $this->relationColumnPath($field) !== null)
                    : self::tableHasColumn($table, $field)))
            ->values()
            ->all();
    }

    /**
     * Whether a dotted column field addresses a path inside a JSON column of the
     * resolved model (e.g. `custom_attributes.sap_number`): the base segment must
     * be a real table column that the model casts to an array/object, so the
     * query can filter it via the JSON arrow operator. Relation paths
     * (`customer.name`) have no such column and stay unfilterable.
     */
    protected function isJsonColumnPath(string $field): bool
    {
        if ($this->resolvedModelClass === null) {
            return false;
        }

        $this->jsonColumnPathCache ??= [];

        if (array_key_exists($field, $this->jsonColumnPathCache)) {
            return $this->jsonColumnPathCache[$field];
        }

        $model = $this->resolvedModelInstance();
        $base = Str::before($field, '.');

        return $this->jsonColumnPathCache[$field] = self::tableHasColumn($model->getTable(), $base)
            && $model->hasCast($base, ['array', 'json', 'object', 'collection']);
    }

    /**
     * Resolve a dotted column field into a relation filter target: everything
     * before the last dot is the (possibly nested) relation path handed to
     * whereHas(), the last segment the column on the related table — e.g.
     * `defaultDeliveryAddress.locality` => relation `defaultDeliveryAddress`,
     * column `locality`. Returns null whenever a segment is not a relation
     * method or the column does not exist, so a YAML typo never breaks the list.
     *
     * @return array{relation: string, column: string, table: string}|null
     */
    protected function relationColumnPath(string $field): ?array
    {
        if ($this->resolvedModelClass === null) {
            return null;
        }

        $this->relationColumnPathCache ??= [];

        if (array_key_exists($field, $this->relationColumnPathCache)) {
            return $this->relationColumnPathCache[$field];
        }

        return $this->relationColumnPathCache[$field] = $this->resolveRelationColumnPath($field);
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

        $table = $this->resolvedModelInstance()->getTable();
        $schemaTypes = $this->schemaColumnTypeMap($table);
        $yamlTypes = collect($this->getListConfig($this->listQueryConfigName)['columns'] ?? [])
            ->filter(fn($column): bool => isset($column['field'], $column['type']))
            ->pluck('type', 'field')
            ->toArray();
        $picklistFields = array_keys($this->picklistOptionsFromDetail());

        foreach ($this->listColumnFilters as $field => $raw) {
            if (! in_array($field, $allowed, true) || ! is_string($raw) || mb_trim($raw) === '') {
                continue;
            }

            // A relation path (`defaultDeliveryAddress.locality`) filters through a
            // whereHas() subquery on the related table — a join would collide with
            // the base table's column names. JSON paths take precedence and fall
            // through to the arrow-operator branch below.
            $relationPath = self::isDottedField($field) && ! $this->isJsonColumnPath($field)
                ? $this->relationColumnPath($field)
                : null;
            if ($relationPath !== null) {
                $relationType = $yamlTypes[$field]
                    ?? $this->schemaColumnTypeMap($relationPath['table'])[$relationPath['column']]
                    ?? 'text';
                $query->whereHas(
                    $relationPath['relation'],
                    fn(Builder $related) => ColumnFilterParser::apply($related, $relationPath['column'], $relationType, $raw),
                );

                continue;
            }

            // Same type resolution as the rendered header cell: explicit YAML type,
            // else a detail-picklist column counts as badge, else the schema type.
            $type = $yamlTypes[$field] ?? null;
            if (($type === null || $type === 'text') && in_array($field, $picklistFields, true)) {
                $type = 'badge';
            }
            $type ??= $schemaTypes[$field] ?? 'text';

            // A JSON path (custom_attributes.x) filters via the arrow operator on
            // its base column; it has no schema type, so YAML/picklist typing
            // applies with text as the fallback.
            $column = self::isDottedField($field) ? str_replace('.', '->', $field) : $field;

            ColumnFilterParser::apply($query, $column, $type, $raw);
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
        $model = $this->resolveModelFromRows($rows) ?? $this->resolvedModelInstance();
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
            if (in_array($type, ['number', 'currency'], true)) {
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
        $columnTypeMap = [];
        foreach (self::tableColumns($table) as $name => $col) {
            $normalized = mb_strtolower(preg_replace('/\(.*\)/', '', $col['type_name']));
            if (isset(self::COLUMN_TYPE_MAP[$normalized])) {
                $columnTypeMap[$name] = self::COLUMN_TYPE_MAP[$normalized];
            }
        }

        return $columnTypeMap;
    }

    /**
     * One shared instance of the resolved model class for table-name and cast
     * introspection — the render path asks for it many times per request.
     */
    protected function resolvedModelInstance(): ?Model
    {
        if ($this->resolvedModelClass === null) {
            return null;
        }

        return $this->resolvedModelInstanceMemo ??= new $this->resolvedModelClass();
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
     * Returns all data needed for the list view.
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
        $this->primeRelationBadgeTitles($listSettings, $rows);

        // Object permissions: strip the affordances the current user may not use.
        // In-memory lists (no model class) stay unrestricted.
        $permissionModel = $this->objectPermissionModelClass();
        $objectAccessDenied = ! AccessHelper::canReadObject($permissionModel);
        if ($objectAccessDenied) {
            unset($listSettings['actions']);
        } else {
            // "New …" buttons (action: listAction, or a route-opened record) are
            // CREATE affordances; every other header action stays gated by write.
            $canCreate = AccessHelper::canCreateObject($permissionModel);
            $canWrite = AccessHelper::canWriteObject($permissionModel);
            if (! $canCreate || ! $canWrite) {
                $listSettings['actions'] = array_values(array_filter(
                    $listSettings['actions'] ?? [],
                    function (array $action) use ($canCreate, $canWrite): bool {
                        $isCreate = ($action['action'] ?? null) === 'listAction' || isset($action['route']);

                        return $isCreate ? $canCreate : $canWrite;
                    },
                ));
                if ($listSettings['actions'] === []) {
                    unset($listSettings['actions']);
                }
            }
        }
        if (! AccessHelper::canDeleteObject($permissionModel)) {
            $listSettings['bulkActions'] = array_values(array_filter(
                $listSettings['bulkActions'] ?? [],
                fn(array $action): bool => ($action['action'] ?? null) !== 'deleteSelected',
            ));
        }

        // buildList() REPLACES the config, so anything derived from it must go too.
        $this->headerControlsCache = null;

        return $this->builtListConfigCache = [
            'listId' => $this->listId,
            'sortField' => $this->sortField,
            'sortAsc' => $this->sortAsc,
            'notSortableColumns' => $listSettings['notSortableColumns'] ?? [],
            'rows' => $rows,
            'listSettings' => $listSettings,
            'listColumnFilters' => $this->listColumnFilters,
            'filterableColumns' => $this->filterableColumnFields(),
            'objectAccessDenied' => $objectAccessDenied,
        ];
    }

    /**
     * The model class the object permission checks key off: an explicitly
     * declared `public ?string $objectPermissionModel` wins (for repository-
     * backed lists without $listModel, e.g. the liefertool orders list), then
     * the class resolved by the last listQuery() call, falling back to a
     * declared $listModel. Null for in-memory/manual lists — those are never
     * restricted. Deliberately not declared on the trait — a component
     * redeclares it with its own default (same pattern as $listModel).
     */
    protected function objectPermissionModelClass(): ?string
    {
        if (property_exists($this, 'objectPermissionModel') && $this->objectPermissionModel !== null) {
            return $this->objectPermissionModel;
        }

        return $this->resolvedModelClass
            ?? (property_exists($this, 'listModel') ? $this->listModel : null);
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
     * Resolve the titles of every `relationBadge` column for the current page in
     * one query per column instead of one per cell: the resolver's per-id memo is
     * primed with a whereIn batch, so the table cells only read memoized values.
     */
    protected function primeRelationBadgeTitles(array $listSettings, mixed $rows): void
    {
        $badgeFields = [];
        foreach ($listSettings['columns'] ?? [] as $column) {
            if (($column['type'] ?? null) === 'relationBadge' && isset($column['field'])) {
                $badgeFields[] = $column['field'];
            }
        }
        if ($badgeFields === []) {
            return;
        }

        if ($rows instanceof LengthAwarePaginator || $rows instanceof Paginator) {
            $collection = $rows->getCollection();
        } elseif ($rows instanceof Collection) {
            $collection = $rows;
        } elseif (is_array($rows)) {
            $collection = collect($rows);
        } else {
            return;
        }

        if ($collection->isEmpty()) {
            return;
        }

        $resolver = app(RelationTitleResolver::class);
        foreach ($badgeFields as $field) {
            $resolver->prime($field, $collection->map(fn($row) => data_get($row, $field))->all());
        }
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
        LayoutFields::walk($fields, function (array $field) use (&$map): void {
            if (! isset($field['name'])) {
                return;
            }

            // A value-storing collection select (valueField) resolves its badge
            // labels from the tenant's collection entries — same source as the
            // form element. Id-storing collection selects stay excluded (their
            // columns are FK ids, not picklist values).
            if (($field['type'] ?? null) === 'setupCollectionSelect'
                && ! empty($field['collectionKey'])
                && ! empty($field['valueField'])) {
                $options = SetupCollectionHelper::selectOptions(
                    $field['collectionKey'],
                    $field['displayField'] ?? 'name',
                    $field['valueField'],
                );

                if ($options !== []) {
                    $map[Str::after($field['name'], 'detailData.')] = $options;
                }

                return;
            }

            if (($field['type'] ?? null) !== 'select' || empty($field['options'])) {
                return;
            }

            $map[Str::after($field['name'], 'detailData.')] = $field['options'];
        });

        return $this->picklistOptionCache[$detailComponent] = $map;
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
     * Get list configuration from YAML — the component's own config by
     * default, or a custom config name when provided.
     */
    protected function getListConfig(?string $customName = null): array
    {
        // Memoized per request: the render path resolves the config from several
        // places (query, filters, chips, bulk-action guard), and every raw
        // resolution re-runs the layout-override hook (an extension may back it by DB).
        $memoKey = implode('|', [
            $customName ?? '',
            $this->listView ?? '',
            $this->listViewApp ?? '',
            $this->listActionMethod,
        ]);
        if (array_key_exists($memoKey, $this->listConfigMemo)) {
            return $this->listConfigMemo[$memoKey];
        }

        return $this->listConfigMemo[$memoKey] = $this->resolveListConfig($customName);
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
            'bool', 'boolean' => ListCellFormatter::truthy($value) ? __('Yes') : __('No'),
            'date' => FormatHelper::date($value),
            'datetime' => FormatHelper::dateTime($value),
            'currency', 'number' => is_numeric($value)
                ? FormatHelper::decimal((float) $value)
                : $this->neutralizeCsvFormula((string) ($value ?? '')),
            'badge' => $this->neutralizeCsvFormula(ListCellFormatter::format($value, $column)),
            default => $this->neutralizeCsvFormula((string) ($value ?? '')),
        };
    }

    /**
     * Excel/Sheets interpret a cell starting with =, +, -, @, tab or CR as a
     * formula, so a value like `=HYPERLINK(...)` in a user-entered text field
     * would execute on open. Prefix a single quote to keep such a value literal.
     */
    protected function neutralizeCsvFormula(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * @param  array<int, array{value: mixed, label: string}>  $options
     */
    protected function badgeLabel(mixed $value, array $options): string
    {
        return ListCellFormatter::badgeLabel($value, $options);
    }

    /**
     * Get the event listeners for the component.
     * Dynamically registers the refreshList listener based on the config name.
     *
     * OVERRIDE CONTRACT: a component defining its own getListeners() replaces
     * this set entirely and silently disconnects the framework events — always
     * merge: `return parent-style via the trait alias + [...]` (see
     * NoerdDetail's pageGetListeners alias for the pattern). #[On] attributes
     * are no alternative here: the event names embed getName()/config-derived
     * values, which attribute interpolation cannot express.
     */
    protected function getListeners(): array
    {
        $name = $this->listConfigComponent();
        $stripped = Str::afterLast($name, '.');

        $listeners = ['refreshList-' . $name => 'refreshList'];

        if ($name !== $stripped) {
            $listeners['refreshList-' . $stripped] = 'refreshList';
        }

        return $listeners;
    }

    /**
     * @see getListConfig()
     */
    private function resolveListConfig(?string $customName): array
    {
        $name = $customName ?? $this->listConfigComponent();

        // An active alternate view only applies to this component's own config,
        // never to an explicitly requested custom config. A view from another
        // app resolves via explicit-app lookup; the session app stays untouched.
        if ($customName === null && ($this->listView !== null || $this->listViewApp !== null)) {
            $viewName = $this->listView !== null ? "{$name}--{$this->listView}" : $name;
            $config = $this->listViewApp !== null
                ? StaticConfigHelper::getListConfigForApp($this->listViewApp, $viewName, $this->listModel ?? null)
                : StaticConfigHelper::getListConfig($viewName, $this->listModel ?? null);
            if ($config !== []) {
                return $config;
            }
            // The view's YAML disappeared mid-session — fall back to the default view.
            $this->listView = null;
            $this->listViewApp = null;
            $this->syncListViewParam();
        }

        return StaticConfigHelper::getListConfig($name, $this->listModel ?? null);
    }

    /**
     * @return array{relation: string, column: string, table: string}|null
     */
    private function resolveRelationColumnPath(string $field): ?array
    {
        $segments = explode('.', $field);
        $column = (string) array_pop($segments);

        if ($segments === [] || $column === '') {
            return null;
        }

        $model = new $this->resolvedModelClass();
        foreach ($segments as $segment) {
            $related = $this->resolveRelatedModel($model, $segment);
            if ($related === null) {
                return null;
            }
            $model = $related;
        }

        $table = $model->getTable();
        if (! Schema::hasTable($table) || ! self::tableHasColumn($table, $column)) {
            return null;
        }

        return [
            'relation' => implode('.', $segments),
            'column' => $column,
            'table' => $table,
        ];
    }

    /**
     * The related model behind one relation segment, or null when the method is
     * not a callable no-argument relation. Calling the method is unavoidable to
     * learn the related class, so it is guarded by reflection and try/catch.
     */
    private function resolveRelatedModel(Model $model, string $method): ?Model
    {
        if ($method === '' || ! method_exists($model, $method)) {
            return null;
        }

        try {
            $reflection = new ReflectionMethod($model, $method);

            if (! $reflection->isPublic() || $reflection->isStatic() || $reflection->getNumberOfRequiredParameters() > 0) {
                return null;
            }

            $relation = $model->{$method}();
        } catch (Throwable) {
            return null;
        }

        return $relation instanceof Relation ? $relation->getRelated() : null;
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
     * Set the active view state ($listViewApp + $listView) from a dropdown key —
     * plain ('vip') or composite ('gastro::vip').
     */
    private function applyListViewKey(string $key): void
    {
        [$app, $viewKey] = StaticConfigHelper::parseListViewKey($key);
        $this->listViewApp = $app;
        $this->listView = $viewKey === 'default' ? null : $viewKey;
    }

    /**
     * Default sorting is configured in the list YAML — never in the component:
     *
     *   defaultSort:
     *     field: name
     *     direction: asc   # optional, desc when omitted
     *
     * Applied only while the user has not sorted the list themselves (no
     * session entry). Without the key, lists sort by id descending.
     */
    private function applyDefaultSortFromConfig(): void
    {
        $defaultSort = $this->getListConfig()['defaultSort'] ?? null;

        if (! is_array($defaultSort) || empty($defaultSort['field'])) {
            return;
        }

        $this->sortField = (string) $defaultSort['field'];
        $this->sortAsc = ($defaultSort['direction'] ?? 'desc') === 'asc';
    }

    /**
     * Remember the sort per component, so a reload restores it. Shared by
     * sortBy() and setSortDirection().
     */
    private function persistListSort(): void
    {
        session(["listSort.{$this->componentName()}" => [
            'field' => $this->sortField,
            'asc' => $this->sortAsc,
        ]]);
    }

    /**
     * Whether listData() is the trait's own implementation. A component that
     * overrides it may narrow the result set in ways listQuery() cannot see.
     */
    private function usesTraitListData(): bool
    {
        return (new ReflectionMethod($this, 'listData'))->getFileName()
            === (new ReflectionClass(NoerdList::class))->getFileName();
    }

    /**
     * Whether listAction() is the trait's own implementation. An overriding
     * component may mutate state its view shows, so only the trait's
     * dispatch-only version is safe to leave unrendered.
     */
    private function usesTraitListAction(): bool
    {
        return (new ReflectionMethod($this, 'listAction'))->getFileName()
            === (new ReflectionClass(NoerdList::class))->getFileName();
    }
}
