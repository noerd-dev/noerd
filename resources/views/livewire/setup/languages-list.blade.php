<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Noerd\Cms\Models\Language;
use Noerd\Noerd\Helpers\StaticConfigHelper;
use Noerd\Noerd\Traits\Noerd;

new class extends Component {

    use Noerd;

    public const COMPONENT = 'languages-list';

    public function mount()
    {
        if ((int)request()->customerId) {
            $this->tableAction(request()->customerId);
        }

        if (request()->create) {
            $this->tableAction();
        }
    }

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'language-detail',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with(): array
    {
        $rows = Language::where('tenant_id', Auth::user()->selected_tenant_id)
            ->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('code', 'like', '%' . $this->search . '%')
                        ->orWhere('name', 'like', '%' . $this->search . '%');
                });
            })
            ->paginate(self::PAGINATION);

        $tableConfig = StaticConfigHelper::getTableConfig('languages-list');

        return [
            'rows' => $rows,
            'tableConfig' => $tableConfig,
        ];
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    @include('noerd::components.table.table-build', ['tableConfig' => $tableConfig])

</x-noerd::page>




