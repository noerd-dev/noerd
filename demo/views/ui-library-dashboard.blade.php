<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Noerd\Helpers\TenantHelper;

new class extends Component {

    public string $demoText = 'Hello World';
    public string $demoEmail = 'user@example.com';
    public string $demoNumber = '42';
    public string $demoDate = '2026-04-12';
    public string $demoTime = '14:30';
    public string $demoTextarea = "This is sample content for the textarea component.\nIt supports multiple lines.";
    public bool $demoCheckbox = true;
    public string $demoSelect = 'option_b';
    public string $demoColor = '#3B82F6';
    public float $demoCurrency = 1234.56;
    public bool $demoToggle = false;

    public function mount(): void
    {
        TenantHelper::setSelectedAppFromRoute();
    }

    public function demoAction(): void
    {
        // No-op for button demos
    }

    #[Computed]
    public function demoSelectOptions(): array
    {
        return [
            ['value' => '', 'label' => '-- Select --'],
            ['value' => 'option_a', 'label' => 'Option A'],
            ['value' => 'option_b', 'label' => 'Option B'],
            ['value' => 'option_c', 'label' => 'Option C'],
        ];
    }

    #[Computed]
    public function demoToolbarButtons(): array
    {
        return [
            ['action' => 'demoAction', 'label' => 'Export', 'heroicon' => 'arrow-down-tray'],
            ['type' => 'separator'],
            ['action' => 'demoAction', 'label' => 'Print', 'heroicon' => 'printer'],
            ['action' => 'demoAction', 'label' => 'Refresh', 'heroicon' => 'arrow-path', 'loading' => 'Loading...'],
        ];
    }
}; ?>

<div class="min-h-screen bg-gray-50">
    <div class="max-w-5xl mx-auto py-8 px-4 sm:px-6">

        {{-- Header --}}
        <div class="mb-10">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('ui_library_dashboard_title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">All available Noerd UI components with live demos and usage code.</p>
        </div>

        @include('ui-library-sections.buttons')
        @include('ui-library-sections.form-inputs')
        @include('ui-library-sections.form-inputs-advanced')
        @include('ui-library-sections.labels-errors')
        @include('ui-library-sections.layout')
        @include('ui-library-sections.actions')
        @include('ui-library-sections.advanced')
        @include('ui-library-sections.filters')

    </div>
</div>
