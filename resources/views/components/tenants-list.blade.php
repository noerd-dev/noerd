<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Noerd\Models\Tenant;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = Tenant::class;

    public ?string $detailRoute = 'tenant.detail';

    public $detailComponent = 'noerd::tenant-detail';

    public function mount(): void
    {
        $this->mountList();
        $this->setDefaultSort('name', true);
    }

    public function listData(): array
    {
        $query = $this->listQuery($this->listModel);

        if (! Auth::user()->isSuperAdmin()) {
            $query->whereIn('id', Auth::user()->adminTenants()->pluck('tenants.id'));
        }

        $rows = $query->paginate($this->perPage);

        return $this->buildList($rows);
    }
}; ?>

<x-noerd::page>

    <x-noerd::list />

</x-noerd::page>
