<?php

use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Noerd\Enums\Profile;
use Noerd\Helpers\NoerdAuth;

/**
 * Confirms the deletion of the authenticated user's own account with its
 * password. Opened from the profile page (profile.delete-user-form).
 */
new class extends Component {
    public string $password = '';

    public function deleteUser(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        $user = NoerdAuth::user();

        if (! $user || ! Hash::check($this->password, $user->password)) {
            $this->addError('password', __('This password does not match our records.'));

            return;
        }

        // A tenant must not lose its last administrator.
        foreach ($user->adminTenants as $tenant) {
            $otherAdmins = $tenant->users()
                ->wherePivot('profile_key', Profile::Admin->value)
                ->whereKeyNot($user->id)
                ->exists();

            if (! $otherAdmins) {
                $this->addError('password', __('You are the only administrator of :tenant. Assign another administrator first.', ['tenant' => $tenant->name]));

                return;
            }
        }

        NoerdAuth::guard()->logout();

        $user->tenants()->detach();
        $user->userSetting()->delete();
        $user->delete();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/');
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Are you sure you want to delete your account?') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="[]" :modelId="null" :showBlock="false">
        <x-slot:tab1>
            <form wire:submit="deleteUser">
                <p class="text-sm text-gray-600">
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                </p>

                <div class="mt-6">
                    <x-noerd::input-label for="password" :value="__('Password')" />
                    <x-noerd::text-input
                        wire:model="password"
                        id="password"
                        type="password"
                        autocomplete="current-password"
                        class="mt-1 block w-full"
                    />
                    <x-noerd::input-error :messages="$errors->get('password')" class="mt-2"/>
                </div>
            </form>
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <div class="ml-auto flex items-center gap-2">
            <x-noerd::button variant="secondary" wire:click="$dispatch('closeTopModal')">
                {{ __('Cancel') }}
            </x-noerd::button>
            <x-noerd::button variant="danger" :icon="false" wire:click="deleteUser">
                {{ __('Delete Account') }}
            </x-noerd::button>
        </div>
    </x-slot:footer>
</x-noerd::page>
