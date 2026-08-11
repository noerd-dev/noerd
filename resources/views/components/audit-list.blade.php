<?php

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Traits\BelongsToTenant;
use OwenIt\Auditing\Contracts\Auditable;

new class extends Component {
    #[Locked]
    public string $modelClass = '';

    #[Locked]
    public ?int $modelId = null;

    public array $audits = [];

    public function mount(): void
    {
        // Mount arguments arrive from the client — never trust the round-trip.
        abort_unless(
            $this->modelClass !== ''
            && class_exists($this->modelClass)
            && is_subclass_of($this->modelClass, Model::class)
            && is_subclass_of($this->modelClass, Auditable::class)
            && in_array(BelongsToTenant::class, class_uses_recursive($this->modelClass), true),
            404,
        );

        $model = $this->modelClass::findOrFail($this->modelId);

        $this->audits = $model->audits()->latest('id')->get()->toArray();
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Activity Log') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="[]" :modelId="$modelId" :showBlock="false">
        <x-slot:tab1>
            <div class="pb-8">
                <x-noerd::audit-table :audits="$audits"/>
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
