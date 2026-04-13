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

    public function mount(): void
    {
        TenantHelper::setSelectedAppFromRoute();
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
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>Form Inputs</x-noerd::modal-title>
    </x-slot:header>

    @include('noerd::ui-library-sections.form-inputs')
</x-noerd::page>
