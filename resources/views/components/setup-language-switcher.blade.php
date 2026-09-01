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


    public function setLanguage(string $code): void
    {
        session(['selectedLanguage' => $code]);
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
