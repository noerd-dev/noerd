<?php

namespace Noerd\Noerd\Traits;

use Livewire\Attributes\On;
use Livewire\WithPagination;
use Noerd\Noerd\Helpers\StaticConfigHelper;

trait NoerdModelTrait
{
    use WithPagination;

    /* a modelId is required to load a model thorugh a event or as a parameter */
    public ?int $modelId = null;

    public bool $showSuccessIndicator = false;
    public int $currentTab = 1;
    public array $pageLayout;
    public $lastChangeTime;

    public bool $disableModal = false;

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
}
