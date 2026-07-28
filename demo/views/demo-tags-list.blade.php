<?php

use App\Models\DemoTag;
use Livewire\Component;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = DemoTag::class;
    public $detailComponent = 'demo-tag-detail';
};
?>

<x-noerd::page>
    <x-noerd::list/>
</x-noerd::page>
