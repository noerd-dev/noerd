<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Noerd\Noerd\Models\Profile;

new class extends Component {

    #[Computed]
    public function userTenantAccess(): array
    {
        $user = Auth::user();
        $tenantAccess = [];
        
        foreach ($user->tenants as $tenant) {
            $profileId = $tenant->pivot->profile_id;
            $profile = Profile::find($profileId);
            
            $tenantAccess[] = [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'profile_name' => $profile ? $profile->name : __('Unbekanntes Profil'),
                'profile_id' => $profileId,
            ];
        }
        
        return $tenantAccess;
    }

}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Mandanten-Zugriff') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Overview of your current access rights to tenants and assigned roles.') }}
        </p>
    </header>

    <div class="mt-6 space-y-6">
        <div>
            <div class="pb-4 font-medium text-gray-700">
                {{ __('Access to the following tenants:') }}
            </div>
            
            @if(count($this->userTenantAccess) > 0)
                <div class="pl-2 space-y-3">
                    @foreach($this->userTenantAccess as $tenant)
                        <div class="max-w-2xl">
                            <div class="relative flex items-center py-2 px-4 bg-gray-50 rounded-lg border">
                                <div class="flex items-center">
                                    <!-- Read-only indicator instead of checkbox -->
                                    <div class="flex h-6 items-center">
                                        <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <span class="ml-3 text-sm font-medium text-gray-900">
                                        {{ $tenant['name'] }}
                                    </span>
                                </div>

                                <div class="ml-auto">
                                    <!-- Read-only display instead of select -->
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                        {{ $tenant['profile_name'] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="pl-2">
                    <div class="text-sm text-gray-500 bg-gray-50 p-4 rounded-lg">
                        {{ __('You currently have no access to tenants.') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>