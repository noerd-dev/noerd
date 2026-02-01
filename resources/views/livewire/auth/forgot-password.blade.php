<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

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

<div class="flex min-h-screen items-stretch">
    <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
        <div class="mx-auto w-full max-w-sm lg:w-96">
            <div>
                <x-noerd::application-logo class="h-10 w-auto" />
                <h2 class="mt-8 text-2xl/9 font-bold tracking-tight text-gray-900">
                    {{ __('Forgot password') }}
                </h2>
                <p class="mt-2 text-sm/6 text-gray-500">
                    {{ __('Enter your email to receive a password reset link') }}
                </p>
            </div>

            <!-- Session Status -->
            <x-noerd::auth-session-status class="mt-6" :status="session('status')" />

            <div class="mt-10">
                <form wire:submit="sendPasswordResetLink" class="space-y-6">
                    <!-- Email Address -->
                    <x-noerd::forms.input name="email" type="email" label="{{ __('Email address') }}" />

                    <!-- Submit Button -->
                    <div>
                        <x-noerd::buttons.primary type="submit" class="w-full justify-center">
                            {{ __('Email password reset link') }}
                        </x-noerd::buttons.primary>
                    </div>
                </form>

                <p class="mt-10 text-center text-sm/6 text-gray-500">
                    <a href="{{ route('login') }}" wire:navigate class="font-semibold">
                        {{ __('Back to login') }}
                    </a>
                </p>
            </div>
        </div>
    </div>
    <div class="relative hidden w-0 flex-1 bg-black lg:block">
        @if(config('noerd.branding.auth_background_image'))
            <img src="{{ config('noerd.branding.auth_background_image') }}" alt="" class="absolute inset-0 size-full object-cover" />
        @endif
    </div>
</div>
