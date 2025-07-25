<?php

use Noerd\Noerd\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Nywerk\Customer\Models\Customer;
use Nywerk\Liefertool\Models\LiefertoolSettings;
use Nywerk\Liefertool\Services\ModesService;
use Nywerk\Liefertool\Traits\OnboardingTrait;
use Nywerk\Order\Models\Order;
use Nywerk\Order\Repositories\OrderRepository;

new class extends Component {

    use OnboardingTrait;

    #[Locked]
    public $clientId = null;

    public array $settings;

    public function mount()
    {
        $this->openOnboardingModal(auth()->user()->selectedTenant());
    }

    public function with()
    {
        $client = auth()->user()->selectedTenant();
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
        $pickingCount = $orderRepository->getPickingPositions(Auth::user()->selected_tenant_id)->count();
        $overduePayment = count($client->dueInvoices) > 0;

        return [
            'customersCount' => $customersCount,
            'ordersCount' => $ordersCount,
            'ordersTotal' => $ordersTotal,
            'openOrdersCount' => $openOrdersCount,
            'pickingCount' => $pickingCount,
            'menuUrl' => $client->domain,
            'overduePayment' => $overduePayment,
        ];
    }
} ?>

<div class="max-w-4xl mx-auto">
    @if($overduePayment)
        <div class="mb-12 mt-6 bg-red-100 rounded-sm p-4">
            <div>Überfällige Liefertool-Rechnung</div>
            <div class="mt-2">
                <a href="/noerd-invoices" class="text-blue-500 underline">Zur Rechnungsübersicht</a>
            </div>
        </div>
    @endif

    <div class="mb-12 flex">
        <x-noerd::dashboard-card icon="cart" title="Zum Shop" external="{{$menuUrl}}" background="bg-green-50"/>
    </div>

    <div class="mb-12">
        <div class="font-semibold text-sm border-b border-gray-300 pb-2">
            {{__('Tasks')}}
        </div>
        <div class="flex">
            <x-noerd::dashboard-card icon="cart" title="Nicht akzeptierte Bestellungen" :value="$openOrdersCount"
                              :arguments="['statusFilter' => 0] ?? []"
                              component="orders-table"/>
            <x-noerd::dashboard-card icon="cart" title="Kommissionierung" :value="$pickingCount"
                              component="picking-table"/>
        </div>
    </div>

    <div class="mb-12">
        <div class="font-semibold text-sm border-b border-gray-300 pb-2">
            Erstellen
        </div>
        <div class="flex">
            <x-noerd::dashboard-card icon="cart" title="Neue Bestellung" :value="$ordersTotal"
                              component="order-component"/>
            <x-noerd::dashboard-card icon="customer" title="Neuer Kunde" :value="$customersCount"
                              component="customer-component"/>
        </div>
    </div>

    {{--
   <div class="mb-12">
       <div class="font-semibold text-sm border-b pb-2">
           {{__('Open Orders')}}
       </div>
   </div>

   <livewire:orders-table statusFilter="0"/>
   --}}
</div>
