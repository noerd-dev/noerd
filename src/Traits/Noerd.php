<?php

namespace Noerd\Noerd\Traits;

use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Noerd\Noerd\Helpers\StaticConfigHelper;

trait Noerd
{
    use WithoutUrlPagination;
    use WithPagination;

    protected const PAGINATION = 50;

    /* a modelId is required to load a model thorugh a event or as a parameter */
    public ?string $modelId = null;

    public bool $showSuccessIndicator = false;
    #[Url(as: 'tab', keep: false, except: 1)]
    public int $currentTab = 1;
    public array $pageLayout;
    public $lastChangeTime;

    public bool $disableModal = false;

    public string $search = '';

    public string $sortField = 'id';

    public bool $sortAsc = false;

    public string $tableActionMethod = 'tableAction';

    public ?string $selectTableConfig = null;

    public string $modalTitle = '';

    public string $tableId = '';

    #[Url]
    public ?string $filter = null;
    #[Url]
    public array $currentTableFilter = [];

    public array $activeTableFilters = [];

    public array $relationTitles = [];

    #[On('reloadTable-' . self::COMPONENT)]
    public function reloadTable(): void
    {
        $this->dispatch('$refresh');
    }

    public function mount(): void
    {
        $this->tableId = Str::random();
        $this->loadActiveTableFilters();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortAsc = !$this->sortAsc;
        } else {
            $this->sortAsc = true;
        }
        $this->sortField = $field;
    }

    public function storeActiveTableFilters(): void
    {
        session(['activeTableFilters' => $this->activeTableFilters]);
    }

    public function loadActiveTableFilters(): void
    {
        $this->activeTableFilters = session('activeTableFilters', []);
    }

    public function findTableAction(int|string $id): void
    {
        $tableData = $this->with()['rows'];
        $method = $this->tableActionMethod;

        if (is_array($tableData)) {
            $item = $tableData[$id];
            $this->$method($item['id']);

            return;
        }

        $item = $tableData->getCollection()->get($id);
        if (! $item) {
            return;
        }
        $this->$method($item->id);
    }

    public function changeEditMode(): void
    {
        $this->editMode = !$this->editMode;
    }

    public function callAMethod(callable $callback)
    {
        return call_user_func($callback);
    }

    public function mountModalProcess(string $component, $model): void
    {
        $this->pageLayout = StaticConfigHelper::getComponentFields($component);
        $this->model = $model->toArray();
        $this->modelId = $model->id;
        $this->{self::ID} = $model['id'];
    }

    /**
     * Get table configuration from YAML.
     * Uses self::COMPONENT by default, or a custom name if provided.
     * In select mode, uses selectTableConfig if set.
     */
    protected function getTableConfig(?string $customName = null): array
    {
        if ($customName === null && $this->tableActionMethod === 'selectAction' && $this->selectTableConfig) {
            return StaticConfigHelper::getTableConfig($this->selectTableConfig);
        }

        return StaticConfigHelper::getTableConfig($customName ?? self::COMPONENT);
    }

    /**
     * Get the event name for select mode.
     * Derives from COMPONENT: 'customers-list' → 'customerSelected'
     */
    protected function getSelectEvent(): string
    {
        $entity = Str::singular(Str::before(self::COMPONENT, '-list'));

        return Str::camel($entity) . 'Selected';
    }

    /**
     * Handle select action - dispatch selection event and close modal.
     */
    public function selectAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch($this->getSelectEvent(), $modelId);
        $this->dispatch('close-modal-' . self::COMPONENT);
    }

    #[On('close-modal-' . self::COMPONENT)]
    public function closeModalProcess(?string $source = null, ?string $modalKey = null): void
    {
        if (defined('self::ID')) {
            $this->{self::ID} = '';
        }
        $this->currentTab = 1;
        $this->dispatch('downModal2', componentName: self::COMPONENT, source: $source, modalKey: $modalKey);

        if ($source) {
            $this->dispatch('reloadTable-' . $source);
        }
    }

    public function storeProcess($model): void
    {
        $this->showSuccessIndicator = true;
        if ($model->wasRecentlyCreated) {
            $this->modelId = $model['id'];
        }
        $this->{self::ID} = $model['id'];
    }

    public function updateRow(): void {}

    public function tableFilters(): void {}

    public function states(): void {}

    public function filters(): void {}

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
}
