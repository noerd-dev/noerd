<?php

use App\Models\DemoCategory;
use Livewire\Component;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = DemoCategory::class;
    public ?string $detailRoute = 'demo-category.detail';

    public $detailComponent = 'demo-category-detail';
}; ?>

<x-noerd::page>
    <x-noerd::list/>
</x-noerd::page>
