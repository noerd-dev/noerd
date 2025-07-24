<?php

use Nywerk\Noerd\Models\Tenant;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {

    public $selectedClientId;
    public $openOrders;
    public $domain;

    #[On('echo-private:orders.{selectedClientId},OrderCreated')]
    public function mount()
    {
        $this->selectedClientId = auth()->user()->selected_tenant_id ?? 0;
        if (auth()->user()->can('orders', Tenant::class)) {
            $this->openOrders = auth()->user()->selectedTenant()?->openOrders()->count();
        }
        $this->domain = auth()->user()->selectedTenant()?->domain;
    }

    #[On('refreshOrderCount')]
    public function refreshOrderCount()
    {
        if (auth()->user()->can('orders', Tenant::class)) {

            $this->openOrders = auth()->user()->selectedTenant()?->openOrders()->count();
        }
    }
} ?>

<div class="flex gap-x-2">
    @can('orders', Tenant::class)
        <div class="hidden lg:flex">
            <button
                wire:click="$dispatch('noerdModal', {component: 'orders-table', arguments: {{json_encode(['statusFilter' => 0] ?? [])}}})"
                @class([
                    'bg-gray-100 rounded-lg my-auto text-sm px-3 py-1',
                    'bg-red-300' => $openOrders > 0,
                ])
            >
                {{__('Open Orders')}}: {{$openOrders}}
            </button>
        </div>
    @endcan

    @can('orders', Tenant::class)
        <div class="hidden lg:flex">
            <a class="flex" target="_blank" href="{{$domain}}">
                <button
                    @class([
                        'bg-gray-100 rounded-lg my-auto text-sm px-3 py-1',
                    ])
                >
                    @can('justMenuModule', Tenant::class)
                        {{__('To Menu')}}
                    @else
                        {{__('To Shop')}}
                    @endif
                </button>
            </a>
        </div>
    @endcan

    @can('times', Tenant::class)
        <livewire:next-slot
            :tenant="Tenant::find(auth()->user()->selected_tenant_id)"></livewire:next-slot>
    @endcan
</div>
