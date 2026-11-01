<?php

use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Helpers\NoerdAuth;
use Noerd\Models\NoerdUser;
use Noerd\Traits\AdministersNoerdUsers;
use Noerd\Traits\NoerdPage;

new class extends Component {
    use AdministersNoerdUsers;
    use NoerdPage;

    public ?string $detailPrimary = 'userId';

    public $detailModel = NoerdUser::class;

    /**
     * The account itself (password, deletion) is managed here — by a super
     * admin, or by the person on their own record. Drives the password tab.
     */
    #[Locked]
    public bool $accountManageable = false;

    #[Locked]
    public bool $accountDeletable = false;

    public function mount(): void
    {
        // Defense in depth: this admin-only user editor can be reached outside its
        // setup route (the modal stack). Enforce admin access
        // here too, independent of the dynamic-mount guard.
        abort_unless(NoerdAuth::user()?->isAdmin(), 403);

        $this->authorizeTargetUser();

        $this->initPage();

        $this->accountManageable = (bool) $this->modelId && $this->canManageAccount();

        $target = $this->modelId ? NoerdUser::find($this->modelId) : null;
        $admin = NoerdAuth::user();

        $this->accountDeletable = $target instanceof NoerdUser
            && $admin->isSuperAdmin()
            && ! $target->is($admin)
            && ! $target->isSuperAdmin();
    }

    public function delete(): void
    {
        $this->removeFromSelectedTenant();
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('User') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::detail-grid :layout="$pageLayout" :modelId="$modelId">
        <x-noerd::tabs :layout="$pageLayout" :modelId="$modelId">
            @if ($accountDeletable)
                <x-slot:actions>
                    <x-noerd::action-menu :label="__('Actions')">
                        <x-noerd::action-menu-item
                            wire:click="deleteAccount"
                            wire:confirm="{{ __('Delete this account for good? It loses the access to every tenant.') }}"
                        >
                            {{ __('Delete Account') }}
                        </x-noerd::action-menu-item>
                    </x-noerd::action-menu>
                </x-slot:actions>
            @endif
        </x-noerd::tabs>

        <x-noerd::tab-panels>
            <x-noerd::tab-panel :number="1">
                @livewire($this->embeddedDetailComponent(), ['modelId' => $modelId, 'embedded' => true], key('embedded-detail'))
            </x-noerd::tab-panel>

            <x-noerd::tab-panel :number="2">
                @if (isset($modelId) && $accountManageable)
                    <div class="max-w-xl">
                        <livewire:noerd::user-update-password :userId="$modelId"/>
                    </div>
                @endif
            </x-noerd::tab-panel>
        </x-noerd::tab-panels>
    </x-noerd::detail-grid>

    <x-slot:footer>
        <x-noerd::delete-save-bar
            :showDelete="isset($modelId) && (int) $modelId !== (int) NoerdAuth::id() && $this->assignedToCurrentTenant"
            :deleteLabel="__('Remove from tenant')"
        />
    </x-slot:footer>
</x-noerd::page>
