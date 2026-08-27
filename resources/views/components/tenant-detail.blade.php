<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
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

        $user = Auth::user();
        abort_unless(
            $user->isSuperAdmin() || $user->adminTenants()->whereKey($this->modelId)->exists(),
            403,
        );

        $this->initDetail();
    }

    public function store(): void
    {
        $this->validate([
            'detailData.name' => ['required', 'string', 'max:255', 'min:3'],
        ]);

        $tenant = Tenant::findOrFail($this->modelId);
        $tenant->name = $this->detailData['name'];
        $tenant->logo = $this->detailData['logo'] ?? null;
        $tenant->save();

        $this->storeProcess($tenant);
    }

    public function delete(): void
    {
    }

    public function updatedLogo()
    {
        $this->storeFile();
    }

    public function storeFile()
    {
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
