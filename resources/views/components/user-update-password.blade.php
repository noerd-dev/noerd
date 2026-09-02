<?php

use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\NoerdUser;

new class extends Component {

    #[Locked]
    public ?int $userId = null;

    public string $password = '';
    public string $password_confirmation = '';

    public bool $showSuccessIndicator = false;

    /**
     * Whether the current user may set this account's password. Drives BOTH the
     * rendering (an unauthorized viewer simply sees no form) and the action
     * guard below — deliberately not an abort() on render, so an admin opening
     * a user who is not in their tenants still gets the rest of the editor.
     */
    #[Computed]
    public function canSetPassword(): bool
    {
        $admin = NoerdAuth::user();

        if (! $admin?->isAdmin()) {
            return false;
        }

        if (! $this->userId || $admin->isSuperAdmin() || $this->userId === (int) $admin->id) {
            return true;
        }

        return NoerdUser::whereKey($this->userId)
            ->whereHas('tenants', fn($query) => $query->whereIn('tenants.id', $admin->adminTenants()->pluck('tenants.id')))
            ->exists();
    }

    public function updatePassword(): void
    {
        // Re-authorized on the action, not only on mount: #[Locked] rejects a
        // client UPDATE of $userId but does NOT protect the MOUNT arguments,
        // which the modal stack and the generic component page take from the
        // client. Without this check any authenticated user could mount this
        // component for a foreign id and overwrite that account's password.
        $this->authorizeTarget();

        $this->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = NoerdUser::findOrFail($this->userId);
        $user->password = bcrypt($this->password);
        $user->save();

        $this->showSuccessIndicator = true;
    }

    /**
     * Only an admin may set a password, and only for a user who belongs to a
     * tenant that admin actually administers.
     */
    private function authorizeTarget(): void
    {
        abort_unless($this->canSetPassword, 403);
    }
}; ?>

<section @class(['hidden' => ! $this->canSetPassword])>
    <header>
        <div class="text-lg font-medium text-gray-900">
            {{ __('Set Password') }}
        </div>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Overwrites the user's password. Can only be set by administrators.") }}
        </p>
    </header>

    <form wire:submit="updatePassword" class="mt-6 space-y-6">
        {{-- Password --}}
        <div>
            <x-noerd::input-label for="password" :value="__('Password')" />
            <x-noerd::text-input
                wire:model="password"
                id="password"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="{{ __('Password') }}"
                class="mt-1 block w-full"
            />
            <x-noerd::input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        {{-- Confirm Password --}}
        <div>
            <x-noerd::input-label for="password_confirmation" :value="__('Confirm password')" />
            <x-noerd::text-input
                wire:model="password_confirmation"
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="{{ __('Confirm password') }}"
                class="mt-1 block w-full"
            />
            <x-noerd::input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-noerd::button>{{ __('Save password') }}</x-noerd::button>

            <div x-show="$wire.showSuccessIndicator"
                 x-transition.out.opacity.duration.1000ms
                 x-effect="if($wire.showSuccessIndicator) setTimeout(() => $wire.showSuccessIndicator = false, 3000)"
                 class="flex mt-2 mr-2">
                <div class="flex ml-auto">
                    <div class="shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd"
                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">
                            {{__('Successfully saved')}}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</section>
