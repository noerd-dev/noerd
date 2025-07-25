<?php

use Noerd\Noerd\Models\UserRole;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Noerd\Noerd\Traits\NoerdTableTrait;

new class extends Component {

    use NoerdTableTrait;

    public const COMPONENT = 'user-roles-table';

    public function mount()
    {
        if (!Auth::user()->isAdmin()) {
            abort(401);
        }

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
            component: 'user-role-component',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function with(): array
    {
        $tenants = Auth::user()->adminTenants();
        $rows = UserRole::where('tenant_id', auth()->user()->selected_tenant_id)->orderBy('name')
            ->paginate(self::PAGINATION);

        return [
            'rows' => $rows,
        ];
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    @include('noerd::components.table.table-build',
      [
          'title' => __('Benutzerrollen'),
          'description' => '',
          'newLabel' => __('Neue Benutzerrolle'),
          'redirectAction' => '',
          'table' => [
              [
                  'width' => 10,
                  'field' => 'name',
                  'label' => __('Name'),
                  'readOnly' => false,
              ],
                 [
                  'width' => 10,
                  'field' => 'description',
                  'label' => __('Description'),
                  'readOnly' => false,
              ],
          ],
      ])

</x-noerd::page>
