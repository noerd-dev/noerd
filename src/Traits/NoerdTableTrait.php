<?php

namespace Noerd\Noerd\Traits;

use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

trait NoerdTableTrait
{
    use WithPagination;

    protected const PAGINATION = 50;

    public string $search = '';

    public string $sortField = 'id';

    public bool $sortAsc = false;

    public string $modalTitle = '';

    public bool $disableModal = false;

    public string $tableId = '';

    #[Url]
    public ?string $filter = null;
    #[Url]
    public ?string $filter2 = null;

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

    //  #[On('enableComponent-' . self::COMPONENT)]
    //  public function enableComponent(): void
    //  {
    //      $this->disableComponent = false;
    //      $this->dispatch('$refresh');
    //  }

    //  #[On('disableComponent-' . self::COMPONENT)]
    //  public function disableComponent(): void
    //  {
    //      //
    //  }

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

    public function updateRow(): void {}

    public function tableFilters(): void {}

    public function states(): void {}

    public function filters(): void {}
}
