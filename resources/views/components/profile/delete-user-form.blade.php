<?php

use Livewire\Component;
use Noerd\Facades\Noerd;

new class extends Component {
    public function openConfirmation(): void
    {
        Noerd::modal('noerd::delete-account-modal');
    }
}; ?>

<section class="space-y-6">
    <header>
        <div class="text-lg font-medium text-gray-900">
            {{ __('Delete Account') }}
        </div>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <x-noerd::button variant="danger" :icon="false" wire:click="openConfirmation">
        {{ __('Delete Account') }}
    </x-noerd::button>
</section>
