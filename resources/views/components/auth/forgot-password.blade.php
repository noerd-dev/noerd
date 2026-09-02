<?php

use Livewire\Attributes\Layout;
use Livewire\Component;
use Noerd\Helpers\NoerdAuth;

new #[Layout('noerd::layouts.auth')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        NoerdAuth::broker()->sendResetLink(['email' => $this->email]);

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}; ?>

<x-noerd::auth-shell :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')">
    <form wire:submit="sendPasswordResetLink" class="space-y-6">
        {{-- Email Address --}}
        <x-noerd::forms.input name="email" type="email" label="{{ __('Email address') }}" />

        {{-- Submit Button --}}
        <div>
            <x-noerd::button type="submit" class="w-full justify-center">
                {{ __('Email password reset link') }}
            </x-noerd::button>
        </div>
    </form>

    <p class="mt-10 text-center text-sm/6 text-gray-500">
        <a href="{{ route('noerd.login') }}" wire:navigate class="font-semibold">
            {{ __('Back to login') }}
        </a>
    </p>
</x-noerd::auth-shell>
