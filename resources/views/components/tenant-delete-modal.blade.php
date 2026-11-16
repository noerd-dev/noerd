<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Exceptions\TenantDeletionBlockedException;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;
use Noerd\Services\TenantDeletionService;

/**
 * Confirms the deletion of a tenant: lists what keeps it (module blockers, the
 * tenant the super admin works in) and asks for the tenant's name. Opened from
 * the tenant detail; only a super admin gets here, and every action re-checks it.
 */
new class extends Component {
    #[Locked]
    public int $tenantId;

    public string $confirmation = '';

    public function mount(int $tenantId): void
    {
        $this->authorizeSuperAdmin();

        $this->tenantId = Tenant::query()->findOrFail($tenantId)->getKey();
    }

    #[Computed]
    public function tenant(): Tenant
    {
        return Tenant::query()->findOrFail($this->tenantId);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function blockers(): array
    {
        $reasons = app(TenantDeletionService::class)->blockers($this->tenant);

        // The session would keep working in a tenant that no longer exists.
        if (TenantHelper::getSelectedTenantId() === $this->tenantId) {
            array_unshift($reasons, __('You are working in this tenant. Switch to another tenant first.'));
        }

        return $reasons;
    }

    public function deleteTenant(): void
    {
        $this->authorizeSuperAdmin();
        $this->resetErrorBag();

        if ($this->blockers !== []) {
            return;
        }

        if (trim($this->confirmation) !== $this->tenant->name) {
            $this->addError('confirmation', __('The name does not match the tenant.'));

            return;
        }

        try {
            app(TenantDeletionService::class)->delete($this->tenant);
        } catch (TenantDeletionBlockedException $exception) {
            $this->addError('confirmation', $exception->getMessage());

            return;
        }

        // The detail below this dialog shows a tenant that is gone.
        $this->redirectRoute('noerd.tenants');
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless((bool) NoerdAuth::user()?->isSuperAdmin(), 403);
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Delete tenant :name?', ['name' => $this->tenant->name]) }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="[]" :modelId="null" :showBlock="false">
        <x-slot:tab1>
            @if ($this->blockers !== [])
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    <p class="font-medium">{{ __('The tenant cannot be deleted yet:') }}</p>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach ($this->blockers as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                </div>
            @else
                <form wire:submit="deleteTenant">
                    <p class="text-sm text-gray-600">
                        {{ __('The tenant and all of its data will be permanently deleted. User accounts stay; they only lose their access to this tenant. This cannot be undone.') }}
                    </p>

                    <div class="mt-6">
                        <x-noerd::input-label for="tenant-delete-confirmation" :value="__('Type the name of the tenant to confirm')" />
                        <x-noerd::text-input
                            wire:model="confirmation"
                            id="tenant-delete-confirmation"
                            type="text"
                            autocomplete="off"
                            class="mt-1 block w-full"
                            :placeholder="$this->tenant->name"
                        />
                        <x-noerd::input-error :messages="$errors->get('confirmation')" class="mt-2"/>
                    </div>
                </form>
            @endif
        </x-slot:tab1>
    </x-noerd::tab-content>

    <x-slot:footer>
        <div class="ml-auto flex items-center gap-2">
            <x-noerd::button variant="secondary" wire:click="$dispatch('closeTopModal')">
                {{ __('Cancel') }}
            </x-noerd::button>
            @if ($this->blockers === [])
                <x-noerd::button variant="danger" :icon="false" wire:click="deleteTenant">
                    {{ __('Delete tenant') }}
                </x-noerd::button>
            @endif
        </div>
    </x-slot:footer>
</x-noerd::page>
