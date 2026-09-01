<?php

use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Renders a mail template with sample data (see HasEmailPreview::openPreview()).
 */
new class extends Component {
    #[Locked]
    public string $emailSubject = '';

    #[Locked]
    public array $sampleData = [];

    #[Locked]
    public string $previewHtml = '';
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Email Preview') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="[]" :modelId="null" :showBlock="false">
        <x-slot:tab1>
            <p class="text-sm text-gray-600">
                {{ __('This is how the email will be displayed with sample data') }}
            </p>

            @if (! empty($emailSubject))
                <div class="mt-6 px-6 py-3 bg-gray-50 border border-gray-200 rounded">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                        {{ __('Subject') }}
                    </div>
                    <div class="text-base font-medium text-gray-900">
                        {{ str_replace(array_keys($sampleData), array_values($sampleData), $emailSubject) }}
                    </div>
                </div>
            @endif

            <div class="mt-6">
                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                    {{ __('Content') }}
                </div>
                <div class="border border-gray-200 rounded overflow-hidden">
                    {{-- The preview is untrusted template output: fully sandboxed, escaped attribute. --}}
                    <iframe
                        srcdoc="{{ $previewHtml }}"
                        class="w-full h-[500px] bg-white"
                        sandbox=""
                        title="{{ __('Email Preview') }}">
                    </iframe>
                </div>
            </div>
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <div class="ml-auto">
            <x-noerd::button variant="secondary" wire:click="$dispatch('closeTopModal')">
                {{ __('Close') }}
            </x-noerd::button>
        </div>
    </x-slot:footer>
</x-noerd::page>
