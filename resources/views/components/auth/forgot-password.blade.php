<?php

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
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

        // The broker throttles per account; this limit keeps one address from
        // sending reset mails to many accounts.
        $key = 'noerd-password-reset|' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($key),
                    'minutes' => ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ]);
        }

        RateLimiter::hit($key, 600);

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
