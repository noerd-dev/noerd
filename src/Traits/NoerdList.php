<?php

declare(strict_types=1);

namespace Noerd\Traits;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use LogicException;
use Noerd\Facades\Noerd;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Services\ComponentSlotsRegistry;
use Noerd\Services\ComputedColumnRegistry;
use Noerd\Services\RelationTitleResolver;
use Noerd\Support\ColumnFilterParser;
use Noerd\Support\ComponentNaming;
use Noerd\Support\ComputedFields;
use Noerd\Support\CsvListExporter;
use Noerd\Support\ListColumnIntrospection;
use Noerd\Support\ListFilter;
use Noerd\Support\ListPermissions;
use Noerd\Support\PicklistBadges;
use Noerd\Support\SchemaColumnCache;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\StreamedResponse;
use WeakMap;

trait NoerdList
{
    use NoerdComponentShared;
    use RoutedModal;
    use WithoutUrlPagination;
    use WithPagination;

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

    /*
     | A list DECLARES its query shape instead of overriding listData():
     |   protected array $listWith = ['category'];               relations eager-loaded for every page
     |   protected array $listWithCount = ['items'];             relation counts (`items_count`) per row
     |   protected array $listScopes = ['party_id' => 'partyId']; columns narrowed by a mount property
     | listQuery() reads them by name (declaredArray()). The trait declares none of
     | them: PHP refuses a class that redeclares a trait property with another default.
     */

    /** The model class behind this list, remembered from the last listQuery() call. */
    protected ?string $resolvedModelClass = null;

    /**
     * Custom list-config name handed to listQuery(), for lists whose YAML does not
     * follow the component-name convention (e.g. 'accounting-customers-list').
     * Search, column filters and the filterable-column whitelist all resolve
     * through it; null = the component's own config.
     */
    protected ?string $listQueryConfigName = null;

    /** @var array<int, string>|null Request cache for filterableColumnFields(). */
    protected ?array $filterableColumnCache = null;

    /** @var array<string, mixed>|null Last buildList() result, memoized per request. */
    protected ?array $builtListConfigCache = null;

    /** @var array<string, array<string, mixed>> Request memo for getListConfig() (incl. layout overrides). */
    protected array $listConfigMemo = [];

    /** @var array<string, ListFilter>|null Request memo for listFilterDefinitions(). */
    protected ?array $listFilterDefinitionCache = null;

    /** @var WeakMap<Builder, bool>|null The builders the header filters were applied to. */
    protected ?WeakMap $filteredBuilders = null;

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

    /**
     * The rows and the built config of this list — the ONE source every
     * consumer reads (see builtListConfig()). The trait's version queries the
     * declared $listModel; a list whose rows are not one model (a repository, a
     * raw query) overrides it and returns buildList($rows, $config) itself.
     */
    public function listData(): array
    {
        $modelClass = property_exists($this, 'listModel') ? $this->listModel : null;

        if (! is_string($modelClass) || $modelClass === '') {
            throw new LogicException(sprintf(
                '%s declares no $listModel: declare the model the list shows, or override listData() and return buildList($rows, $config).',
                static::class,
            ));
        }

        return $this->buildList($this->paginateList($this->listQuery($modelClass)));
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

        // A computed column is no table column: its provider decides whether
        // the query can be ordered by it.
        $computedColumn = $this->computedColumn($field);
        if ($computedColumn !== null && ! app(ComputedColumnRegistry::class)->isSortable($this->computedModelClass(), $computedColumn)) {
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
        // A filter with a default is cleared to the EMPTY selection, stored as
        // such — otherwise the next mount would put the default back.
        $this->listFilters = [];
        foreach ($this->listFilterDefinitions() as $column => $definition) {
            if ($definition->defaultValue() !== null) {
                $this->listFilters[$column] = '';
            }
        }
        session(['listFilters' => $this->listFilters]);

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
        } elseif ($this->isListColumnField($field)) {
            // Only a column the list shows can carry a filter: the field name is
            // client input and ends up in the filter chips.
            $this->listColumnFilters[$field] = $value;
        } else {
            return;
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
            ? ListColumnIntrospection::schemaTypes($model->getTable())
            : [];

        $chips = [];
        foreach ($active as $field => $raw) {
            // A ?cf[...] key arrives straight from the URL (and the session keeps
            // it): only a column of this list becomes a chip.
            if (! $columns->has($field)) {
                continue;
            }

            $column = $columns->get($field, []);
            $type = $column['type'] ?? $schemaTypes[$field] ?? null;
            $options = $column['options'] ?? $picklistOptions[$field] ?? [];

            $value = mb_trim($raw);
            $wantEmpty = ColumnFilterParser::emptiness($value);
            if ($wantEmpty !== null) {
                $value = $wantEmpty ? __('Empty') : __('Not empty');
            } elseif (in_array($type, ['bool', 'boolean', 'inversebool'], true)) {
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
     * Open a row by its POSITION in the current page — the keyboard path only
     * (arrow keys track a positional index, Enter submits it). Resolving the
     * position costs a full listData() round; mouse clicks know the model id
     * and go straight to openListRow().
     */
    public function findListAction(int|string $id): void
    {
        $listData = $this->builtListConfig()['rows'] ?? [];

        $item = ListColumnIntrospection::rows($listData)?->get($id);
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
        $this->builtListConfig();

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
        $this->resetPage();
    }

    /**
     * The header filters this list declares: the `filters:` entries of its list
     * YAML, then every `get{Name}ListFilter()` method of the component — each a
     * ListFilter or its array form. Override to show filters conditionally.
     *
     * @return array<int, ListFilter|array<string, mixed>>
     */
    #[Computed]
    public function tableFilters(): array
    {
        $filters = [];

        foreach ($this->getListConfig()['filters'] ?? [] as $filter) {
            if (is_array($filter) && ! empty($filter['column'])) {
                $filters[] = ListFilter::fromArray($filter, $this->listModel ?? null, translate: true);
            }
        }

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
            $this->perPage = $this->clampPerPage($this->minimalLimit);
        }
    }

    public function exportCsv(): StreamedResponse
    {
        // Only a list that switched the export on offers it.
        abort_unless($this->enableCsvExport, 403);

        // Populates resolvedModelClass so the read check sees the actual model.
        $this->builtListConfig();
        abort_unless(AccessHelper::canReadObject($this->objectPermissionModelClass()), 403);

        [$query, $columns, $filename] = $this->prepareCsvExport();

        $computedColumns = ComputedFields::columns($columns);
        if ($computedColumns !== []) {
            app(ComputedColumnRegistry::class)->prepareQuery($query, $computedColumns);
        }

        return CsvListExporter::stream(
            $query,
            $columns,
            $filename,
            prepareChunk: $computedColumns === [] ? null : fn($rows) => app(ComputedColumnRegistry::class)->fillRows($rows, $computedColumns),
            prepareRow: $this->prepareExportRow(...),
            format: $this->formatCsvValue(...),
        );
    }

    /**
     * The page of rows the list shows — `$perPage` is client-controlled, so the
     * size is clamped here, once, for every list that paginates its own query.
     */
    protected function paginateList(Builder $query): LengthAwarePaginator
    {
        return $query->paginate($this->clampPerPage($this->perPage));
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
    protected function isSortableColumn(string $field, array $notSortableColumns = []): bool
    {
        return $field !== 'action'
            && ! ListColumnIntrospection::isDottedField($field)
            && ! in_array($field, $notSortableColumns, true);
    }

    /**
     * The generic header controls this list actually renders, resolved ONCE so the
     * title row, the filter row and modal-title cannot disagree about what
     * exists. Every consumer reads this — never re-derive "does the list have a
     * search field / secondary action" from $listSettings at the call site.
     *
     * The action indices are preserved on purpose: splitting the YAML actions into
     * a primary and a secondary group must not renumber them, because the position
     * in the YAML is what assigns the keyboard shortcut.
     *
     * @return array{search: bool, csv: bool, secondary: array<int, array<string, mixed>>, primary: array<int, array<string, mixed>>, registry: array<int, string>}
     */
    protected function headerControls(): array
    {
        $config = $this->builtListConfig();
        $settings = $config['listSettings'] ?? [];

        // A picker is reduced to selecting rows: it offers neither the YAML actions
        // nor the module-contributed ones. A read-denied list keeps its header but
        // loses the data affordances (buildList() already stripped the actions).
        $isPicker = $this->returnsSelection;
        $actions = $isPicker ? [] : ($settings['actions'] ?? []);

        return [
            'search' => ! ($settings['disableSearch'] ?? false) && ! ($config['objectAccessDenied'] ?? false),
            'csv' => $this->enableCsvExport,
            'secondary' => array_filter($actions, static fn(array $action): bool => ($action['style'] ?? '') === 'secondary'),
            'primary' => array_filter($actions, static fn(array $action): bool => ($action['style'] ?? '') !== 'secondary'),
            'registry' => $isPicker ? [] : app(ComponentSlotsRegistry::class)->get(ComponentSlotsRegistry::LIST_HEADER_ACTIONS),
        ];
    }

    /**
     * Whether the list has a search field, a CSV export or `style: secondary`
     * actions — the controls a custom header slot gets injected next to the
     * primary buttons and the registry actions.
     */
    protected function hasCollapsibleControls(): bool
    {
        $controls = $this->headerControls();

        return $controls['search'] || $controls['csv'] || $controls['secondary'] !== [];
    }

    /**
     * Whether the header renders any generic control at all — the question
     * modal-title asks before it injects them into a custom header slot.
     */
    protected function hasHeaderControls(): bool
    {
        $controls = $this->headerControls();

        return $this->hasCollapsibleControls() || $controls['primary'] !== [] || $controls['registry'] !== [];
    }

    /**
     * Numeric row ids stay ints (the storage type of every model-backed list);
     * non-numeric ids (composite string ids of manual-row lists) stay strings, so
     * strict comparisons work for both.
     */
    protected function normalizeRecordId(mixed $id): int|string
    {
        return is_numeric($id) ? (int) $id : (string) $id;
    }

    /**
     * Ids of the rows currently rendered (current page).
     *
     * @return array<int, int|string>
     */
    protected function visibleRowIds(): array
    {
        $collection = ListColumnIntrospection::rows($this->builtListConfig()['rows'] ?? null);
        if ($collection === null) {
            return [];
        }

        return $collection
            ->map(fn($row): int|string => $this->normalizeRecordId(is_array($row) ? ($row['id'] ?? 0) : $row->id))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The listData() result, computed at most once per request. The header
     * chrome, the list body and the generic actions (row click, select-all,
     * bulk delete) all read it — without the memo each of them would run the
     * paginated query again. Mutating actions (deleteSelected) clear it so the
     * re-render queries fresh rows.
     *
     * @return array<string, mixed>
     */
    protected function builtListConfig(): array
    {
        return $this->builtListConfigCache ??= $this->listData();
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

        // Clamped: a session value stored by an earlier per-page option set (or by
        // hand) must never exceed MAX_PER_PAGE, or the footer select has no match.
        $this->perPage = $this->clampPerPage((int) session("listPerPage.{$this->componentName()}", 50));
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
            // '%3A%3A' noise); decode it back to the composite key.
            $urlView = str_contains($urlView, '::')
                ? ''
                : Str::replaceFirst('--', '::', $urlView);
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
        // updates POST to /livewire/update and carry no page query. A list
        // needs no rendering() hook of its own for this. The id keeps its
        // shape (an integer key stays an int, a ULID stays a string).
        $deepLinkId = request()->query($this->getDeepLinkParam());
        if (is_scalar($deepLinkId) && mb_trim((string) $deepLinkId) !== '') {
            $this->listAction($this->normalizeRecordId(mb_trim((string) $deepLinkId)));
        } elseif (request()->query('create')) {
            $this->listAction();
        }
    }

    /**
     * The header filters live in ONE session bucket shared across lists ON
     * PURPOSE: NoerdPage::setPreselect() seeds it from detail pages so a
     * related list opens pre-filtered, and preselect() reads it back. Only
     * columns whitelisted per list (getAllowedListFilterColumns) ever apply.
     * A filter whose selection was never stored starts on its declared default.
     */
    protected function loadListFilters(): void
    {
        $this->listFilters = session('listFilters', []);

        // A header filter holds ONE value. The bucket may still carry an array
        // for a column of this list (a multi-select of an earlier version, a
        // seeded selection) — reduced to its first scalar here, so the select
        // binds a value it can show; other lists' keys stay as they are.
        foreach ($this->getAllowedListFilterColumns() as $column) {
            if (array_key_exists($column, $this->listFilters) && is_array($this->listFilters[$column])) {
                $this->listFilters[$column] = $this->scalarListFilterValue($this->listFilters[$column]) ?? '';
            }
        }

        foreach ($this->listFilterDefinitions() as $column => $definition) {
            if ($definition->defaultValue() !== null && ! array_key_exists($column, $this->listFilters)) {
                $this->listFilters[$column] = $definition->defaultValue();
            }
        }
    }

    /**
     * The declared header filters keyed by column, every array form turned into
     * a ListFilter. Memoized per request — the options may query the database.
     *
     * @return array<string, ListFilter>
     */
    protected function listFilterDefinitions(): array
    {
        if ($this->listFilterDefinitionCache !== null) {
            return $this->listFilterDefinitionCache;
        }

        $definitions = [];
        foreach ($this->tableFilters as $filter) {
            $definition = match (true) {
                $filter instanceof ListFilter => $filter,
                is_array($filter) && ! empty($filter['column']) => ListFilter::fromArray($filter),
                default => null,
            };

            if ($definition !== null) {
                $definitions[$definition->column] = $definition->forModel($this->listModel ?? $this->resolvedModelClass);
            }
        }

        return $this->listFilterDefinitionCache = $definitions;
    }

    /**
     * The header filters in the array form the filter row renders.
     *
     * @return array<int, array{label: string, column: string, type: string, options: array<string, string>}>
     */
    protected function headerFilters(): array
    {
        return array_values(array_map(
            static fn(ListFilter $definition): array => $definition->toArray(),
            $this->listFilterDefinitions(),
        ));
    }

    /**
     * The methods a row click may run through $listActionMethod. A component
     * with a row action of its own adds it here.
     *
     * @return list<string>
     */
    protected function rowActionMethods(): array
    {
        return ['listAction', 'selectAction'];
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
     * Columns the header filters (listFilters) may constrain — every declared
     * filter column. Override to allow additional keys (a value seeded through
     * setPreselect() that no dropdown declares); such a key constrains the
     * column of that name by equality.
     *
     * @return array<int, string>
     */
    protected function getAllowedListFilterColumns(): array
    {
        return array_keys($this->listFilterDefinitions());
    }

    /**
     * Put the active header filters on the query — listQuery() does this for
     * every list, exactly once per builder. A declared filter applies as its
     * ListFilter says (equality on a real column, the date comparison of the
     * date kinds, a virtual column through its closure); an extra whitelisted
     * key constrains the column of that name when the table has it.
     */
    protected function applyListFilters(Builder $query): void
    {
        $this->filteredBuilders ??= new WeakMap();
        if (isset($this->filteredBuilders[$query])) {
            return;
        }
        $this->filteredBuilders[$query] = true;

        if ($this->listFilters === []) {
            return;
        }

        $definitions = $this->listFilterDefinitions();
        $allowed = array_map('strval', $this->getAllowedListFilterColumns());
        $table = $query->getModel()->getTable();

        foreach ($this->listFilters as $column => $value) {
            $column = (string) $column;
            $value = $this->scalarListFilterValue($value);
            if ($value === null || $value === '') {
                continue;
            }

            if (isset($definitions[$column])) {
                $definitions[$column]->applyTo($query, $value);
            } elseif (in_array($column, $allowed, true) && SchemaColumnCache::hasColumn($table, $column)) {
                $query->where($query->qualifyColumn($column), $value);
            }
        }
    }

    /*
     | The naming hooks below derive their value from the component name;
     | override the method for a component that does not follow the convention.
     */

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
     * The select event and the deep link parameter derive from it.
     */
    protected function getListEntity(): string
    {
        return $this->deriveListEntity();
    }

    protected function dispatchSelectionEvents(mixed $modelId = null): void
    {
        $this->dispatch('noerdRelationSelected', $modelId, $this->context);
        $this->dispatch($this->getSelectEvent(), $modelId, $this->context);
        $this->dispatch('closeTopModal');
    }

    /**
     * Build a query with search, sort, the Excel-style column filters and the
     * header filters applied based on the YAML. Pass $configName when the list
     * renders a custom YAML config (the same name handed to buildList()).
     */
    protected function listQuery(string $modelClass, ?string $configName = null): Builder
    {
        if ($this->resolvedModelClass !== $modelClass || $this->listQueryConfigName !== $configName) {
            $this->filterableColumnCache = null;
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

            $validFields = array_filter($searchableFields, fn($f) => is_string($f) && SchemaColumnCache::hasColumn($table, $f));

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
        $this->applyListFilters($query);
        $this->applyListScopes($query);

        $eagerLoads = ListColumnIntrospection::eagerLoads($modelClass, $this->getListConfig($configName)['columns'] ?? []);
        if ($eagerLoads !== []) {
            $query->with($eagerLoads);
        }
        if ($this->declaredArray('listWith') !== []) {
            $query->with($this->declaredArray('listWith'));
        }
        if ($this->declaredArray('listWithCount') !== []) {
            $query->withCount($this->declaredArray('listWithCount'));
        }

        $computedColumns = ComputedFields::columns($listConfig['columns'] ?? []);
        if ($computedColumns !== []) {
            app(ComputedColumnRegistry::class)->prepareQuery($query, $computedColumns);
        }

        // A sortable computed column orders through its provider (e.g. by an
        // aggregate subselect); the client-writable $sortField is re-checked here.
        $sortComputedColumn = $this->computedColumn($this->sortField);
        if ($sortComputedColumn !== null && app(ComputedColumnRegistry::class)->isSortable($this->computedModelClass(), $sortComputedColumn)) {
            app(ComputedColumnRegistry::class)->get($sortComputedColumn)?->applyOrder($query, $sortComputedColumn, $this->sortAsc ? 'asc' : 'desc');

            return $query;
        }

        // $sortField is client-writable (sortBy() enforces isSortableColumn(),
        // a raw property update does not). Ordering by a column the model hides
        // — password, remember_token, api_token — turns the list into an
        // oracle over that value, so hidden attributes are never sortable.
        // Only a column the list shows (or its default sort, or the technical
        // `id`) orders the rows — sorting by an unshown column would rank the
        // rows by a value the reader is not meant to see.
        $hidden = $this->resolvedModelInstance()?->getHidden() ?? [];
        $sortable = array_merge(
            ['id', (string) ($listConfig['defaultSort']['field'] ?? '')],
            array_column(array_filter($listConfig['columns'] ?? [], 'is_array'), 'field'),
        );
        $sortField = SchemaColumnCache::hasColumn($table, $this->sortField)
            && ! in_array($this->sortField, $hidden, true)
            && in_array($this->sortField, $sortable, true)
                ? $this->sortField
                : 'id';
        $query->orderBy($sortField, $this->sortAsc ? 'asc' : 'desc');

        return $query;
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

        // With zero rows (e.g. a column filter matching nothing) no model can be
        // pulled from the result set — fall back to the class listQuery() resolved,
        // so bool/date columns keep their type and the filter popover keeps its UI.
        $listSettings = ListColumnIntrospection::applyAutoTypes(
            $listSettings,
            ListColumnIntrospection::firstModel($rows) ?? $this->resolvedModelInstance(),
        );
        $listSettings = PicklistBadges::apply($listSettings, $this->picklistOptionsFromDetail());
        $this->fillComputedColumns($listSettings['columns'] ?? [], $rows);
        app(RelationTitleResolver::class)->primeColumns($listSettings['columns'] ?? [], ListColumnIntrospection::rows($rows));

        // Object permissions: strip the affordances the current user may not use.
        // In-memory lists (no model class) stay unrestricted.
        $permissionModel = $this->objectPermissionModelClass();
        $objectAccessDenied = ! AccessHelper::canReadObject($permissionModel);
        $listSettings = ListPermissions::strip($listSettings, $permissionModel);

        // Computed columns their provider cannot order by render without a sort
        // button — isSortableColumn() reads them from notSortableColumns.
        $notSortableColumns = $listSettings['notSortableColumns'] ?? [];
        foreach (ComputedFields::columns($listSettings['columns'] ?? []) as $computedColumn) {
            if (! app(ComputedColumnRegistry::class)->isSortable($this->computedModelClass(), $computedColumn)) {
                $notSortableColumns[] = $computedColumn['field'];
            }
        }

        // A preview is a picture, there is nothing to order by.
        foreach ($listSettings['columns'] ?? [] as $column) {
            if (($column['type'] ?? null) === 'preview' && is_string($column['field'] ?? null)) {
                $notSortableColumns[] = $column['field'];
            }
        }

        return $this->builtListConfigCache = [
            'listId' => $this->listId,
            'sortField' => $this->sortField,
            'sortAsc' => $this->sortAsc,
            'notSortableColumns' => array_values(array_unique($notSortableColumns)),
            'rows' => $rows,
            'listSettings' => $listSettings,
            'listColumnFilters' => $this->listColumnFilters,
            'filterableColumns' => $this->filterableColumnFields(),
            'objectAccessDenied' => $objectAccessDenied,
        ];
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

    /**
     * One CSV cell (CsvListExporter::formatValue()); override to change the
     * notation of a column.
     *
     * @param  array<string, mixed>  $column
     */
    protected function formatCsvValue(mixed $value, array $column): string
    {
        return CsvListExporter::formatValue($value, $column);
    }

    /**
     * The refreshList listener under the config name (and the name after the
     * last dot). Same override contract as NoerdPage::getListeners(): merge,
     * never replace.
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
     * The one value a header filter compares with: a scalar as string, an array
     * (an older multi-select, a seeded list) as its first non-empty scalar,
     * nothing for null, false, an empty array or an object.
     */
    private function scalarListFilterValue(mixed $value): ?string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $scalar = $this->scalarListFilterValue($item);
                if ($scalar !== null && $scalar !== '') {
                    return $scalar;
                }
            }

            return null;
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if ($value === null || $value === false || ! is_scalar($value)) {
            return null;
        }

        return (string) $value;
    }

    /**
     * One of the query-shape arrays a list declares ($listWith, $listWithCount,
     * $listScopes) — empty when the component declares none.
     *
     * @return array<int|string, mixed>
     */
    private function declaredArray(string $property): array
    {
        return property_exists($this, $property) && is_array($this->{$property}) ? $this->{$property} : [];
    }

    /**
     * Narrow the query by the declared $listScopes: every column whose mount
     * property holds a value (null and '' leave the list unnarrowed).
     */
    private function applyListScopes(Builder $query): void
    {
        foreach ($this->declaredArray('listScopes') as $column => $property) {
            $value = property_exists($this, $property) ? $this->{$property} : null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            is_array($value)
                ? $query->whereIn($query->qualifyColumn($column), $value)
                : $query->where($query->qualifyColumn($column), $value);
        }
    }

    /**
     * Whether $field is a column of the active list configuration.
     */
    private function isListColumnField(string $field): bool
    {
        if ($field === 'action') {
            return false;
        }

        return collect($this->getListConfig($this->listQueryConfigName)['columns'] ?? [])
            ->contains(fn($column): bool => is_array($column) && ($column['field'] ?? null) === $field);
    }

    /**
     * Dispatch a row click to the configured listActionMethod. The method name
     * is a mount argument (pickers pass `selectAction`), so it is resolved by
     * name — but only PUBLIC methods are Livewire actions, and only those may
     * be reached this way. Anything else is ignored instead of exposing the
     * component's protected internals to a client-chosen name.
     */
    private function callRowAction(int|string $modelId): void
    {
        $method = $this->listActionMethod;

        // $listActionMethod arrives as a mount argument (the picker passes
        // selectAction): only a declared row action may run, never an arbitrary
        // public method or one of Livewire's own.
        if (! in_array($method, $this->rowActionMethods(), true)
            || ! method_exists($this, $method)
            || ! (new ReflectionMethod($this, $method))->isPublic()) {
            return;
        }

        $this->{$method}($modelId);
    }

    /**
     * Whether the active list config declares a bulk action running the given
     * method — the authorization for invoking that bulk action server-side.
     */
    private function listDeclaresBulkAction(string $action): bool
    {
        foreach ($this->getListConfig($this->listQueryConfigName)['bulkActions'] ?? [] as $bulkAction) {
            if (($bulkAction['action'] ?? null) === $action) {
                return true;
            }
        }

        return false;
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
    private function filterableColumnFields(): array
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
            ->filter(function ($field) use ($table): bool {
                if (! is_string($field) || $field === 'action') {
                    return false;
                }

                $computedColumn = $this->computedColumn($field);
                if ($computedColumn !== null) {
                    return app(ComputedColumnRegistry::class)->isFilterable($this->computedModelClass(), $computedColumn);
                }

                return ListColumnIntrospection::isDottedField($field)
                    ? ($this->isJsonColumnPath($field) || $this->relationColumnPath($field) !== null)
                    : SchemaColumnCache::hasColumn($table, $field);
            })
            ->values()
            ->all();
    }

    /**
     * Apply every active Excel-style column filter (AND-combined, stacking with
     * search and the header listFilters). Skipped for compact/minimal embedded
     * lists, which render no filter UI — a session-stored filter must not
     * invisibly hide their rows.
     */
    private function applyColumnFilters(Builder $query, string $modelClass): void
    {
        if ($this->listColumnFilters === [] || $this->compact || $this->minimal) {
            return;
        }

        $allowed = $this->filterableColumnFields();
        if ($allowed === []) {
            return;
        }

        $table = $this->resolvedModelInstance()->getTable();
        $schemaTypes = ListColumnIntrospection::schemaTypes($table);
        $yamlTypes = collect($this->getListConfig($this->listQueryConfigName)['columns'] ?? [])
            ->filter(fn($column): bool => isset($column['field'], $column['type']))
            ->pluck('type', 'field')
            ->toArray();
        $picklistFields = array_keys($this->picklistOptionsFromDetail());

        foreach ($this->listColumnFilters as $field => $raw) {
            if (! in_array($field, $allowed, true) || ! is_string($raw) || mb_trim($raw) === '') {
                continue;
            }

            // filterableColumnFields() admitted it, so its provider can filter it.
            $computedColumn = $this->computedColumn($field);
            if ($computedColumn !== null) {
                app(ComputedColumnRegistry::class)->get($computedColumn)?->applyFilter($query, $computedColumn, (string) ($computedColumn['type'] ?? 'text'), $raw);

                continue;
            }

            // A relation path (`defaultDeliveryAddress.locality`) filters on the
            // related table; JSON paths take precedence and use the arrow operator.
            $relationPath = ListColumnIntrospection::isDottedField($field) && ! $this->isJsonColumnPath($field)
                ? $this->relationColumnPath($field)
                : null;
            if ($relationPath !== null) {
                $relationType = $yamlTypes[$field]
                    ?? ListColumnIntrospection::schemaTypes($relationPath['table'])[$relationPath['column']]
                    ?? 'text';

                ColumnFilterParser::applyRelation($query, $relationPath['relation'], $relationPath['column'], $relationType, $raw);

                continue;
            }

            // The type the header cell renders with (buildList()): an explicit YAML
            // type, else the schema type, else a badge for a detail-picklist column.
            $type = $yamlTypes[$field] ?? $schemaTypes[$field] ?? null;
            if (($type === null || $type === 'text') && in_array($field, $picklistFields, true)) {
                $type = 'badge';
            }

            // A JSON path (custom_attributes.x) filters via the arrow operator on
            // its base column; it has no schema type, so YAML/picklist typing
            // applies with text as the fallback.
            $column = ListColumnIntrospection::isDottedField($field) ? str_replace('.', '->', $field) : $field;

            ColumnFilterParser::apply($query, $column, $type ?? 'text', $raw);
        }
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
    private function objectPermissionModelClass(): ?string
    {
        if (property_exists($this, 'objectPermissionModel') && $this->objectPermissionModel !== null) {
            return $this->objectPermissionModel;
        }

        return $this->resolvedModelClass
            ?? (property_exists($this, 'listModel') ? $this->listModel : null);
    }

    /**
     * Map of `field => options` for every select field of this list's paired
     * detail YAML — the source of the automatic picklist badges.
     *
     * @return array<string, array<int, array{value: mixed, label: string}>>
     */
    private function picklistOptionsFromDetail(): array
    {
        $detailComponent = $this->pairedDetailComponent();

        return $detailComponent === null ? [] : PicklistBadges::optionsFromDetail($detailComponent);
    }

    /**
     * The detail whose YAML options become the list's picklist badges: the
     * declared $detailComponent when it is a detail, else the one paired by name.
     */
    private function pairedDetailComponent(): ?string
    {
        $declared = property_exists($this, 'detailComponent') ? $this->detailComponent : null;

        if (is_string($declared) && Str::endsWith($declared, '-detail')) {
            return $declared;
        }

        return ComponentNaming::pairedDetail($this->componentName());
    }

    /**
     * The computed column (a column a ComputedColumnRegistry provider handles,
     * e.g. one with a `method:` key) of the active list config for a field, or
     * null when the field is none.
     *
     * @return array<string, mixed>|null
     */
    private function computedColumn(?string $field): ?array
    {
        if ($field === null || $field === '') {
            return null;
        }

        foreach (ComputedFields::columns($this->getListConfig($this->listQueryConfigName)['columns'] ?? []) as $column) {
            if ($column['field'] === $field) {
                return $column;
            }
        }

        return null;
    }

    /**
     * The model class computed columns are evaluated against — the class the
     * last listQuery() resolved, else the declared $listModel or
     * $objectPermissionModel (sortBy() runs in a request before any query was built).
     */
    private function computedModelClass(): ?string
    {
        if ($this->resolvedModelClass !== null) {
            return $this->resolvedModelClass;
        }

        foreach (['listModel', 'objectPermissionModel'] as $property) {
            if (property_exists($this, $property) && is_string($this->{$property})) {
                return $this->{$property};
            }
        }

        return null;
    }

    /**
     * Compute the computed columns of the current page once for all rows, so
     * every cell reads its value with data_get() like a stored attribute.
     *
     * @param  array<int, mixed>  $columns
     */
    private function fillComputedColumns(array $columns, mixed $rows): void
    {
        $computedColumns = ComputedFields::columns($columns);
        if ($computedColumns === []) {
            return;
        }

        // Hand-built array rows carry no models a provider could fill.
        $collection = is_array($rows) ? null : ListColumnIntrospection::rows($rows);

        if ($collection !== null) {
            app(ComputedColumnRegistry::class)->fillRows($collection, $computedColumns);
        }
    }

    /**
     * One shared instance of the class listQuery() resolved, for table-name and
     * cast lookups; null before the first listQuery() call.
     */
    private function resolvedModelInstance(): ?Model
    {
        return $this->resolvedModelClass === null ? null : ListColumnIntrospection::instance($this->resolvedModelClass);
    }

    private function isJsonColumnPath(string $field): bool
    {
        return $this->resolvedModelClass !== null && ListColumnIntrospection::isJsonPath($this->resolvedModelClass, $field);
    }

    /**
     * @return array{relation: string, column: string, table: string}|null
     */
    private function relationColumnPath(string $field): ?array
    {
        return $this->resolvedModelClass === null ? null : ListColumnIntrospection::relationPath($this->resolvedModelClass, $field);
    }

    private function deriveListEntity(): string
    {
        return ComponentNaming::listEntity($this->componentName())
            ?? Str::singular(ComponentNaming::bareName($this->componentName()));
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
