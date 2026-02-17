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

    private function loadApps(): void
    {
        $tenant = TenantHelper::getSelectedTenant();
        $assignedIds = $tenant->tenantApps()->pluck('tenant_apps.id')->toArray();

        $this->assignedApps = $tenant->tenantApps()->get()->map(fn ($app) => [
            'id' => $app->id,
            'title' => $app->title,
            'icon' => $app->icon,
            'name' => $app->name,
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

    <div class="max-w-3xl">
        <div class="mb-6">
            <h2 class="text-lg font-semibold">{{ __('noerd_label_assigned_apps') }}</h2>
        </div>

        @if(count($assignedApps) > 0)
            <div x-sort="$wire.appSort($item, $position)" class="space-y-2">
                @foreach($assignedApps as $app)
                    <div x-sort:item="{{ $app['id'] }}" wire:key="assigned-{{ $app['id'] }}" class="bg-gray-100 rounded-lg p-4 flex items-center gap-4">
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

                        <button wire:click="toggleApp({{ $app['id'] }})" class="shrink-0 text-red-500 hover:text-red-700 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-gray-500 italic mb-4">{{ __('noerd_label_no_assigned_apps') }}</div>
        @endif

        <div class="mt-10 mb-6">
            <h2 class="text-lg font-semibold">{{ __('noerd_label_available_apps') }}</h2>
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

                        <button wire:click="toggleApp({{ $app['id'] }})" class="shrink-0 text-green-600 hover:text-green-800 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-gray-500 italic">{{ __('noerd_label_no_available_apps') }}</div>
        @endif
    </div>

</x-noerd::page>
