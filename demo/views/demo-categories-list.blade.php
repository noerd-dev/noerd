<?php

use App\Models\DemoCategory;
use Livewire\Component;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = DemoCategory::class;
    public $detailComponent = 'demo-category-detail';

    public function rendering()
    {
        if ((int) request()->demoCategoryId) {
            $this->listAction(request()->demoCategoryId);
        }

        if (request()->create) {
            $this->listAction();
        }
    }
}; ?>

<x-noerd::page>
    <x-noerd::list/>
</x-noerd::page>
