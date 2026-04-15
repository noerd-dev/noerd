<?php

use Livewire\Component;
use Noerd\Helpers\TenantHelper;

new class () extends Component {
    public function mount(): void
    {
        TenantHelper::setSelectedAppFromRoute();
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>UI Library</x-noerd::modal-title>
    </x-slot:header>

    <div class="max-w-5xl mx-auto">
        <p class="text-sm text-gray-500 my-6">
            All available Noerd UI components with live demos and usage code.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="{{ route('ui-library.buttons') }}" class="block p-6 bg-white border rounded-lg hover:shadow-sm transition">
                <h3 class="font-semibold text-gray-900">Buttons</h3>
                <p class="text-sm text-gray-500 mt-1">Variants, sizes, icons, loading states</p>
            </a>
            <a href="{{ route('ui-library.form-inputs') }}" class="block p-6 bg-white border rounded-lg hover:shadow-sm transition">
                <h3 class="font-semibold text-gray-900">Form Inputs</h3>
                <p class="text-sm text-gray-500 mt-1">Text, textarea, checkbox, select, currency, color</p>
            </a>
            <a href="{{ route('ui-library.form-inputs-advanced') }}" class="block p-6 bg-white border rounded-lg hover:shadow-sm transition">
                <h3 class="font-semibold text-gray-900">Form Inputs (Advanced)</h3>
                <p class="text-sm text-gray-500 mt-1">Picklist, file upload, image, relation, belongs-to-many</p>
            </a>
            <a href="{{ route('ui-library.labels-errors') }}" class="block p-6 bg-white border rounded-lg hover:shadow-sm transition">
                <h3 class="font-semibold text-gray-900">Labels & Errors</h3>
                <p class="text-sm text-gray-500 mt-1">Labels, validation errors, info boxes</p>
            </a>
            <a href="{{ route('ui-library.layout') }}" class="block p-6 bg-white border rounded-lg hover:shadow-sm transition">
                <h3 class="font-semibold text-gray-900">Layout Components</h3>
                <p class="text-sm text-gray-500 mt-1">Page, detail blocks, tabs, toolbar, table</p>
            </a>
            <a href="{{ route('ui-library.actions') }}" class="block p-6 bg-white border rounded-lg hover:shadow-sm transition">
                <h3 class="font-semibold text-gray-900">Action Components</h3>
                <p class="text-sm text-gray-500 mt-1">Delete-save bar, modals, toast notifications</p>
            </a>
            <a href="{{ route('ui-library.advanced') }}" class="block p-6 bg-white border rounded-lg hover:shadow-sm transition">
                <h3 class="font-semibold text-gray-900">Advanced Components</h3>
                <p class="text-sm text-gray-500 mt-1">Rich text, translatable fields, file uploads</p>
            </a>
            <a href="{{ route('ui-library.filters') }}" class="block p-6 bg-white border rounded-lg hover:shadow-sm transition">
                <h3 class="font-semibold text-gray-900">Filters</h3>
                <p class="text-sm text-gray-500 mt-1">List filters, date range, search</p>
            </a>
        </div>
    </div>
</x-noerd::page>
