<?php

use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {

    #[Locked]
    public $userId;

    public string $password = '';
    public string $password_confirmation = '';

    public bool $showSuccessIndicator = false;

    public function updatePassword()
    {
        $this->validate([
            'password' => ['required', 'string', 'confirmed'],
        ]);

        $user = \Noerd\Noerd\Models\User::find($this->userId);
        $user->password = bcrypt($this->password);
        $user->save();

        $this->showSuccessIndicator = true;
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Set Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Overwrites the user's password. Can only be set by administrators.") }}
        </p>
    </header>

    <form wire:submit="updatePassword" class="mt-6 space-y-6">
        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Password') }}</label>
            <input
                wire:model="password"
                id="password"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="{{ __('Password') }}"
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
            />
            @error('password')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Confirm password') }}</label>
            <input
                wire:model="password_confirmation"
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="{{ __('Confirm password') }}"
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
            />
            @error('password_confirmation')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-4">
            <x-noerd::primary-button>{{ __('Passwort speichern') }}</x-noerd::primary-button>

            <div x-show="$wire.showSuccessIndicator"
                 x-transition.out.opacity.duration.1000ms
                 x-noerd::effect="if($wire.showSuccessIndicator) setTimeout(() => $wire.showSuccessIndicator = false, 3000)"
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
