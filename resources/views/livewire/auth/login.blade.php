<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('noerd::components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        Auth::user()->update(['last_login_at' => now()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email) . '|' . request()->ip());
    }
}; ?>

<div class="flex min-h-screen items-stretch">
    <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
        <div class="mx-auto w-full max-w-sm lg:w-96">
            <div>
                <x-noerd::application-logo class="h-10 w-auto" />
                <h2 class="mt-8 text-2xl/9 font-bold tracking-tight text-gray-900">
                    {{ __('Log in to your account') }}
                </h2>
                <p class="mt-2 text-sm/6 text-gray-500">
                    {{ __('Enter your email and password below to log in') }}
                </p>
            </div>

            <!-- Session Status -->
            <x-noerd::auth-session-status class="mt-6" :status="session('status')" />

            <div class="mt-10">
                <form wire:submit="login" class="space-y-6">
                    <!-- Email Address -->
                    <x-noerd::forms.input name="email" type="email" label="{{ __('Email address') }}" />

                    <!-- Password -->
                    <div>
                        <div class="flex items-center justify-between">
                            <x-noerd::input-label for="password" :value="__('Password')" />
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" wire:navigate class="text-sm font-semibold">
                                    {{ __('Forgot password?') }}
                                </a>
                            @endif
                        </div>
                        <x-noerd::forms.input name="password" type="password" />
                    </div>

                    <!-- Remember Me -->
                    <x-noerd::forms.checkbox name="remember" label="{{ __('Remember me') }}" />

                    <!-- Submit Button -->
                    <div>
                        <x-noerd::buttons.primary type="submit" class="w-full justify-center">
                            {{ __('Log in') }}
                        </x-noerd::buttons.primary>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="relative hidden w-0 flex-1 bg-black lg:block">
        @if(config('noerd.branding.auth_background_image'))
            <img src="{{ config('noerd.branding.auth_background_image') }}" alt="" class="absolute inset-0 size-full object-cover" />
        @endif
    </div>
</div>
