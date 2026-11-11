<?php

use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\NoerdUser;
use Noerd\Support\Impersonation;

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
     * a user still gets the rest of the editor.
     *
     * The password belongs to the account, not to a tenant: a super admin sets
     * it, or the person on their own record — never a tenant admin, and never
     * an administrator signed in as the person.
     */
    #[Computed]
    public function canSetPassword(): bool
    {
        $admin = NoerdAuth::user();

        if (! $admin?->isAdmin() || ! $this->userId) {
            return false;
        }

        return $admin->isSuperAdmin()
            || ($this->userId === (int) $admin->id && ! Impersonation::active());
    }

    /**
     * The account whose password is set — shown above the form, so an
     * administrator sees whose account they are about to change. Only
     * resolved for a viewer who may set the password.
     */
    #[Computed]
    public function targetUser(): ?NoerdUser
    {
        return $this->canSetPassword ? NoerdUser::find($this->userId) : null;
    }

    /**
     * Fills both fields with a fresh, strong password on request. Never on
     * mount: the administrator decides whether a generated password is used.
     */
    public function generatePassword(): void
    {
        $this->authorizeTarget();

        $this->password = Str::password(16);
        $this->password_confirmation = $this->password;
        $this->resetValidation();
    }

    public function updatePassword(): void
    {
        // Re-authorized on the action, not only on mount: #[Locked] rejects a
        // client UPDATE of $userId but does NOT protect the MOUNT arguments,
        // which the modal stack takes from the client. Without this check any authenticated user could mount this
        // component for a foreign id and overwrite that account's password.
        $this->authorizeTarget();

        $this->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = NoerdUser::findOrFail($this->userId);
        $user->password = bcrypt($this->password);
        $user->save();

        $this->reset('password', 'password_confirmation');
        $this->showSuccessIndicator = true;
    }

    /**
     * Only a super admin — or the person on their own record — sets a password.
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

    @if ($this->targetUser)
        <dl class="mt-4 divide-y divide-gray-100 rounded-sm border border-gray-200 bg-gray-50 text-sm">
            <div class="flex gap-4 px-3 py-2">
                <dt class="w-24 shrink-0 text-gray-500">{{ __('Name') }}</dt>
                <dd class="min-w-0 truncate font-medium text-gray-900">{{ $this->targetUser->name }}</dd>
            </div>
            <div class="flex gap-4 px-3 py-2">
                <dt class="w-24 shrink-0 text-gray-500">{{ __('Email') }}</dt>
                <dd class="min-w-0 truncate font-medium text-gray-900">{{ $this->targetUser->email }}</dd>
            </div>
        </dl>
    @endif

    <form wire:submit="updatePassword" class="mt-6 space-y-6" x-data="{ visible: false, copied: false }">
        {{-- Password --}}
        <div>
            <x-noerd::input-label for="password" :value="__('Password')" />
            <div class="flex items-center gap-2">
                <div class="relative min-w-0 flex-1">
                    <x-noerd::text-input
                        wire:model="password"
                        id="password"
                        x-bind:type="visible ? 'text' : 'password'"
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="{{ __('Password') }}"
                        class="mt-1 block w-full pr-10! font-mono"
                    />
                    <button
                        type="button"
                        x-on:click="visible = ! visible"
                        x-bind:title="visible ? @js(__('Hide password')) : @js(__('Show password'))"
                        class="absolute inset-y-0 right-0 mt-1 flex cursor-pointer items-center px-3 text-gray-500 hover:text-gray-700"
                    >
                        <x-heroicons::outline.eye x-show="! visible" class="h-4 w-4" />
                        <x-heroicons::outline.eye-slash x-show="visible" x-cloak class="h-4 w-4" />
                    </button>
                </div>
                <x-noerd::button
                    variant="secondary"
                    icon="arrow-path"
                    wire:click="generatePassword"
                    wireTarget="generatePassword"
                    x-on:click="visible = true; copied = false"
                    class="mt-1 shrink-0"
                >
                    {{ __('Generate password') }}
                </x-noerd::button>
                <x-noerd::button
                    variant="secondary"
                    icon="clipboard-document"
                    x-show="$wire.password"
                    x-on:click="navigator.clipboard.writeText($wire.password); copied = true; setTimeout(() => copied = false, 2000)"
                    class="mt-1 shrink-0"
                >
                    <span x-show="! copied">{{ __('Copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                </x-noerd::button>
            </div>
            <x-noerd::input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        {{-- Confirm Password --}}
        <div>
            <x-noerd::input-label for="password_confirmation" :value="__('Confirm password')" />
            <x-noerd::text-input
                wire:model="password_confirmation"
                id="password_confirmation"
                x-bind:type="visible ? 'text' : 'password'"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="{{ __('Confirm password') }}"
                class="mt-1 block w-full font-mono"
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
