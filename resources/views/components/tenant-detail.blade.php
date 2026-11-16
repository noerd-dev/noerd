<?php

use Livewire\Component;
use Noerd\Facades\Noerd;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;

    public ?string $detailPrimary = 'tenantId';

    public $detailModel = Tenant::class;

    public function mount(): void
    {
        if (! $this->modelId) {
            $this->modelId = TenantHelper::currentTenantId();
        }

        $this->authorizeTenant();

        $this->initDetail();
    }

    /**
     * The edited tenant must be one this user administers. Re-checked on every
     * write, not only on mount: $modelId is URL-bound and therefore rewritable
     * by the client, so a mount-time-only check let an admin of one tenant
     * rename any other tenant in the installation.
     */
    private function authorizeTenant(): void
    {
        $user = NoerdAuth::user();

        abort_unless(
            $user?->isSuperAdmin() || (bool) $user?->adminTenants()->whereKey($this->modelId)->exists(),
            403,
        );
    }

    public function store(): void
    {
        $this->authorizeTenant();

        $this->validateFromLayout();

        $this->validate([
            'detailData.name' => ['required', 'string', 'max:255', 'min:3'],
        ]);

        $tenant = Tenant::findOrFail($this->modelId);
        $tenant->name = $this->detailData['name'];
        $tenant->save();

        $this->finishStore($tenant);
    }

    /**
     * Only a super admin deletes a tenant — a tenant admin manages its members,
     * never the tenant itself.
     */
    public function canDeleteObject(): bool
    {
        return (bool) $this->modelId && (bool) NoerdAuth::user()?->isSuperAdmin();
    }

    /**
     * Opens the confirmation, which names what keeps the tenant and asks for
     * its name before anything is deleted.
     */
    public function delete(): void
    {
        if (! $this->canDeleteObject()) {
            return;
        }

        Noerd::modal('noerd::tenant-delete-modal', ['tenantId' => (int) $this->modelId]);
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Tenant') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="true" :deleteLabel="__('Delete tenant')" />
    </x-slot:footer>
</x-noerd::page>
