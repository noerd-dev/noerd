<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Services\ComponentSlotsRegistry;
use Noerd\Support\LandingPage;

new #[Layout('noerd::layouts.auth')] class extends Component {
    /** The e-mail address of the account — or its username, while usernames are switched on. */
    public string $login = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * The further ways in a module put on the login page (single sign-on).
     *
     * @return list<string>
     */
    public function loginComponents(): array
    {
        return app(ComponentSlotsRegistry::class)->get(ComponentSlotsRegistry::AUTH_LOGIN);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function authenticate(): void
    {
        abort_unless(NoerdAuth::passwordLoginEnabled(), 404);

        $this->validate([
            'login' => NoerdAuth::usernameLoginEnabled() ? 'required|string|max:255' : 'required|string|email',
            'password' => 'required|string',
        ]);

        $this->ensureIsNotRateLimited();

        if (! NoerdAuth::guard()->attempt(['login' => $this->login, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->ipThrottleKey());

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirect(session()->pull('url.intended') ?? LandingPage::path(), navigate: false);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        // Per account and address (5), and per address across every account
        // (20) — the second one stops trying one password on many accounts.
        $key = match (true) {
            RateLimiter::tooManyAttempts($this->throttleKey(), 5) => $this->throttleKey(),
            RateLimiter::tooManyAttempts($this->ipThrottleKey(), 20) => $this->ipThrottleKey(),
            default => null,
        };

        if ($key === null) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'login' => __('auth.throttle', [
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
        return Str::transliterate(Str::lower(mb_trim($this->login)) . '|' . request()->ip());
    }

    protected function ipThrottleKey(): string
    {
        return 'noerd-login|' . request()->ip();
    }
}; ?>

@php
    $loginComponents = $this->loginComponents();
    $passwordLogin = NoerdAuth::passwordLoginEnabled();
    $usernameLogin = NoerdAuth::usernameLoginEnabled();
@endphp

<x-noerd::auth-shell
    :title="__('Log in to your account')"
    :description="match (true) {
        ! $passwordLogin => __('Choose how you want to log in'),
        $usernameLogin => __('Enter your email address or username and your password below to log in'),
        default => __('Enter your email and password below to log in'),
    }"
>
    @if ($loginComponents !== [])
        <div class="space-y-3">
            @foreach ($loginComponents as $loginComponent)
                <livewire:dynamic-component :component="$loginComponent" :key="$loginComponent" />
            @endforeach
        </div>
    @endif

    @if ($passwordLogin && $loginComponents !== [])
        <div class="relative my-6 flex items-center" aria-hidden="true">
            <div class="flex-grow border-t border-gray-200"></div>
            <span class="mx-3 text-xs text-gray-500">{{ __('or') }}</span>
            <div class="flex-grow border-t border-gray-200"></div>
        </div>
    @endif

    @if ($passwordLogin)
    <form wire:submit="authenticate" class="space-y-6">
        {{-- Email address — or username, while usernames are switched on --}}
        @if ($usernameLogin)
            <x-noerd::forms.input name="login" type="text" label="{{ __('Email address or username') }}" />
        @else
            <x-noerd::forms.input name="login" type="email" label="{{ __('Email address') }}" />
        @endif

        {{-- Password --}}
        <div>
            <div class="flex items-center justify-between">
                <x-noerd::input-label for="password" :value="__('Password')" />
                @if (Route::has('noerd.password.request'))
                    <a href="{{ route('noerd.password.request') }}" wire:navigate class="text-sm font-semibold">
                        {{ __('Forgot password?') }}
                    </a>
                @endif
            </div>
            <x-noerd::forms.input name="password" type="password" />
        </div>

        {{-- Remember Me --}}
        <x-noerd::forms.checkbox name="remember" label="{{ __('Remember me') }}" />

        {{-- Submit Button --}}
        <div>
            <x-noerd::button type="submit" class="w-full justify-center">
                {{ __('Log in') }}
            </x-noerd::button>
        </div>
    </form>
    @endif
</x-noerd::auth-shell>
