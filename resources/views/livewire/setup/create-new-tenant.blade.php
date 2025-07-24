<?php

use Nywerk\Noerd\Models\Profile;
use Nywerk\Noerd\Models\Tenant;
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
            $copyUserRole = new \Nywerk\Noerd\Models\UserRole();
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
            {{ __('Neuen Mandanten erstellen') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Ein neuer Mandant enthält seine eigenen Stamm- und Bewegungsdaten, kann jedoch mit den gleichen Benutzern verwaltet werden.") }}
        </p>
    </header>

    <form wire:submit="createTenant" class="mt-6 space-y-6">
        <div>
            <x-noerd::input-label for="name" :value="__('Name neuer Mandant')"/>
            <x-noerd::text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required
                          autofocus autocomplete="name"/>
            <x-noerd::input-error class="mt-2" :messages="$errors->get('name')"/>
        </div>

        <div class="flex items-center gap-4">
            <x-noerd::primary-button>{{ __('Neuen Mandanten erstellen') }}</x-noerd::primary-button>
        </div>
    </form>
</section>
