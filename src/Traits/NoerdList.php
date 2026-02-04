<?php

namespace Noerd\Traits;

use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Services\ListQueryContext;

trait NoerdList
{
    use WithoutUrlPagination;
    use WithPagination;

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

    public mixed $context = '';

    public bool $disableModal = false;

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

    public function mount(): void
    {
        $this->listId = Str::random();
        $this->loadActiveListFilters();
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

    /**
     * Handle select action - dispatch selection event and close modal.
     */
    public function selectAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch($this->getSelectEvent(), $modelId, $this->context);

        $this->dispatch('closeTopModal');
    }

    protected function componentName(): string
    {
        return defined('static::COMPONENT') ? static::COMPONENT : $this->getName();
    }

    /**
     * Get the component name (alias for getName).
     */
    public function getComponentName(): string
    {
        return $this->getName();
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
}
