<?php

use Livewire\Component;
use Noerd\Models\SetupLanguage;

new class extends Component
{
    public array $languages = [];

    public function mount(): void
    {
        // Ensure default languages exist
        SetupLanguage::ensureDefaultLanguages();

        $this->languages = SetupLanguage::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('sort_order')
            ->get(['code', 'name'])
            ->toArray();

        if (! session('selectedLanguage')) {
            $default = SetupLanguage::where('is_default', true)->first();
            if ($default) {
                session(['selectedLanguage' => $default->code]);
            }
        }
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
                        'text-black underline' => session('selectedLanguage') === $language['code'],
                        'text-gray-500' => session('selectedLanguage') !== $language['code'],
                    ]) wire:click="setLanguage('{{ $language['code'] }}')">
                        {{ strtoupper($language['code']) }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
