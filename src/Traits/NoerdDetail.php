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
     * Check if record navigation is available.
     */
    public function hasRecordNavigation(): bool
    {
        if (! $this->modelId) {
            return false;
        }

        if (! empty($this->recordNavigationIds)) {
            return true;
        }

        return defined('static::DETAIL_CLASS');
    }

    /**
     * Navigate to the next or previous record.
     */
    public function navigateRecord(string $direction): void
    {
        if (! $this->modelId) {
            return;
        }

        // Fallback: query model by ID when not opened from a list
        if (empty($this->recordNavigationIds)) {
            if (! defined('static::DETAIL_CLASS')) {
                return;
            }

            $modelClass = static::DETAIL_CLASS;
            // Down (next) = older record (lower ID), Up (prev) = newer record (higher ID)
            $newId = $direction === 'next'
                ? $modelClass::where('id', '<', $this->modelId)->orderByDesc('id')->value('id')
                : $modelClass::where('id', '>', $this->modelId)->orderBy('id')->value('id');

            if ($newId) {
                $this->loadRecord($newId);
            }

            return;
        }

        $currentIndex = array_search((int) $this->modelId, $this->recordNavigationIds);
        if ($currentIndex === false) {
            return;
        }

        $newIndex = $direction === 'next' ? $currentIndex + 1 : $currentIndex - 1;

        if ($newIndex < 0 || $newIndex >= count($this->recordNavigationIds)) {
            return;
        }

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
        if (! $this->modelId) {
            return ['available' => false];
        }

        // Fallback mode: query model by ID
        if (empty($this->recordNavigationIds)) {
            if (! defined('static::DETAIL_CLASS')) {
                return ['available' => false];
            }

            $modelClass = static::DETAIL_CLASS;

            // Up (prev) = newer (higher ID), Down (next) = older (lower ID)
            return [
                'available' => true,
                'hasPrev' => $modelClass::where('id', '>', $this->modelId)->exists(),
                'hasNext' => $modelClass::where('id', '<', $this->modelId)->exists(),
                'current' => null,
                'total' => null,
            ];
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

    public function openSelectMediaModal(string $fieldName): void
    {
        $this->mediaToken = uniqid('media_', true);
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'media-list',
            arguments: ['selectMode' => true, 'selectContext' => $fieldName, 'selectToken' => $this->mediaToken],
        );
    }

    #[On('mediaSelected')]
    public function mediaSelected(int $mediaId, ?string $fieldName = null, ?string $token = null): void
    {
        if ($this->mediaToken === null || $this->mediaToken !== $token) {
            return;
        }

        $resolver = app(MediaResolverContract::class);
        if (! $resolver->exists($mediaId)) {
            return;
        }

        $key = $this->resolveImageFieldKey($fieldName);
        $this->detailData[$key] = $mediaId;
        $this->mediaToken = null;
    }

    public function deleteImage(string $fieldName): void
    {
        $key = $this->resolveImageFieldKey($fieldName);
        $this->detailData[$key] = null;
    }

    public function updatedImageUploads(mixed $value, string $fieldKey): void
    {
        $resolver = app(MediaResolverContract::class);
        $url = $resolver->storeUploadedFile($value);

        if ($url) {
            $this->detailData[$fieldKey] = $url;
        }

        unset($this->imageUploads[$fieldKey]);
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

    /**
     * Strips "detailData." prefix from validation attribute names in error messages.
     * Called by Livewire before building the Validator instance.
     */
    public function validate($rules = null, $messages = [], $attributes = []): array
    {
        if (empty($attributes) && is_array($rules)) {
            foreach (array_keys($rules) as $ruleKey) {
                if (str_starts_with($ruleKey, 'detailData.')) {
                    $field = Str::after($ruleKey, 'detailData.');
                    $attributes[$ruleKey] = str_replace('_', ' ', $field);
                }
            }
        }

        return parent::validate($rules, $messages, $attributes);
    }

    public function clearRelation(string $fieldName): void
    {
        $key = str_replace('detailData.', '', $fieldName);
        $this->relationTitles[$key] = '';

        if (array_key_exists($key, $this->detailData)) {
            $this->detailData[$key] = null;
        }
    }

    public function openRelationDetail(string $detailComponent, string $fieldName): void
    {
        $parts = explode('.', $fieldName);
        $key = end($parts);

        $id = $this->detailData[$key] ?? null;

        if (! $id) {
            $camelKey = Str::camel($key);
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

    /**
     * Load record navigation IDs from session.
     */
    protected function loadRecordNavigation(): void
    {
        $listComponent = $this->getListComponent();
        $this->recordNavigationIds = session("record_navigation.{$listComponent}", []);
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
