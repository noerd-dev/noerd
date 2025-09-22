<?php

use Noerd\Noerd\Models\Tenant;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {

    public $selectedClientId;
    public $openOrders;
    public $domain;
    public $websiteUrl;

    public function mount()
    {
        $this->selectedClientId = auth()->user()->selected_tenant_id ?? 0;
        if (auth()->user()->can('orders', Tenant::class)) {
            $this->openOrders = auth()->user()->selectedTenant()?->openOrders()->count();
        }
        $this->domain = auth()->user()->selectedTenant()?->domain;

        // Compute website URL for CMS access if available
        $hash = auth()->user()->selectedTenant()?->hash;
        if (auth()->user()->can('cms', Tenant::class) && !empty($hash)) {
            $this->websiteUrl = url('/index?hash=' . $hash);
        } else {
            $this->websiteUrl = null;
        }
    }

    public function refreshOrderCount()
    {
        if (auth()->user()->can('orders', Tenant::class)) {
            $this->openOrders = auth()->user()->selectedTenant()?->openOrders()->count();
        }
    }
} ?>

<div class="flex gap-x-2">
    @can('orders', Tenant::class)
        <div class="hidden lg:flex" wire:poll.15s="refreshOrderCount">
            <button
                wire:click="$dispatch('noerdModal', {component: 'orders-list', arguments: {{json_encode(['statusFilter' => 0] ?? [])}}})"
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

    @can('cms', Tenant::class)
        <div class="hidden lg:flex">
            <a class="flex" target="_blank" href="{{ $websiteUrl }}">
                <button
                    @class([
                        'bg-gray-100 rounded-lg my-auto text-sm px-3 py-1',
                    ])
                >
                    {{__('Zur Webseite')}}
                </button>
            </a>
        </div>
    @endcan

    @can('times', Tenant::class)
        <livewire:next-slot
            :tenant="Tenant::find(auth()->user()->selected_tenant_id)"></livewire:next-slot>
    @endcan
</div>
