<?php

use Livewire\Component;
use Noerd\Models\SetupLanguage;

new class extends Component
{
    public array $languages = [];

    /**
     * Mounted inside modal headers, which re-mount on every stack update —
     * so mount() only reads. The parent ensures the tenant's languages exist.
     */
    public function mount(): void
    {
        $this->languages = SetupLanguage::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order')
            ->get(['code', 'name'])
            ->toArray();
    }


    /**
     * The code comes from the client, so it is only honoured while it names one
     * of the tenant's ACTIVE languages — an unknown or deactivated code would
     * otherwise be written into the session and drive every translatable field.
     */
    public function setLanguage(string $code): void
    {
        if (! in_array($code, SetupLanguage::activeCodes(), true)) {
            return;
        }

        session([SetupLanguage::SESSION_KEY => $code]);
        $this->dispatch('setupLanguageChanged');
    }
} ?>

<div class="flex">
    @if(count($languages) > 1)
        <div class="ml-auto mr-6 my-auto pl-4">
            <div class="ml-auto flex">
                @foreach($languages as $language)
                    <a @class([
                        'cursor-pointer ml-2',
                        'text-black underline' => SetupLanguage::selectedCode() === $language['code'],
                        'text-gray-500' => SetupLanguage::selectedCode() !== $language['code'],
                    ]) wire:click="setLanguage('{{ $language['code'] }}')">
                        {{ mb_strtoupper($language['code']) }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
