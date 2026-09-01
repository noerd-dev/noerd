<?php

namespace Noerd\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Noerd\Facades\Noerd;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Helpers\ThemeHelper;
use Noerd\Support\ThemeContext;
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

    public bool $quickCreate = false;

    public array $detailData = [];

    /**
     * Set on mount when the current user may not read the object behind this
     * page/detail (see the noerd.object-read gate / AccessHelper). The page chrome then renders a
     * friendly denied state instead of the form, and no record data is loaded.
     */
    public bool $objectReadBlocked = false;

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

        $component = $this->componentName();

        if (session('noerd.lastDetailComponent') !== $component) {
            $this->currentTab = 1;
        }

        session(['noerd.lastDetailComponent' => $component]);
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

        if ($this->embedded || !$detailPrimary) {
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
     * wire:model state; the detail persists and reports back via
     * `detailStored-{detail}`. NoerdDetail overrides this with the model store.
     */
    public function store(): void
    {
        $detail = $this->embeddedDetailComponent();

        if ($detail) {
            $this->dispatch('storeDetail-' . $detail);
        }
    }

    public function delete(): void
    {
        // Server-side guard: the delete button is hidden for delete-denied users,
        // but the method stays reachable via keyboard shortcut and direct calls.
        if (!$this->canDeleteObject() || !isset($this->detailModel)) {
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

    public function canDeleteObject(): bool
    {
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
     * The embedded detail component declared in the page YAML (`detail:`).
     * Deliberately no fallback to the component's own name — a standalone
     * detail must never grow page listeners.
     */
    public function embeddedDetailComponent(): ?string
    {
        return $this->pageLayout['detail'] ?? null;
    }

    /**
     * The active theme (default, compact, numbered, …). Blades pass it to
     * hand-written chrome that lives outside the YAML field grid — position
     * tables above all — so those follow the form's theme:
     * `<livewire:module::position :theme="$this->detailTheme()" />`.
     */
    public function detailTheme(): string
    {
        return ThemeHelper::fromLayout($this->pageLayout);
    }

    /**
     * Livewire trait rendering hook: expose the active theme to chrome
     * components outside the YAML field grid (x-noerd::button above all).
     */
    public function renderingNoerdPage(): void
    {
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
    }

    /**
     * The embedded detail persisted its record: adopt the id, refresh the page's
     * model snapshot and run the shared post-store chrome (success indicator,
     * quick-create exit). Pages hook extra persistence via afterEmbeddedDetailStored().
     */
    public function embeddedDetailStored(int $modelId): void
    {
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
    public function embeddedDetailDataUpdated(array $detailData): void
    {
        $this->detailData = array_merge($this->detailData, $detailData);
    }

    /**
     * Open the detail of the record a relation field points at. $detailRoute is
     * the preferred target (the browser URL is rewritten to the record);
     * $detailComponent stays as the fallback for an unregistered route.
     */
    public function openRelationDetail(string $detailComponent, string $fieldName, ?string $detailRoute = null): void
    {
        $key = str_replace('detailData.', '', $fieldName);
        $id = data_get($this->detailData, $key);

        if (!$id) {
            $lastKey = last(explode('.', $key));
            $camelKey = Str::camel($lastKey);
            if (property_exists($this, $camelKey)) {
                $id = $this->{$camelKey};
            }
        }

        if ($id) {
            Noerd::modalFor($detailRoute, $detailComponent, ['modelId' => $id]);
        }
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
                if (!$this->loadDetailModel(new $modelClass(), $modelClass)) {
                    return;
                }
            }

            $this->resolveQuickCreate();
        });
    }

    protected function storeProcess(Model $model): void
    {
        $this->showSuccessIndicator = true;

        if ($model->wasRecentlyCreated) {
            $this->modelId = $model->id;
        }

        // A successful quick-create reveals the full detail of the new record so
        // the remaining fields can be completed — without closing and reopening the
        // modal. The same component instance simply switches out of quick-create
        // mode (its layout re-renders as the full form) and the panel is widened in
        // place. No overlay flicker, and the record's url parameter (e.g. taskId) is
        // kept rather than cleared.
        if ($this->quickCreate) {
            $this->quickCreate = false;
            $this->pageLayout['quickCreate'] = false;
            $this->dispatch('resizeTopModal');
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

        if (!$this->canReadObject()) {
            $this->objectReadBlocked = true;

            return;
        }

        $load();
    }

    /**
     * Every model-backed `*-detail` must declare its URL alias explicitly.
     * `*-page` components (entity pages declare it anyway; settings pages are
     * tenant singletons) and components without $detailModel (dashboards,
     * always-embedded children, bespoke modals) are exempt.
     */
    protected function assertDetailPrimaryDeclared(): void
    {
        if (!isset($this->detailModel) || ($this->detailPrimary ?? null) !== null) {
            return;
        }

        if (!Str::endsWith($this->componentName(), '-detail')) {
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
    protected function prepareRoutedModal(): bool
    {
        if ($this->modelId === 'new') {
            $this->modelId = null;
        }

        return $this->redirectToRoutedModal();
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
            $model = $modelClass::find($this->modelId);

            if (!$model) {
                $this->modelId = null;
                $this->dispatch('closeTopModal');

                return false;
            }
        }

        $this->detailData = collect($model->toArray())
            ->except(['created_at', 'updated_at'])
            ->toArray();

        return true;
    }

    /**
     * Resolve the quick-create runtime mode from the YAML opt-in (`quickCreate: true`,
     * carried in pageLayout). When opted in and no record is being edited,
     * quick-create mode is enabled — no list/nav/blade wiring needed. The raw
     * opt-in in pageLayout is replaced with the resolved mode so `tab-content`
     * reads the correct value.
     */
    protected function resolveQuickCreate(): void
    {
        $optIn = (bool) ($this->pageLayout['quickCreate'] ?? false);
        if (!$this->modelId && $optIn) {
            $this->quickCreate = true;
        }

        if (!empty($this->pageLayout)) {
            $this->pageLayout['quickCreate'] = $this->quickCreate;
        }
    }

    /**
     * The name this component's detail YAML resolves under — the component's
     * own name. Override when a component renders another component's YAML.
     */
    protected function getDetailComponent(): string
    {
        return $this->componentName();
    }

    /**
     * The list this record belongs to (refreshed when the modal closes), derived
     * from the component name with its namespace kept: 'customer-detail' →
     * 'customers-list', 'crm::account-page' → 'crm::accounts-list'. Override
     * when the list name does not follow the plural convention.
     */
    protected function getListComponent(): string
    {
        $name = $this->componentName();

        // If this is already a list component, return as-is
        if (Str::endsWith($name, '-list')) {
            return $name;
        }

        // Extract entity: 'customer-detail' → 'customer', 'account-page' → 'account'
        $entity = Str::endsWith($name, '-page')
            ? Str::beforeLast($name, '-page')
            : Str::before($name, '-detail');

        // Pluralize and add -list: 'customer' → 'customers-list'
        return Str::plural($entity) . '-list';
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
        if (!empty($filters[$key])) {
            $method = Str::camel(Str::beforeLast($key, '_id')) . 'Selected';
            if (method_exists($this, $method)) {
                $this->{$method}($filters[$key]);
            }
        }
    }

    /**
     * Get the event listeners for the component: the generic list refresh plus —
     * when the page YAML declares an embedded detail — the store roundtrip events
     * scoped by the detail's full component name.
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

        $detail = $this->embeddedDetailComponent();
        if ($detail) {
            $listeners['detailStored-' . $detail] = 'embeddedDetailStored';
            $listeners['detailDataUpdated-' . $detail] = 'embeddedDetailDataUpdated';
        }

        return $listeners;
    }
}
