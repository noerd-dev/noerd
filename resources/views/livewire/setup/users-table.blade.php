<?php

use Nywerk\Noerd\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Nywerk\Noerd\Traits\NoerdTableTrait;

new class extends Component {

    use NoerdTableTrait;

    public const COMPONENT = 'users-table';

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
        $this->dispatch('set-app-id', ['id' => null]);

        $this->dispatch(
            event: 'noerdModal',
            component: 'user-component',
            source: self::COMPONENT,
            arguments: ['modelId' => $modelId, 'relationId' => $relationId],
        );
    }

    public function loginAsUser($userId)
    {
        if (!Auth::user()->isAdmin()) {
            abort(401);
        }

        $tenants = Auth::user()->adminTenants();
        $allowedUserIds = User::whereHas('tenants', function ($relationQuery) use ($tenants) {
            $relationQuery->whereIn('tenant_id', $tenants->pluck('id'));
        })->get()->pluck('id')->toArray();

        if (in_array($userId, $allowedUserIds) === false) {
            abort(401);
        }
        Auth::loginUsingId($userId);

        return redirect('/');
    }

    public function removeFromTenant($id)
    {
        dd('TODO');
    }

    public function with(): array
    {
        $tenants = Auth::user()->adminTenants();
        $rows = User::whereHas('tenants', function ($relationQuery) use ($tenants) {
            $relationQuery->whereIn('tenant_id', $tenants->pluck('id'));
        })->orderBy($this->sortField, $this->sortAsc ? 'asc' : 'desc')
            ->when($this->search, function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->paginate(self::PAGINATION);

        return [
            'rows' => $rows,
        ];
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    @include('noerd::components.table.table-build',
      [
          'title' => __('Users'),
          'description' => '',
          'newLabel' => __('New User'),
          'redirectAction' => '',
          'table' => [
              [
                  'width' => 10,
                  'field' => 'email',
                  'label' => __('E-Mail'),
                  'readOnly' => false,
              ],
              [
                  'width' => 10,
                  'field' => 'name',
                  'label' => __('Name'),
                  'readOnly' => false,
              ],
              [
                  'width' => 3,
                  'field' => 'action',
                  'actions' => [
                      [
                          'label' => __('Als Benutzer anmelden'),
                          'heroicon' => 'user',
                          'action' => 'loginAsUser',
                          'confirm' => 'Möchtest Du Dich wirklich als dieser Benutzer anmelden?',
                      ],
                      /*
                      [
                          'label' => __('Benutzer entfernen'),
                          'heroicon' => 'minus',
                          'action' => 'removeFromTenant',
                           'confirm' => 'Möchtest Du diesen Benutzer wirklich aus dem Mandanten entfernen?',
                      ],
                      */
                  ],
                  'readOnly' => false,
              ],
          ],
      ])

</x-noerd::page>
