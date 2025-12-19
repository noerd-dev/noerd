<?php

use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public bool $showSuccess = false;

    public function createTenant()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:50', 'min:3'],
        ]);

        $tenant = new Tenant();
        $tenant->name = $this->name;
        $tenant->uuid = Str::uuid();
        $tenant->save();

        $profile = new Profile();
        $profile->key = 'USER';
        $profile->name = 'User';
        $profile->tenant_id = $tenant->id;
        $profile->save();

        $profile = new Profile();
        $profile->key = 'ADMIN';
        $profile->name = 'Admin';
        $profile->tenant_id = $tenant->id;
        $profile->save();

        // Default also admin
        $tenant->users()->attach(auth()->user()->id, [
            'profile_id' => $profile->id,
        ]);

        $apps = auth()->user()->selectedTenant()?->tenantApps;
        foreach ($apps as $app) {
            $tenant->tenantApps()->attach($app->id);
        }

        // copy all profiles (change later with app installation)
        $userRoles = auth()->user()->selectedTenant()?->userRoles;
        foreach ($userRoles as $userRole) {
            $copyUserRole = new \Noerd\Noerd\Models\UserRole();
            $copyUserRole->key = $userRole->key;
            $copyUserRole->name = $userRole->name;
            $copyUserRole->description = $userRole->description;
            $copyUserRole->tenant_id = $tenant->id;
            $copyUserRole->save();
        }

        $user = Auth::user();
        $user->selected_tenant_id = $tenant->id;
        $user->save();

        $this->showSuccess = true;
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Create New Tenant') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("A new tenant contains its own master and transactional data, but can be managed with the same users.") }}
        </p>
    </header>

    <form wire:submit="createTenant" class="mt-6 space-y-6">
        <div>
            <x-noerd::input-label for="name" :value="__('New Tenant Name')"/>
            <x-noerd::text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required
                          autofocus autocomplete="name"/>
            <x-noerd::input-error class="mt-2" :messages="$errors->get('name')"/>
        </div>

        <div class="flex items-center gap-4">
            <x-noerd::primary-button>{{ __('Neuen Mandanten erstellen') }}</x-noerd::primary-button>
        </div>
    </form>

    @if($showSuccess)
        <div class="rounded-md bg-green-50 p-4 mt-6"
             x-init="setTimeout(() => { window.location.href = '/' }, 2000)">
            <div class="flex">
                <div class="shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                              clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">
                        {{ __('Tenant successfully created. Redirecting...') }}
                    </p>
                </div>
            </div>
        </div>
    @endif
</section>
