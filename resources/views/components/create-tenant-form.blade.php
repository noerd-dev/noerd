<?php

use Illuminate\Support\Str;
use Livewire\Component;
use Noerd\Enums\Profile;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\Tenant;

new class extends Component {
    public string $name = '';
    public bool $showSuccess = false;

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function createTenant(): void
    {
        // Guarded on the ACTION as well as on mount: this component carries the
        // whole tenant-creation logic while the admin/feature checks used to sit
        // only on its wrapper (noerd::create-tenant). Reached directly — through
        // the modal stack or the generic component page — it let any
        // authenticated user create a tenant AND attach themselves to its ADMIN
        // profile, and isAdmin() is not tenant-scoped, so that was a global
        // privilege escalation.
        $this->authorizeAccess();

        $this->validate([
            'name' => ['required', 'string', 'max:50', 'min:3'],
        ]);

        $tenant = new Tenant();
        $tenant->name = $this->name;
        $tenant->uuid = Str::uuid();
        $tenant->save();

        // Default also admin
        $tenant->users()->attach(NoerdAuth::user()->id, [
            'profile_key' => Profile::Admin->value,
        ]);

        $selectedTenant = TenantHelper::getSelectedTenant();
        $apps = $selectedTenant?->tenantApps ?? collect();
        foreach ($apps as $app) {
            $tenant->tenantApps()->attach($app->id);
        }

        TenantHelper::setSelectedTenantId($tenant->id);

        $this->showSuccess = true;
    }

    /**
     * Called by the success indicator after a short delay so the user can read
     * the confirmation before landing on the app overview of the new tenant.
     */
    public function goHome(): void
    {
        $this->redirect(route('noerd.apps'));
    }

    private function authorizeAccess(): void
    {
        abort_unless(config('noerd.features.multi_tenant'), 404);
        abort_unless(NoerdAuth::user()?->isAdmin(), 403);
    }
}; ?>

<section>
    <header>
        <p class="text-sm text-gray-600">
            {{ __("A new tenant contains its own master and transactional data, but can be managed with the same users.") }}
        </p>
    </header>

    <form wire:submit="createTenant" class="mt-6 space-y-6">
        <div>
            <x-noerd::input-label for="name" :value="__('New Tenant Name')"/>
            <x-noerd::text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required
                                 autofocus autocomplete="name"/>
            <x-noerd::input-error class="mt-2" :messages="$errors->get('name')"/>
        </div>

        <div class="flex items-center gap-4">
            <x-noerd::button>{{ __('Create New Tenant') }}</x-noerd::button>
        </div>
    </form>

    @if($showSuccess)
        <div class="rounded-md bg-green-50 p-4 mt-6"
             x-init="setTimeout(() => $wire.goHome(), 1500)">
            <div class="flex">
                <div class="shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                              clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">
                        {{ __('Tenant successfully created. Redirecting...') }}
                    </p>
                </div>
            </div>
        </div>
    @endif
</section>
