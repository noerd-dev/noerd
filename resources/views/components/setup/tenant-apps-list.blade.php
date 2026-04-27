<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\TenantApp;

new class extends Component {
    public array $assignedApps = [];
    public array $availableApps = [];

    public function mount(): void
    {
        if (! config('noerd.features.multi_tenant')) {
            abort(404);
        }

        if (! Auth::user()->isSuperAdmin()) {
            abort(403);
        }

        $this->loadApps();
    }

    public function toggleApp(int $appId): void
    {
        $tenant = TenantHelper::getSelectedTenant();
        $assignedIds = $tenant->tenantApps()->pluck('tenant_apps.id')->toArray();

        if (in_array($appId, $assignedIds)) {
            $tenant->tenantApps()->detach($appId);
        } else {
            $maxSort = $tenant->tenantApps()->max('sort_order') ?? -1;
            $tenant->tenantApps()->attach($appId, ['sort_order' => $maxSort + 1]);
        }

        $this->loadApps();
    }

    public function appSort(int $appId, int $newPosition): void
    {
        $tenant = TenantHelper::getSelectedTenant();
        $apps = $tenant->tenantApps()->get();

        $loop = 0;
        foreach ($apps as $app) {
            if ($newPosition === $loop) {
                $loop++;
            }
            if ($app->id === $appId) {
                $tenant->tenantApps()->updateExistingPivot($app->id, ['sort_order' => $newPosition]);
            } else {
                $tenant->tenantApps()->updateExistingPivot($app->id, ['sort_order' => $loop++]);
            }
        }

        $this->loadApps();
    }

    public function toggleHidden(int $appId): void
    {
        $tenant = TenantHelper::getSelectedTenant();
        $current = $tenant->tenantApps()->where('tenant_apps.id', $appId)->first();

        if ($current) {
            $tenant->tenantApps()->updateExistingPivot($appId, [
                'is_hidden' => ! $current->pivot->is_hidden,
            ]);
        }

        $this->loadApps();
    }

    private function loadApps(): void
    {
        $tenant = TenantHelper::getSelectedTenant();
        $assignedIds = $tenant->tenantApps()->pluck('tenant_apps.id')->toArray();

        $this->assignedApps = $tenant->tenantApps()->get()->map(fn ($app) => [
            'id' => $app->id,
            'title' => $app->title,
            'icon' => $app->icon,
            'name' => $app->name,
            'is_hidden' => (bool) $app->pivot->is_hidden,
        ])->toArray();

        $this->availableApps = TenantApp::where('is_active', true)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('title')
            ->get()
            ->map(fn ($app) => [
                'id' => $app->id,
                'title' => $app->title,
                'icon' => $app->icon,
                'name' => $app->name,
            ])->toArray();
    }
}; ?>

<x-noerd::page>

    <x-slot:header>
        <x-noerd::modal-title>
            {{ __('Assigned Apps') }}
        </x-noerd::modal-title>
    </x-slot:header>

    <div class="max-w-3xl py-8">

        @if(count($assignedApps) > 0)
            <div x-sort="$wire.appSort($item, $position)" class="space-y-2">
                @foreach($assignedApps as $app)
                    <div x-sort:item="{{ $app['id'] }}" wire:key="assigned-{{ $app['id'] }}" @class(['bg-gray-100 rounded-lg p-4 flex items-center gap-4', 'opacity-50' => $app['is_hidden']])>
                        <a href="#/" class="cursor-grab active:cursor-grabbing">
                            <img alt="" width="20" src="/svg/drag.svg">
                        </a>

                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            @if($app['icon'])
                                <div class="w-6 h-6 shrink-0">
                                    <x-noerd::app-icon :icon="$app['icon']" class="stroke-black border-transparent" />
                                </div>
                            @endif
                            <span class="font-medium truncate">{{ $app['title'] }}</span>
                        </div>

                        <x-noerd::button variant="icon" :icon="$app['is_hidden'] ? 'eye-slash' : 'eye'" wire:click="toggleHidden({{ $app['id'] }})" wire:confirm="{{ $app['is_hidden'] ? __('Are you sure you want to make this app visible?') : __('Are you sure you want to hide this app?') }}" class="shrink-0"/>
                        <x-noerd::button variant="icon" icon="x-mark" wire:click="toggleApp({{ $app['id'] }})" wire:confirm="{{ __('Are you sure you want to remove this app?') }}" class="text-red-500! shrink-0"/>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-gray-500 italic mb-4">{{ __('No apps assigned') }}</div>
        @endif

        <div class="mt-10 mb-6">
            <div class="text-lg font-semibold">{{ __('Available Apps') }}</div>
        </div>

        @if(count($availableApps) > 0)
            <div class="space-y-2">
                @foreach($availableApps as $app)
                    <div wire:key="available-{{ $app['id'] }}" class="bg-gray-50 rounded-lg p-4 flex items-center gap-4">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            @if($app['icon'])
                                <div class="w-6 h-6 shrink-0">
                                    <x-noerd::app-icon :icon="$app['icon']" class="stroke-black border-transparent" />
                                </div>
                            @endif
                            <span class="font-medium truncate">{{ $app['title'] }}</span>
                        </div>

                        <x-noerd::button variant="icon" icon="plus" wire:click="toggleApp({{ $app['id'] }})" wire:confirm="{{ __('Are you sure you want to install this app?') }}" class="text-green-600! shrink-0"/>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-gray-500 italic">{{ __('No more apps available') }}</div>
        @endif
    </div>

</x-noerd::page>
