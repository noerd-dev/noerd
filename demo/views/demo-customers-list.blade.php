<?php

use App\Models\DemoCustomer;
use Livewire\Component;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = DemoCustomer::class;
    public ?string $detailRoute = 'demo-customer.detail';

    public $detailComponent = 'demo-customer-detail';
}; ?>

<x-noerd::page>
    <x-noerd::list/>
</x-noerd::page>
