<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\Tenant;
use Noerd\Traits\NoerdDetail;

new class extends Component {
    use NoerdDetail;
    use WithFileUploads;

    public ?string $detailPrimary = 'tenantId';

    public $detailModel = Tenant::class;
    public const DETAIL_COMPONENT = 'noerd::tenant-detail';

    public $logo;

    public function mount(): void
    {
        if (! $this->modelId) {
            $this->modelId = (string) Auth::user()->selected_tenant_id;
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

        $this->validate([
            'detailData.name' => ['required', 'string', 'max:255', 'min:3'],
        ]);

        $tenant = Tenant::findOrFail($this->modelId);
        $tenant->name = $this->detailData['name'];
        $tenant->logo = $this->detailData['logo'] ?? null;
        $tenant->save();

        $this->storeProcess($tenant);
    }

    /**
     * Deliberate no-op override: NoerdPage::delete() would remove the tenant
     * record, and the page shortcut (ctrl+backspace) resolves delete() via
     * method_exists. Tenants are never deleted from this screen.
     */
    public function delete(): void
    {
    }

    public function updatedLogo()
    {
        $this->storeFile();
    }

    public function storeFile()
    {
        $this->authorizeTenant();

        // Validated before storing: storePublicly() keeps the client-supplied
        // extension on a web-served disk, so an unvalidated upload was stored
        // same-origin script execution (.html/.svg) at /storage/uploads/….
        $this->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

        $link = $this->logo->storePublicly(path: 'uploads', options: 'public');
        $this->detailData['logo'] = '/storage/' . $link;
    }

    public function deleteImage()
    {
        $this->detailData['logo'] = null;
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('Tenant') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="false" />
    </x-slot:footer>
</x-noerd::page>
