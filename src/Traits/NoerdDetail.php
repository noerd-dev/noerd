<?php

namespace Noerd\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Noerd\Contracts\MediaResolverContract;
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

    public ?string $mediaToken = null;

    public array $detailData = [];

    public array $imageUploads = [];

    /**
     * Get the component name (alias for getName).
     */
    public function getComponentName(): string
    {
        return $this->getName();
    }

    public function initDetail(): void
    {
        // For detail components with DETAIL_CLASS constant
        if (defined('static::DETAIL_CLASS')) {
            $modelClass = static::DETAIL_CLASS;
            $this->mountDetailComponent(new $modelClass(), $modelClass);
        }
    }

    public function closeModalProcess(?string $source = null, ?string $modalKey = null): void
    {
        $this->currentTab = 1;

        $this->dispatch('closeTopModal');
        if ($source) {
            $this->dispatch('refreshList-' . Str::afterLast($source, '.'));
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

    #[On('setFieldValue')]
    public function setFieldValue(string $field, mixed $value, ?string $relationTitle = null): void
    {
        if (str_starts_with($field, 'detailData.')) {
            $key = str_replace('detailData.', '', $field);
            $detailData = $this->detailData;
            data_set($detailData, $key, $value);
            $this->detailData = $detailData;
        } else {
            $key = $field;
            $rootKey = Str::before($field, '.');

            if (property_exists($this, $rootKey)) {
                if ($rootKey === $field) {
                    $this->{$field} = $value;
                } else {
                    $rootValue = $this->{$rootKey};
                    data_set($rootValue, Str::after($field, $rootKey . '.'), $value);
                    $this->{$rootKey} = $rootValue;
                }
            } else {
                $detailData = $this->detailData;
                data_set($detailData, $field, $value);
                $this->detailData = $detailData;
            }
        }

        if ($relationTitle !== null) {
            $relationKey = last(explode('.', $key));
            $this->relationTitles[$relationKey] = $relationTitle;
        }
    }

    public function clearRelation(string $fieldName): void
    {
        $key = str_replace('detailData.', '', $fieldName);
        $relationKey = last(explode('.', $key));
        $this->relationTitles[$relationKey] = '';

        if (str_contains($key, '.')) {
            $detailData = $this->detailData;
            data_set($detailData, $key, null);
            $this->detailData = $detailData;
        } elseif (array_key_exists($key, $this->detailData)) {
            $this->detailData[$key] = null;
        }
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

    public function refreshList(): void
    {
        $this->dispatch('$refresh');
    }

    public function callAMethod(callable $callback)
    {
        return call_user_func($callback);
    }

    public function resolvePicklistOptions(string $picklistField): array
    {
        if (method_exists($this, $picklistField)) {
            return $this->{$picklistField}();
        }

        $registry = app(\Noerd\Services\PicklistRegistry::class);
        $provider = $registry->resolve($picklistField);

        return $provider ? $provider() : [];
    }

    protected function resolveImageFieldKey(string $fieldName): string
    {
        return str_replace('detailData.', '', $fieldName);
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
        $this->detailData = collect($model->toArray())
            ->except(['created_at', 'updated_at'])
            ->toArray();
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
