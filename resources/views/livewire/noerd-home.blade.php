<?php

use Livewire\Volt\Component;
use Illuminate\Support\Str;

new class extends Component {

    // TODO this should be implemented in a middleware
    public function mount(): void
    {
        if (!auth()->user()->selected_tenant_id) {
            $user = auth()->user();
            $user->selected_tenant_id = $user->tenants->first()?->id;
            $user->save();
        }

        // TOOD also check if user is not anymore assigned to the tenant
    }

} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>Home</x-noerd::modal-title>
    </x-slot:header>

    <div class="mb-12">
        <div class="flex flex-wrap">
            @foreach(auth()->user()->selectedTenant()?->tenantApps ?? [] as $tenantApp)
                <a @if($tenantApp->is_active)
                       href="{{ route($tenantApp->route) }}"
                   wire:navigate
                   @else
                       href="#/"
                    @endif
                    @class([
                        'bg-white border border-gray-300 hover:bg-gray-50 w-36 h-36 mr-6 mt-6 flex p-2 py-4 text-sm text-center rounded-lg items-center justify-center',
                        'opacity-50 cursor-not-allowed' => !$tenantApp->is_active
                    ])>
                    <div class="m-auto">
                        <div class="inline-block mb-2">
                            <x-dynamic-component
                                class="{{ session('currentApp') === $tenantApp->name  ? 'stroke-brand-highlight border-brand-highlight' :
                                'stroke-black border-transparent hover:!border-gray-500' }}
                                border-l-2"
                                :component="'noerd::'.$tenantApp->icon"/>
                        </div>

                        <div @class([
                            'text-gray-500 w-full',
                            'text-gray-400' => !$tenantApp->is_active
                        ])>
                            {{ $tenantApp->title }}
                        </div>

                        @if(!$tenantApp->is_active)
                            <div class="text-xs text-gray-400 mt-1">
                                Inaktiv
                            </div>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</x-noerd::page>
