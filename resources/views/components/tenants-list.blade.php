<?php

use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\Tenant;
use Noerd\Traits\NoerdList;

new class extends Component {
    use NoerdList;

    public $listModel = Tenant::class;
    public ?string $detailRoute = 'noerd.tenant.detail';

    public $detailComponent = 'noerd::tenant-detail';

    public function listData(): array
    {
        $query = $this->listQuery($this->listModel);

        if (! NoerdAuth::user()->isSuperAdmin()) {
            $query->whereIn('id', NoerdAuth::user()->adminTenants()->pluck('tenants.id'));
        }

        $rows = $this->paginateList($query);

        return $this->buildList($rows);
    }
}; ?>

<x-noerd::page>

    <x-noerd::list />

</x-noerd::page>
