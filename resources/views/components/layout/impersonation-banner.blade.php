<?php

use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Support\Impersonation;

new class extends Component {
    #[Locked]
    public bool $isImpersonating = false;

    #[Locked]
    public string $userName = '';

    public function mount(): void
    {
        if (Impersonation::active()) {
            $this->isImpersonating = true;
            $this->userName = NoerdAuth::user()?->name ?? '';
        }
    }

    public function stopImpersonating(): void
    {
        Impersonation::stop();

        $this->redirect('/');
    }
}; ?>

<div
    x-data="{ isImpersonating: {{ $isImpersonating ? 'true' : 'false' }} }"
    x-init="document.documentElement.style.setProperty('--impersonation-banner-height', isImpersonating ? '36px' : '0px')"
>
    @if($isImpersonating)
        <div class="fixed top-[var(--environment-banner-height,0px)] left-0 w-full z-50 bg-yellow-500 text-yellow-900">
            <div class="px-4 py-2 text-center text-sm font-medium flex items-center justify-center relative">
                <div class="flex-1 text-center">
                    {{ __('You are logged in as :name', ['name' => $userName]) }}
                </div>

                <button
                    wire:click="stopImpersonating"
                    type="button"
                    class="absolute right-4 px-3 py-0.5 text-xs font-semibold bg-yellow-900 text-yellow-100 rounded hover:bg-yellow-800 transition-colors"
                >
                    {{ __('Return to your account') }}
                </button>
            </div>
        </div>
    @endif
</div>
