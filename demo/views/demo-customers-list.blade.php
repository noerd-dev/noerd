<?php

use App\Models\DemoCustomer;
use Livewire\Component;
use Noerd\Facades\Noerd;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        Noerd::modal('demo-customer-detail', ['modelId' => $modelId, 'relations' => $relations]);
    }

    public function with(): array
    {
        $rows = DemoCustomer::paginate($this->perPage);

        return [
            'listConfig' => $this->buildList($rows),
        ];
    }
};
?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list/>
</x-noerd::page>
