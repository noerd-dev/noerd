<?php

namespace Noerd\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Noerd\Helpers\StaticConfigHelper;

/**
 * Base trait for `*-page` components: page chrome (tabs, modal lifecycle,
 * quick-create), the optional page YAML (`pages/{name}.yml`) and the generic
 * store roundtrip with an embedded `*-detail` component. NoerdDetail composes
 * this trait — a detail is a page plus the model-form concerns.
 */
trait NoerdPage
{
    public bool $showSuccessIndicator = false;

    #[Url(as: 'id', keep: false, except: '')]
    public $modelId = null;

    #[Url(as: 'tab', keep: false, except: 1)]
    public int $currentTab = 1;

    public array $pageLayout = [];

    public bool $disableModal = false;

    /**
     * Set when the component is rendered inside a hosting page component (e.g.
     * account-page embeds account-detail). The x-noerd::page chrome (header,
     * footer, scroll wrapper) is skipped automatically — the hosting page owns it.
     */
    public bool $embedded = false;

    public bool $quickCreate = false;

    public array $detailData = [];

    /**
     * Get the component name (alias for getName).
     */
    public function getComponentName(): string
    {
        return $this->getName();
    }

    /**
     * Livewire trait mount hook (runs for every page/detail). The active tab is
     * shared across all components via the `tab` URL param, so a stale tab (e.g.
     * the lead activity-log tab 2) would otherwise bleed into the next component.
     * Only keep the carried-over tab when the previously opened component was the
     * SAME type (e.g. lead → another lead); a different type starts on tab 1.
     */
    public function mountNoerdPage(): void
    {
        // Embedded children (a detail rendered inside a hosting page component) own no
        // tabs — leave the tab session/URL state to the hosting page.
        if ($this->embedded) {
            return;
        }

        $component = $this->getName();

        if (session('noerd.lastDetailComponent') !== $component) {
            $this->currentTab = 1;
        }

        session(['noerd.lastDetailComponent' => $component]);
    }

    public function mount(): void
    {
        $this->initPage();
    }

    public function initPage(): void
    {
        // Pages backed by a single Eloquent model declare $detailModel — the
        // record is loaded into $detailData exactly like a detail would.
        if (isset($this->detailModel)) {
            $modelClass = $this->detailModel;
            if (! $this->loadDetailModel(new $modelClass(), $modelClass)) {
                return;
            }
        }

        // The page YAML (pages/{name}.yml) is OPTIONAL — hand-built pages keep
        // defining their layout in the component itself.
        $this->pageLayout = StaticConfigHelper::getPageFields($this->getName(), $this->detailModel ?? null);

        $this->resolveQuickCreate();
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
        $modelClass = $this->detailModel;
        $modelClass::find($this->modelId)?->delete();

        $this->closeModalProcess($this->getListComponent());
    }

    public function storeProcess($model): void
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
     * The embedded detail component declared in the page YAML (`detail:`).
     * Deliberately no fallback to DETAIL_COMPONENT — legacy details reusing that
     * constant must never grow page listeners.
     */
    public function embeddedDetailComponent(): ?string
    {
        return $this->pageLayout['detail'] ?? null;
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

    public function refreshList(): void
    {
        $this->dispatch('$refresh');
    }

    public function callAMethod(callable $callback)
    {
        return call_user_func($callback);
    }

    public function openRelationDetail(string $detailComponent, string $fieldName): void
    {
        $key = str_replace('detailData.', '', $fieldName);
        $id = data_get($this->detailData, $key);

        if (! $id) {
            $lastKey = last(explode('.', $key));
            $camelKey = Str::camel($lastKey);
            if (property_exists($this, $camelKey)) {
                $id = $this->{$camelKey};
            }
        }

        if ($id) {
            $this->dispatch(
                event: 'noerdModal',
                modalComponent: $detailComponent,
                arguments: ['modelId' => $id],
            );
        }
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
        if (property_exists($this, 'modelId') && $this->modelId) {
            $model = $modelClass::find($this->modelId);

            if (! $model) {
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
     * carried in pageLayout) or the legacy `public bool $quickCreateOnNew = true;`
     * property. When opted in and no record is being edited, quick-create mode is
     * enabled — no list/nav/blade wiring needed. The raw opt-in in pageLayout is
     * replaced with the resolved mode so `tab-content` reads the correct value.
     */
    protected function resolveQuickCreate(): void
    {
        $optIn = ($this->quickCreateOnNew ?? false) || ($this->pageLayout['quickCreate'] ?? false);
        if (! $this->modelId && $optIn) {
            $this->quickCreate = true;
        }

        if (! empty($this->pageLayout)) {
            $this->pageLayout['quickCreate'] = $this->quickCreate;
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
     * Uses LIST_COMPONENT constant if defined, otherwise derives from the component
     * name: 'customer-detail' → 'customers-list', 'account-page' → 'accounts-list'.
     * Namespaced list components (e.g. 'crm::accounts-list') cannot be derived —
     * those components declare LIST_COMPONENT explicitly.
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
        if (! empty($filters[$key])) {
            $method = Str::camel(Str::beforeLast($key, '_id')) . 'Selected';
            $this->{$method}($filters[$key]);
        }
    }

    /**
     * Get the event listeners for the component: the generic list refresh plus —
     * when the page YAML declares an embedded detail — the store roundtrip events
     * scoped by the detail's full component name.
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
