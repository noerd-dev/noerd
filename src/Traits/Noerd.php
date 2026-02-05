<?php

namespace Noerd\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Services\ListQueryContext;

trait Noerd
{
    use WithoutUrlPagination;
    use WithPagination;

    // === List Properties ===
    protected const PAGINATION = 50;

    public $lastChangeTime;

    public string $search = '';

    public string $sortField = 'id';

    public bool $sortAsc = false;

    public string $listActionMethod = 'listAction';

    public ?string $selectListConfig = null;

    public string $listId = '';

    #[Url]
    public ?string $filter = null;

    #[Url]
    public array $currentTableFilter = [];

    public array $activeListFilters = [];

    // === Modal Properties ===
    public bool $showSuccessIndicator = false;

    #[Url(as: 'tab', keep: false, except: 1)]
    public int $currentTab = 1;

    public array $pageLayout;

    public bool $disableModal = false;

    public array $relationTitles = [];

    public mixed $context = '';

    public array $detailData = [];

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

    /**
     * Get the model ID property name.
     * Uses ID constant if defined, otherwise derives from component name.
     * 'customer-detail' → 'customerId'
     */
    protected function getModelIdProperty(): string
    {
        if (defined('static::ID')) {
            return static::ID;
        }

        $entity = Str::before($this->getDetailComponent(), '-detail');

        return Str::camel($entity) . 'Id';
    }

    /**
     * Get the model data property name.
     * 'customer-detail' → 'customerData'
     */
    protected function getModelDataProperty(): string
    {
        $entity = Str::before($this->getDetailComponent(), '-detail');

        return Str::camel($entity) . 'Data';
    }

    /**
     * Mount a detail component with automatic model loading.
     * Handles: ID lookup, non-existent models, ID assignment, data population.
     *
     * @return bool True if model loaded successfully, false if not found
     */
    protected function mountDetailComponent(Model $model, string $modelClass): bool
    {
        $idProperty = $this->getModelIdProperty();

        // Load by ID if property is set
        if ($this->{$idProperty}) {
            $model = $modelClass::find($this->{$idProperty});

            if (! $model) {
                $this->{$idProperty} = null;
                $this->dispatch('closeTopModal');

                return false;
            }
        }

        // Set ID from loaded model
        $this->{$idProperty} = $model->id;

        // Standard mount process
        $this->mountModalProcess($this->getDetailComponent(), $model);
        $this->detailData = $model->toArray();

        return true;
    }

    /**
     * Get the event listeners for the component.
     * Dynamically registers the refreshList listener based on detail component name.
     */
    protected function getListeners(): array
    {
        return [
            'refreshList-' . $this->getDetailComponent() => 'refreshList',
        ];
    }

    public function refreshList(): void
    {
        $this->dispatch('$refresh');
    }

    public function mount(mixed $model = null): void
    {
        // For list components
        $this->listId = Str::random();
        $this->loadActiveListFilters();

        // For detail components with DETAIL_CLASS constant
        if (defined('static::DETAIL_CLASS')) {
            $modelClass = static::DETAIL_CLASS;
            $idProperty = $this->getModelIdProperty();

            // If model or ID passed as parameter, set the ID property
            if ($model !== null) {
                $this->{$idProperty} = $model instanceof Model ? $model->id : $model;
            }

            $this->mountDetailComponent(new $modelClass(), $modelClass);
        }
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
        if ($this->sortField === $field) {
            $this->sortAsc = ! $this->sortAsc;
        } else {
            $this->sortAsc = true;
        }
        $this->sortField = $field;
        $this->syncListQueryContext();
    }

    public function loadActiveListFilters(): void
    {
        $this->activeListFilters = session('activeListFilters', []);
    }

    public function findListAction(int|string $id): void
    {
        $withData = $this->with();
        $listData = $withData['listConfig']['rows'] ?? [];
        $method = $this->listActionMethod;

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
        $this->{$method}($item->id);
    }

    // Todo remove from Trait
    public function changeEditMode(): void
    {
        $this->editMode = ! $this->editMode;
    }

    // Todo remove from Trait
    public function callAMethod(callable $callback)
    {
        return call_user_func($callback);
    }
    public function mountModalProcess(string $component, $model, ?array $pageLayout = null): void
    {
        if ($pageLayout === null) {
            $pageLayout = StaticConfigHelper::getComponentFields($component);
        }
        $this->pageLayout = $pageLayout;
    }

    /**
     * Handle select action - dispatch selection event and close modal.
     */
    public function selectAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch($this->getSelectEvent(), $modelId, $this->context);

        $this->dispatch('closeTopModal');
    }

    public function closeModalProcess(?string $source = null, ?string $modalKey = null): void
    {
        $this->currentTab = 1;

        $this->dispatch('closeTopModal');
        if ($source) {
            $this->dispatch('refreshList-' . $source);
        }
    }

    public function storeProcess($model): void
    {
        $this->showSuccessIndicator = true;
    }

    /**
     * Validate using rules from pageLayout YAML configuration.
     * Fields with 'required: true' will be validated as required.
     */
    public function validateFromLayout(): void
    {
        $rules = [];
        $this->extractRulesFromFields($this->pageLayout['fields'] ?? [], $rules);

        if (! empty($rules)) {
            $this->validate($rules);
        }
    }

    protected function componentName(): string
    {
        return defined('static::COMPONENT') ? static::COMPONENT : $this->getName();
    }

    /**
     * Get the event name for select mode.
     * Derives from COMPONENT: 'customers-list' -> 'customerSelected'
     */
    protected function getSelectEvent(): string
    {
        $entity = Str::singular(Str::before($this->componentName(), '-list'));

        return Str::camel($entity) . 'Selected';
    }

    /**
     * Recursively extract validation rules from fields array.
     */
    protected function extractRulesFromFields(array $fields, array &$rules): void
    {
        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'block') {
                $this->extractRulesFromFields($field['fields'] ?? [], $rules);

                continue;
            }

            if (! isset($field['name'])) {
                continue;
            }

            $fieldRules = [];

            if ($field['required'] ?? false) {
                $fieldRules[] = 'required';
            }

            if (! empty($fieldRules)) {
                $rules[$field['name']] = $fieldRules;
            }
        }
    }

    public function updateRow(): void {}

    public function tableFilters(): void {}

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

    protected function syncListQueryContext(): void
    {
        app(ListQueryContext::class)->set(
            $this->search,
            $this->sortField,
            $this->sortAsc,
        );
    }

    /**
     * Build complete list configuration including rows and table state.
     * Returns all data needed for the list.index DETAIL_COMPONENT.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator|array  $rows
     */
    protected function buildList(mixed $rows, string|array|null $config = null): array
    {
        $listSettings = is_array($config)
            ? $config
            : $this->getListConfig($config);

        return [
            'listId' => $this->listId,
            'sortField' => $this->sortField,
            'sortAsc' => $this->sortAsc,
            'rows' => $rows,
            'listSettings' => $listSettings,
        ];
    }

    /**
     * Get list configuration from YAML.
     * Uses self::DETAIL_COMPONENT by default, or a custom name if provided.
     * In select mode, uses selectListConfig if set.
     */
    protected function getListConfig(?string $customName = null): array
    {
        if ($customName === null && $this->listActionMethod === 'selectAction' && $this->selectListConfig) {
            return StaticConfigHelper::getListConfig($this->selectListConfig);
        }

        return StaticConfigHelper::getListConfig($customName ?? $this->getDetailComponent());
    }
}
