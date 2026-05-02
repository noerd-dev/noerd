<?php

use Livewire\Component;

new class extends Component {
    public string $emailSubject = '';

    public array $sampleData = [];

    public string $previewHtml = '';
}; ?>

<div>
    <div class="text-xl font-semibold text-gray-900">{{ __('Email Preview') }}</div>
    <p class="text-sm text-gray-600 mt-1">
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
            <iframe
                srcdoc="{!! str_replace('"', '&quot;', $previewHtml) !!}"
                class="w-full h-[500px] bg-white"
                sandbox="allow-same-origin"
                title="{{ __('Email Preview') }}">
            </iframe>
        </div>
    </div>

    <div class="mt-6 flex border-t border-gray-300 pt-4">
        <div class="ml-auto">
            <x-noerd::button variant="secondary" wire:click="$dispatch('closeTopModal')">
                {{ __('Close') }}
            </x-noerd::button>
        </div>
    </div>
</div>
