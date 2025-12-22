<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('noerd::components.layouts.auth')] class extends Component {
    #[Locked]
    public string $token = '';

    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));
            return;
        }

        Session::flash('status', __($status));
        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="flex min-h-screen items-stretch">
    <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
        <div class="mx-auto w-full max-w-sm lg:w-96">
            <div>
                <x-noerd::application-logo class="h-10 w-auto" />
                <h2 class="mt-8 text-2xl/9 font-bold tracking-tight text-gray-900">
                    {{ __('Reset password') }}
                </h2>
                <p class="mt-2 text-sm/6 text-gray-500">
                    {{ __('Enter your new password below') }}
                </p>
            </div>

            <div class="mt-10">
                <form wire:submit="resetPassword" class="space-y-6">
                    <!-- Email Address -->
                    <x-noerd::forms.input name="email" type="email" label="{{ __('Email address') }}" />

                    <!-- Password -->
                    <x-noerd::forms.input name="password" type="password" label="{{ __('Password') }}" />

                    <!-- Confirm Password -->
                    <x-noerd::forms.input name="password_confirmation" type="password" label="{{ __('Confirm password') }}" />

                    <!-- Submit Button -->
                    <div>
                        <x-noerd::primary-button type="submit" class="w-full justify-center">
                            {{ __('Reset password') }}
                        </x-noerd::primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @if(config('noerd.auth.background_image'))
        <div class="relative hidden w-0 flex-1 lg:block">
            <img src="{{ config('noerd.auth.background_image') }}" alt="" class="absolute inset-0 size-full object-cover" />
        </div>
    @endif
</div>
