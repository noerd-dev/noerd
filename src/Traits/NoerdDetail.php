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

    /**
     * Get the component name (alias for getName).
     */
    public function getComponentName(): string
    {
        return $this->getName();
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
     * Get the model ID property name.
     * Uses ID constant if defined, otherwise derives from component name.
     * 'customer-detail' → 'customerId'
     */
    protected function getModelIdProperty(): string
    {
        if (defined('static::ID')) {
            return static::ID;
        }

        return 'id';
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

        // Check if the property exists on the component
        if (! property_exists($this, $idProperty)) {
            // Fall back to modelId if the derived property doesn't exist
            $idProperty = 'modelId';
        }

        // Load by ID if property is set
        if (property_exists($this, $idProperty) && $this->{$idProperty}) {
            $model = $modelClass::find($this->{$idProperty});

            if (! $model) {
                $this->{$idProperty} = null;
                $this->dispatch('closeTopModal');

                return false;
            }
        }

        // Set ID from loaded model
        if (property_exists($this, $idProperty)) {
            $this->{$idProperty} = $model->id;
        }

        // Standard mount process
        $this->mountModalProcess($this->getDetailComponent(), $model);
        $this->detailData = $model->toArray();

        return true;
    }

    public function initDetail(mixed $model = null): void
    {
        // For detail components with DETAIL_CLASS constant
        if (defined('static::DETAIL_CLASS')) {
            $modelClass = static::DETAIL_CLASS;
            $idProperty = $this->getModelIdProperty();

            // Check if the property exists, otherwise fall back to modelId
            if (! property_exists($this, $idProperty)) {
                $idProperty = 'modelId';
            }

            // If model or ID passed as parameter, set the ID property
            if ($model !== null && property_exists($this, $idProperty)) {
                $this->{$idProperty} = $model instanceof Model ? $model->id : $model;
            }

            $this->mountDetailComponent(new $modelClass(), $modelClass);
        }
    }

    public function mountModalProcess(string $component, $model, ?array $pageLayout = null): void
    {
        if ($pageLayout === null) {
            $pageLayout = StaticConfigHelper::getComponentFields($component);
        }
        $this->pageLayout = $pageLayout;
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

    public function callAMethod(callable $callback)
    {
        return call_user_func($callback);
    }

    public function changeEditMode(): void
    {
        $this->editMode = ! $this->editMode;
    }
}
