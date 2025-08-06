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
    use WithPagination, WithoutUrlPagination;

    protected const PAGINATION = 50;

    /* a modelId is required to load a model thorugh a event or as a parameter */
    public ?string $modelId = null;

    public bool $showSuccessIndicator = false;
    public int $currentTab = 1;
    public array $pageLayout;
    public $lastChangeTime;

    public bool $disableModal = false;

    public string $search = '';

    public string $sortField = 'id';

    public bool $sortAsc = false;

    public string $modalTitle = '';

    public string $tableId = '';

    #[Url]
    public ?string $filter = null;
    #[Url]
    public array $currentTableFilter = [];

    public array $activeTableFilters = [];

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

        if (is_array($tableData)) {
            $item = $tableData[$id];
            $this->tableAction($item['id']);
            return;
        }

        $item = $tableData->getCollection()->get($id);
        if (!$item) {
            return;
        }
        $this->tableAction($item->id);
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

    #[On('close-modal-' . self::COMPONENT)]
    public function closeModalProcess(?string $source = null): void
    {
        if (defined('self::ID')) {
            $this->{self::ID} = '';
        }

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

    public function updateRow(): void
    {
    }

    public function tableFilters(): void
    {
    }

    public function states(): void
    {
    }

    public function filters(): void
    {
    }
}
