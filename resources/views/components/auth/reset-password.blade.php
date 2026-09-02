<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Helpers\NoerdAuth;

new #[Layout('noerd::layouts.auth')] class extends Component {
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
        $this->email = (string) request()->input('email', '');
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

        $status = NoerdAuth::broker()->reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user): void {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __($status));
            return;
        }

        Session::flash('status', __($status));
        $this->redirectRoute('noerd.login', navigate: true);
    }
}; ?>

<x-noerd::auth-shell :title="__('Reset password')" :description="__('Enter your new password below')">
    <form wire:submit="resetPassword" class="space-y-6">
        {{-- Email Address --}}
        <x-noerd::forms.input name="email" type="email" label="{{ __('Email address') }}" />

        {{-- Password --}}
        <x-noerd::forms.input name="password" type="password" label="{{ __('Password') }}" />

        {{-- Confirm Password --}}
        <x-noerd::forms.input name="password_confirmation" type="password" label="{{ __('Confirm password') }}" />

        {{-- Submit Button --}}
        <div>
            <x-noerd::button type="submit" class="w-full justify-center">
                {{ __('Reset password') }}
            </x-noerd::button>
        </div>
    </form>
</x-noerd::auth-shell>
