<?php

namespace Noerd\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Noerd\Helpers\StaticConfigHelper;

trait NoerdDetail
{
    public bool $showSuccessIndicator = false;

    #[Url(as: 'id', keep: false, except: '')]
    public $modelId = null;

    #[Url(as: 'tab', keep: false, except: 1)]
    public int $currentTab = 1;

    public array $pageLayout = [];

    public bool $disableModal = false;

    public array $relationTitles = [];

    public array $detailData = [];

    public array $recordNavigationIds = [];

    /**
     * Get the component name (alias for getName).
     */
    public function getComponentName(): string
    {
        return $this->getName();
    }

    public function initDetail(): void
    {
        $this->loadRecordNavigation();

        // For detail components with DETAIL_CLASS constant
        if (defined('static::DETAIL_CLASS')) {
            $modelClass = static::DETAIL_CLASS;
            $this->mountDetailComponent(new $modelClass(), $modelClass);
        }
    }

    /**
     * Load record navigation IDs from session.
     */
    protected function loadRecordNavigation(): void
    {
        $listComponent = $this->getListComponent();
        $this->recordNavigationIds = session("record_navigation.{$listComponent}", []);
    }

    /**
     * Navigate to the next or previous record.
     */
    public function navigateRecord(string $direction): void
    {
        if (empty($this->recordNavigationIds) || ! $this->modelId) {
            file_put_contents(storage_path('logs/nav-debug.log'), date('H:i:s') . " NAVIGATE early return: ids=" . count($this->recordNavigationIds) . " modelId={$this->modelId}\n", FILE_APPEND);

            return;
        }

        $currentIndex = array_search((int) $this->modelId, $this->recordNavigationIds);
        if ($currentIndex === false) {
            file_put_contents(storage_path('logs/nav-debug.log'), date('H:i:s') . " NAVIGATE not found: modelId={$this->modelId}(" . gettype($this->modelId) . ") first3ids=" . implode(',', array_slice($this->recordNavigationIds, 0, 3)) . '(' . gettype($this->recordNavigationIds[0] ?? null) . ")\n", FILE_APPEND);

            return;
        }

        $newIndex = $direction === 'next' ? $currentIndex + 1 : $currentIndex - 1;

        if ($newIndex < 0 || $newIndex >= count($this->recordNavigationIds)) {
            return;
        }

        file_put_contents(storage_path('logs/nav-debug.log'), date('H:i:s') . " NAVIGATE OK: {$this->modelId} -> {$this->recordNavigationIds[$newIndex]}\n", FILE_APPEND);
        $this->loadRecord($this->recordNavigationIds[$newIndex]);
    }

    /**
     * Load a different record into the current detail component.
     * Calls mount() to re-initialize all component properties.
     */
    public function loadRecord(mixed $id): void
    {
        $this->modelId = $id;
        $this->currentTab = 1;
        $this->showSuccessIndicator = false;
        $this->relationTitles = [];

        $this->mount();

        $this->dispatch('record-navigated', id: $id);
    }

    /**
     * Get record navigation position info for the UI indicator.
     */
    public function getRecordNavigationInfo(): array
    {
        if (empty($this->recordNavigationIds) || ! $this->modelId) {
            return ['available' => false];
        }

        $currentIndex = array_search((int) $this->modelId, $this->recordNavigationIds);
        if ($currentIndex === false) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'hasPrev' => $currentIndex > 0,
            'hasNext' => $currentIndex < count($this->recordNavigationIds) - 1,
            'current' => $currentIndex + 1,
            'total' => count($this->recordNavigationIds),
        ];
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

        if ($model->wasRecentlyCreated) {
            $this->modelId = $model->id;
        }
    }

    /**
     * Validate using rules from pageLayout YAML configuration.
     * Fields with 'required: true' will be validated as required.
     */
    public function validateFromLayout(): void
    {
        $rules = [];
        $this->extractRulesFromFields($this->pageLayout['fields'] ?? [], $rules);

        if (!empty($rules)) {
            $this->validate($rules);
        }
    }

    public function clearRelation(string $fieldName): void
    {
        $key = str_replace(['model.', 'detailData.'], '', $fieldName);
        $this->relationTitles[$key] = '';

        if (array_key_exists($key, $this->detailData)) {
            $this->detailData[$key] = null;
        }
    }

    public function refreshList(): void
    {
        $this->dispatch('$refresh');
    }

    public function callAMethod(callable $callback)
    {
        return call_user_func($callback);
    }

    public function changeEditMode(): void
    {
        $this->editMode = !$this->editMode;
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

    /**
     * Get the model data property name.
     * 'customer-detail' → 'customerData'
     */
    protected function getModelDataProperty(): string
    {
        $entity = Str::before($this->getDetailComponent(), '-detail');

        return Str::camel($entity) . 'Data';
    }

    protected function mountDetailComponent(Model $model, string $modelClass): void
    {
        $idProperty = 'modelId';

        // Load by ID if property is set
        if (property_exists($this, $idProperty) && $this->{$idProperty}) {
            $model = $modelClass::find($this->{$idProperty});

            if (!$model) {
                $this->{$idProperty} = null;
                $this->dispatch('closeTopModal');
                return;
            }
        }

        $pageLayout = StaticConfigHelper::getComponentFields($this->getDetailComponent());
        $this->pageLayout = $pageLayout;
        $this->detailData = $model->toArray();
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

            if (!isset($field['name'])) {
                continue;
            }

            $fieldRules = [];

            if ($field['required'] ?? false) {
                $fieldRules[] = 'required';
            }

            if (!empty($fieldRules)) {
                $rules[$field['name']] = $fieldRules;
            }
        }
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
     * Get the event listeners for the component.
     * Dynamically registers the refreshList listener based on detail component name.
     */
    protected function getListeners(): array
    {
        return [
            'refreshList-' . $this->getDetailComponent() => 'refreshList',
        ];
    }
}
