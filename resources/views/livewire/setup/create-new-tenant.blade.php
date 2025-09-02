<?php

use Noerd\Noerd\Models\Profile;
use Noerd\Noerd\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';

    public function createTenant()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:50', 'min:3'],
        ]);

        $tenant = new Tenant();
        $tenant->name = $this->name;
        $tenant->hash = Str::uuid();
        $tenant->save();

        $profile = new Profile();
        $profile->key = 'USER';
        $profile->name = 'Benutzer';
        $profile->tenant_id = $tenant->id;
        $profile->save();

        $profile = new Profile();
        $profile->key = 'ADMIN';
        $profile->name = 'Administrator';
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

        return $this->redirect('/');
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
</section>
