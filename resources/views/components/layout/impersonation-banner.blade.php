<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Noerd\Helpers\TenantHelper;

new class extends Component {
    public bool $isImpersonating = false;
    public string $userName = '';

    public function mount(): void
    {
        if (session('impersonating_from')) {
            $this->isImpersonating = true;
            $this->userName = Auth::user()?->name ?? '';
        }
    }

    public function stopImpersonating()
    {
        $originalUserId = session('impersonating_from');
        session()->forget('impersonating_from');

        // Clear tenant session so InitializeTenantSession will set the correct tenant
        TenantHelper::clear();

        Auth::loginUsingId($originalUserId);

        return redirect('/');
    }
}; ?>

<div
    x-data="{ isImpersonating: {{ $isImpersonating ? 'true' : 'false' }} }"
    x-init="document.documentElement.style.setProperty('--impersonation-banner-height', isImpersonating ? '36px' : '0px')"
>
    @if($isImpersonating)
        <div class="fixed top-0 left-0 w-full z-50 bg-yellow-500 text-yellow-900">
            <div class="px-4 py-2 text-center text-sm font-medium flex items-center justify-center relative">
                <div class="flex-1 text-center">
                    {{ __('noerd_impersonating_banner', ['name' => $userName]) }}
                </div>

                <button
                    wire:click="stopImpersonating"
                    type="button"
                    class="absolute right-4 px-3 py-0.5 text-xs font-semibold bg-yellow-900 text-yellow-100 rounded hover:bg-yellow-800 transition-colors"
                >
                    {{ __('noerd_stop_impersonating') }}
                </button>
            </div>
        </div>
    @endif
</div>
