<?php

declare(strict_types=1);

namespace Noerd\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Services\PositionTableRegistry;
use Noerd\Services\RelationTitleResolver;
use Noerd\Support\ComponentNaming;
use Noerd\Support\DetailHeaderActions;
use Noerd\Support\Positions\PositionColumn;
use Noerd\Support\Positions\PositionColumnResolver;
use Noerd\Support\ThemeContext;
use Noerd\Support\ThemeHelper;
use RuntimeException;

/**
 * Base trait for `*-page` components: page chrome (tabs, modal lifecycle,
 * quick-create), the optional page YAML (`pages/{name}.yml`) and the generic
 * store roundtrip with an embedded `*-detail` component. NoerdDetail composes
 * this trait — a detail is a page plus the model-form concerns.
 */
trait NoerdPage
{
    use NoerdComponentShared;
    use RoutedModal;

    public bool $showSuccessIndicator = false;

    /** The record id; `'new'` opens an empty record (see RoutedModal). */
    public int|string|null $modelId = null;

    #[Url(as: 'tab', keep: false, except: 1)]
    public int $currentTab = 1;

    public array $pageLayout = [];

    /**
     * Set when the component is rendered inside a hosting page component (e.g.
     * account-page embeds account-detail). The x-noerd::page chrome (header,
     * footer, scroll wrapper) is skipped automatically — the hosting page owns it.
     */
    public bool $embedded = false;

    /**
     * The Livewire id of the page an embedded detail belongs to, set by
     * EmbeddedDetailHostHook. The store roundtrip events carry it, so a detail
     * only answers its own page and a page only adopts its own detail's record.
     */
    #[Locked]
    public ?string $hostId = null;

    public bool $quickCreate = false;

    public array $detailData = [];

    /**
     * Set on mount when the current user may not read the object behind this
     * page/detail (see the noerd.object-read gate / AccessHelper). The page chrome then renders a
     * friendly denied state instead of the form, and no record data is loaded.
     */
    public bool $objectReadBlocked = false;

    /*
     | A component may declare `protected array $detailWith = ['party', 'positions'];`
     | — the relations loaded with the record, so the form, detailLoaded() and the
     | relation forms read them without a query each. The trait declares no such
     | property itself: PHP refuses a class redeclaring a trait property with
     | another default (loadDetailModel() reads it by name).
     */

    /**
     * The theme that was active when this component started rendering, restored
     * afterwards so the theme never outlives the render (see renderedNoerdPage).
     */
    protected ?string $themeContextBefore = null;

    /**
     * Livewire trait mount hook (runs for every page/detail). The active tab is
     * shared across all components via the `tab` URL param, so a stale tab (e.g.
     * the lead activity-log tab 2) would otherwise bleed into the next component.
     * Only keep the carried-over tab when the previously opened component was the
     * SAME type (e.g. lead → another lead); a different type starts on tab 1.
     */
    public function mountNoerdPage(): void
    {
        $this->assertDetailPrimaryDeclared();

        // Embedded children (a detail rendered inside a hosting page component) own no
        // tabs — leave the tab session/URL state to the hosting page.
        if ($this->embedded) {
            return;
        }

        // READ ONLY. The matching write happens when the component renders —
        // mount() re-runs on every modal-stack update (@teleport re-mounts
        // Livewire children), so it must stay free of side effects.
        if (session('noerd.lastDetailComponent') !== $this->componentName()) {
            $this->currentTab = 1;
        }
    }

    /**
     * Trait-level query string (auto-collected by Livewire's SupportQueryString):
     * binds $modelId to the component's public URL alias declared in
     * `public ?string $detailPrimary = '{entity}Id';` (e.g. 'customerId' →
     * ?customerId=5). Like $detailModel, the property is deliberately NOT
     * declared on the trait — PHP forbids redeclaring a trait property with a
     * different default. It is MANDATORY for model-backed *-detail components
     * and must be a literal property default (never assigned in mount()): the
     * modal system probes a fresh instance to collect the params to clear on
     * close. Embedded children never bind — the hosting page owns the URL
     * parameter, and a stale query param must not override the mount-passed
     * modelId. Must stay PUBLIC: noerd-modal's resolveUrlParameters() discovers
     * it via get_class_methods().
     */
    public function queryStringNoerdPage(): array
    {
        $detailPrimary = $this->detailPrimary ?? null;

        if ($this->embedded || ! $detailPrimary) {
            return [];
        }

        return [
            'modelId' => ['as' => $detailPrimary, 'keep' => false, 'except' => ''],
        ];
    }

    public function mount(): void
    {
        $this->initPage();
    }

    public function closeModalProcess(?string $source = null): void
    {
        $this->currentTab = 1;

        $this->dispatch('closeTopModal');
        if ($source) {
            $this->dispatch('refreshList-' . Str::afterLast($source, '.'));
        }
    }

    /**
     * Page default: forward the save to the embedded detail declared in the page
     * YAML (`detail:`). The browser roundtrip flushes the detail's deferred
     * wire:model state; the detail persists behind its own guard and reports back
     * via `detailStored-{detail}`. NoerdDetail overrides this with the model store.
     */
    public function store(): void
    {
        if ($detail = $this->embeddedDetailComponent()) {
            $this->dispatch('storeDetail-' . $detail, host: $this->getId());
        }
    }

    public function delete(): void
    {
        // Server-side guard: the delete button is hidden for delete-denied users,
        // but the method stays reachable via keyboard shortcut and direct calls.
        if (! $this->canDeleteObject() || ! isset($this->detailModel)) {
            return;
        }

        $modelClass = $this->detailModel;
        $modelClass::find($this->modelId)?->delete();

        $this->closeModalProcess($this->getListComponent());
    }

    /**
     * Object permission checks for the model behind this page/detail. Public on
     * purpose: the shared chrome (delete-save-bar, page shortcuts) consults them
     * to hide Save/Delete affordances the current user may not use. Components
     * without $detailModel are never restricted.
     */
    public function canReadObject(): bool
    {
        return AccessHelper::canReadObject($this->detailModel ?? null);
    }

    public function canWriteObject(): bool
    {
        return AccessHelper::canWriteObject($this->detailModel ?? null);
    }

    public function canCreateObject(): bool
    {
        return AccessHelper::canCreateObject($this->detailModel ?? null);
    }

    /**
     * A component whose records are never deleted from its screen declares
     * `protected bool $deletable = false;` — the delete button, the delete
     * shortcut and delete() all ask this method, so the flag closes every path
     * whatever the object permissions say. Like $detailModel, the property is
     * deliberately NOT declared on the trait (PHP forbids redeclaring a trait
     * property with a different default).
     */
    public function canDeleteObject(): bool
    {
        if (($this->deletable ?? true) === false) {
            return false;
        }

        return AccessHelper::canDeleteObject($this->detailModel ?? null);
    }

    /**
     * The ability governing store() in the form's CURRENT state: persisting a
     * new record (no id yet) is create, updating an existing one is write. The
     * chrome (save button, save shortcut, readonly fields) keys off this.
     */
    public function canSaveObject(): bool
    {
        return $this->modelId ? $this->canWriteObject() : $this->canCreateObject();
    }

    /**
     * Livewire trait rendering hook: expose the active theme to chrome
     * components outside the YAML field grid (x-noerd::button above all).
     */
    public function renderingNoerdPage(): void
    {
        // Remember which component type was rendered last, so the next one of a
        // DIFFERENT type starts on tab 1 (see mountNoerdPage()).
        if (! $this->embedded) {
            session(['noerd.lastDetailComponent' => $this->componentName()]);
        }

        // The detail header actions mount once per render, in the first form block.
        DetailHeaderActions::reset($this->getId());

        $this->themeContextBefore = ThemeContext::current();

        ThemeContext::set($this->detailTheme());
    }

    /**
     * Livewire trait rendered hook: restore the theme that was active before this
     * component rendered. Nesting is preserved — an embedded detail hands the
     * context back to its hosting page, whose footer still renders in the page
     * theme — while chrome rendered AFTER the page (the layout's app bar and
     * quick-menu buttons) falls back to the default theme instead of inheriting
     * a form theme it never belonged to.
     */
    public function renderedNoerdPage(): void
    {
        ThemeContext::set($this->themeContextBefore);

        // Release the header-action claim again — a long-lived process (Octane)
        // must not keep one entry per component it ever rendered.
        DetailHeaderActions::reset($this->getId());
    }

    /**
     * The embedded detail persisted its record: adopt the id, refresh the page's
     * model snapshot and run the shared post-store chrome (success indicator,
     * quick-create exit). Pages hook extra persistence via afterEmbeddedDetailStored().
     */
    public function embeddedDetailStored(int $modelId, ?string $host = null): void
    {
        // Another page's detail of the same name reported (a second instance in a modal).
        if ($host !== null && $host !== $this->getId()) {
            return;
        }

        // A listener is callable by the client: only adopt a record this user may
        // save, and never repoint a page that already shows a record — the
        // embedded detail of that page reports the same id.
        if (! $this->canSaveObject()) {
            return;
        }

        if ($this->modelId !== null && $this->modelId !== '' && $this->modelId !== 'new'
            && (string) $this->modelId !== (string) $modelId) {
            return;
        }

        $this->modelId = $modelId;

        if (isset($this->detailModel)) {
            $modelClass = $this->detailModel;
            $model = $modelClass::find($modelId);

            if ($model) {
                // Merge instead of replace: page-owned keys in $detailData that are
                // not model columns (e.g. product groups edited on the page) must
                // survive the refresh — afterEmbeddedDetailStored() persists them.
                $this->detailData = array_merge(
                    $this->detailData,
                    collect($model->toArray())->except(['created_at', 'updated_at'])->toArray(),
                );

                $this->storeProcess($model);
                $this->afterEmbeddedDetailStored($model);
            }
        }
    }

    /**
     * Live sync from the embedded detail (`detailDataUpdated-{detail}`): keeps the
     * page's $detailData mirror current, e.g. for a live preview.
     */
    public function embeddedDetailDataUpdated(array $detailData, ?string $host = null): void
    {
        if ($host !== null && $host !== $this->getId()) {
            return;
        }

        $this->detailData = array_merge($this->detailData, $detailData);
    }

    /**
     * The embedded detail component declared in the page YAML (`detail:`). Its
     * record is the page's own ($detailModel, $modelId). Deliberately no fallback
     * to the component's own name — a standalone detail must never grow page
     * listeners. Like the rest of $pageLayout it is client-writable; the guards
     * stay on the detail's own store().
     */
    protected function embeddedDetailComponent(): ?string
    {
        $detail = $this->pageLayout['detail'] ?? null;

        return is_string($detail) && $detail !== '' ? $detail : null;
    }

    /**
     * The active theme (default, compact, numbered, …). Blades pass it to
     * hand-written chrome that lives outside the YAML field grid — position
     * tables above all — so those follow the form's theme:
     * `<livewire:module::position :theme="$this->detailTheme()" />`.
     */
    protected function detailTheme(): string
    {
        return ThemeHelper::fromLayout($this->pageLayout);
    }

    /**
     * The resolved columns of a position (line item) table: the module's
     * catalog merged with the `positions.columns` block of this component's
     * layout. Returns plain arrays, ready to travel as a Livewire prop. Hand
     * the result to `<x-noerd::positions.table :columns>` AND to every row
     * component (`NoerdPositionRow`):
     *
     * `@php $columns = $this->positionColumns(); @endphp`
     *
     * The catalog and position model come from the PositionTableRegistry entry
     * of this component.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function positionColumns(): array
    {
        $table = app(PositionTableRegistry::class)->get($this->componentName());

        if ($table === null) {
            throw new RuntimeException("No position table is registered for [{$this->componentName()}] in the PositionTableRegistry.");
        }

        $catalog = is_string($table['catalog']) ? app($table['catalog']) : $table['catalog'];

        return array_map(
            fn(PositionColumn $column): array => $column->toArray(),
            app(PositionColumnResolver::class)->resolve($catalog, $table['model'], $this->pageLayout),
        );
    }

    /**
     * Form paths a hand-built input binds that no layout field declares, as full
     * field names (`detailData.notes`, `detailData.custom_attributes.sap_number`).
     * The client may only change what the layout or this list declares: the
     * default store() persists these keys too, and a nested path opens exactly
     * that key below a nested root such as `custom_attributes`.
     *
     * @return list<string>
     */
    protected function additionalDetailFields(): array
    {
        return [];
    }

    protected function initPage(): void
    {
        // The page YAML (pages/{name}.yml) is OPTIONAL — hand-built pages keep
        // defining their layout in the component itself. It is loaded even when
        // the guarded init below bails out (read-denied object, stale record id):
        // Blade evaluates a page blade's slot content before the page chrome
        // discards it for the denied state, so a hand-built page reading
        // $pageLayout['detail'] must always find its layout.
        $this->pageLayout = StaticConfigHelper::getPageFields($this->componentName(), $this->detailModel ?? null);

        $this->initNoerdComponent(function (): void {
            // Pages backed by a single Eloquent model declare $detailModel — the
            // record is loaded into $detailData exactly like a detail would.
            if (isset($this->detailModel)) {
                $modelClass = $this->detailModel;
                if (! $this->loadDetailModel(new $modelClass(), $modelClass)) {
                    return;
                }
            }

            $this->resolveQuickCreate();
        });
    }

    protected function storeProcess(Model $model): void
    {
        $this->showSuccessIndicator = true;

        // Leaving quick-create once the new record has its id is
        // QuickCreateExitHook's job — it runs after every action.
        if ($model->wasRecentlyCreated) {
            $this->modelId = $model->id;
        }
    }

    /**
     * Shared init skeleton of initPage()/initDetail()/initSettings(): routed-
     * modal redirect, the object-read guard (canReadObject() is unrestricted
     * for components without $detailModel and overridden by settings pages),
     * then the component-specific loading. The three inits used to be three
     * drifting copies of exactly this sequence.
     */
    protected function initNoerdComponent(callable $load): void
    {
        if ($this->prepareRoutedModal()) {
            return;
        }

        if (! $this->canReadObject()) {
            $this->objectReadBlocked = true;

            return;
        }

        $load();
    }

    /**
     * Hook for pages that persist additional page-owned state after the embedded
     * detail stored its record (e.g. product groups/variants, file uploads).
     */
    protected function afterEmbeddedDetailStored(Model $model): void
    {
        // Intentionally empty.
    }

    /**
     * Load the record identified by $modelId into $detailData (or keep the fresh
     * model for a new record). Returns false when the id no longer resolves — the
     * modal is closed and the caller must stop mounting.
     */
    protected function loadDetailModel(Model $model, string $modelClass): bool
    {
        if ($this->modelId) {
            $query = $modelClass::query();

            // Relations the form reads (a card, a title, a nested table) come
            // along in the one query instead of being lazy-loaded one by one.
            if (property_exists($this, 'detailWith') && is_array($this->detailWith) && $this->detailWith !== []) {
                $query->with($this->detailWith);
            }

            $model = $query->find($this->modelId);

            if (! $model) {
                $this->modelId = null;
                $this->dispatch('closeTopModal');

                return false;
            }
        }

        $this->detailData = collect($model->toArray())
            ->except(['created_at', 'updated_at'])
            ->toArray();

        $this->detailLoaded($model);

        return true;
    }

    /**
     * Runs once the record (or the fresh model of a new record) is in
     * $detailData — the place for everything a component used to re-find the
     * record for in a custom mount(): derived values, extra form keys, a
     * related record's data. Nothing the trait does afterwards (layout defaults,
     * relation forms, custom attributes) is skipped, unlike a mount() that
     * replaces $detailData wholesale.
     */
    protected function detailLoaded(Model $model): void
    {
        // Intentionally empty.
    }

    /**
     * The name this component's detail YAML resolves under — the component's
     * own name. Override for a component that renders another component's YAML.
     */
    protected function getDetailComponent(): string
    {
        return $this->componentName();
    }

    /**
     * The list this record belongs to (refreshed when the modal closes), derived
     * from the component name with its namespace kept: 'customer-detail' →
     * 'customers-list', 'crm::account-page' → 'crm::accounts-list'. Override when
     * the list name does not follow the plural convention.
     */
    protected function getListComponent(): string
    {
        return ComponentNaming::pairedList($this->componentName());
    }

    protected function setPreselect(string $key, mixed $value): void
    {
        $filters = session('listFilters', []);
        $filters[$key] = $value;
        session(['listFilters' => $filters]);
    }

    protected function preselect(string $key, bool $onlyNew = true): void
    {
        if ($onlyNew) {
            if ($this->modelId) {
                return;
            }
            if (property_exists($this, 'relations') && ($this->relations[$key] ?? null)) {
                return;
            }
        }

        $filters = session('listFilters', []);
        if (empty($filters[$key])) {
            return;
        }

        $method = Str::camel(Str::beforeLast($key, '_id')) . 'Selected';
        if (method_exists($this, $method)) {
            $this->{$method}($filters[$key]);

            return;
        }

        // No handler: the value goes straight into the form, and the relation
        // field's title resolves through the type registered for the column
        // (`customer_id` → `customerRelation`). A detail therefore needs no
        // `{x}Selected()` method just to adopt a preselection.
        data_set($this->detailData, $key, $filters[$key]);

        if (property_exists($this, 'relationTitles')) {
            $title = app(RelationTitleResolver::class)->title($key, $filters[$key]);
            if ($title !== null) {
                $this->relationTitles[$key] = $title;
            }
        }
    }

    /**
     * Get the event listeners for the component: the generic list refresh plus —
     * for the embedded detail the page YAML declares — the store roundtrip
     * events scoped by the detail's full component name.
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
        $listeners = [
            'refreshList-' . $this->getDetailComponent() => 'refreshList',
        ];

        if ($detail = $this->embeddedDetailComponent()) {
            $listeners['detailStored-' . $detail] = 'embeddedDetailStored';
            $listeners['detailDataUpdated-' . $detail] = 'embeddedDetailDataUpdated';
        }

        return $listeners;
    }

    /**
     * Every model-backed `*-detail` must declare its URL alias explicitly.
     * `*-page` components (entity pages declare it anyway; settings pages are
     * tenant singletons) and components without $detailModel (dashboards,
     * always-embedded children, bespoke modals) are exempt.
     */
    private function assertDetailPrimaryDeclared(): void
    {
        if (! isset($this->detailModel) || ($this->detailPrimary ?? null) !== null) {
            return;
        }

        if (! Str::endsWith($this->componentName(), '-detail')) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Model-backed detail [%s] must declare its URL alias: `public ?string $detailPrimary = \'%sId\';` '
            . '(embedded instances skip the URL binding automatically).',
            $this->componentName(),
            Str::camel(class_basename($this->detailModel)),
        ));
    }

    /**
     * Routed-modal handling shared by initPage() and initDetail(): normalizes
     * the 'new' modelId sentinel (the URL of a create modal, e.g.
     * /crm/account/new) to null and performs the ?modal=true redirect. Returns
     * true when a redirect was issued and mounting should stop.
     */
    private function prepareRoutedModal(): bool
    {
        if ($this->modelId === 'new') {
            $this->modelId = null;
        }

        return $this->redirectToRoutedModal();
    }

    /**
     * Resolve the quick-create runtime mode from the YAML opt-in (`quickCreate: true`,
     * carried in pageLayout). When opted in and no record is being edited,
     * quick-create mode is enabled — no list/nav/blade wiring needed. The raw
     * opt-in in pageLayout is replaced with the resolved mode so `tab-content`
     * reads the correct value.
     */
    private function resolveQuickCreate(): void
    {
        $optIn = (bool) ($this->pageLayout['quickCreate'] ?? false);
        if (! $this->modelId && $optIn) {
            $this->quickCreate = true;
        }

        if (! empty($this->pageLayout)) {
            $this->pageLayout['quickCreate'] = $this->quickCreate;
        }
    }
}
