<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('noerd::components.layouts.auth')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink(['email' => $this->email]);

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-noerd::auth-header :title="__('Forgot password')"
                          :description="__('Enter your email to receive a password reset link')"/>

    <!-- Session Status -->
    <x-noerd::auth-session-status class="text-center" :status="session('status')"/>

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-6">
        <!-- Email Address -->
        <x-noerd::forms.input name="email" type="email" label="{{ __('Email address') }}" />

        <div>
            <x-noerd::primary-button type="submit" class="w-full justify-center">
                {{ __('Email password reset link') }}
            </x-noerd::primary-button>
        </div>
    </form>

    <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
        <a href="{{ route('login') }}" wire:navigate class="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
            {{ __('Back to login') }}
        </a>
    </div>
</div>
