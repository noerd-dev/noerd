<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Nywerk\Customer\Models\Customer;
use Nywerk\Liefertool\Traits\OnboardingTrait;
use Nywerk\Order\Models\Order;
use Nywerk\Order\Repositories\OrderRepository;

new class extends Component {

    use OnboardingTrait;

    public function mount()
    {
        $this->openOnboardingModal(auth()->user()->selectedTenant());
    }

    public function with()
    {
        $orderRepository = app()->make(OrderRepository::class);

        $customersCount = Customer::where('tenant_id', Auth::user()->selected_tenant_id)->count();
        $ordersCount = Order::where('tenant_id', Auth::user()->selected_tenant_id)
            ->where('delivery_time', '>=', date('Y-m-d'))
            ->count();
        $openOrdersCount = Order::where('tenant_id', Auth::user()->selected_tenant_id)
            ->where('status', 0)
            ->count();
        $ordersTotal = Order::where('tenant_id', Auth::user()->selected_tenant_id)
            ->count();
        $pickingCount = $orderRepository->getPickingPositionsCanteen()->count();
        $restaurant = \Nywerk\Noerd\Models\Tenant::where('id', Auth::user()->selected_tenant_id)->first();

        return [
            'customersCount' => $customersCount,
            'ordersCount' => $ordersCount,
            'ordersTotal' => $ordersTotal,
            'openOrdersCount' => $openOrdersCount,
            'pickingCount' => $pickingCount,
            'menuUrl' => $restaurant->domain,
        ];
    }

} ?>

<div class="max-w-4xl mx-auto">
    <div class="mb-12">
        <x-noerd::dashboard-card icon="cart" title="Zum Shop" external="{{$menuUrl}}" background="bg-green-50"/>
    </div>

    <div class="mb-12">
        <div class="font-semibold text-sm border-b pb-2">
            {{__('Tasks')}}
        </div>
        <div class="flex">
            <x-noerd::dashboard-card icon="cart" title="Nicht akzeptierte Bestellungen" :value="$openOrdersCount"
                              :arguments="['statusFilter' => 0] ?? []"

            <x-noerd::dashboard-card icon="cart" title="Kantine-Vorbestellungen" :value="$pickingCount"
                              component="canteen::livewire.canteen-preorders-table"/>
        </div>
    </div>

    <div class="mb-12">
        <div class="font-semibold text-sm border-b pb-2">
            Erstellen
        </div>
        <div class="flex">
            <x-noerd::dashboard-card icon="cart" title="Neue Bestellung" :value="$ordersTotal"
                              component="order-component"/>
            <x-noerd::dashboard-card icon="customer" title="Neuer Kunde" :value="$customersCount"
                              component="customer-component"/>
        </div>
    </div>

</div>
