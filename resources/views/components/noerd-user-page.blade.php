<?php

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


    public function mount(): void
    {
        // Defense in depth: this admin-only user editor can be reached outside its
        // setup route (modal stack / generic component page). Enforce admin access
        // here too, independent of the dynamic-mount guard.
        abort_unless(NoerdAuth::user()?->isAdmin(), 403);

        $this->authorizeTargetUser();

        $this->initPage();
    }

    /**
     * The page YAML carries the embedded form; fall back to the core component
     * so an installation that has not published pages/noerd-user-page.yml yet
     * still renders and saves.
     */
    public function embeddedDetailComponent(): ?string
    {
        return $this->pageLayout['detail'] ?? 'noerd::noerd-user-detail';
    }

    public function delete(): void
    {
        $this->deleteUserAccount();
    }
}; ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>{{ __('User') }}</x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::detail-grid :layout="$pageLayout" :modelId="$modelId">
        <x-noerd::tabs :layout="$pageLayout" :modelId="$modelId"/>

        <x-noerd::tab-panels>
            <x-noerd::tab-panel :number="1">
                @livewire($this->embeddedDetailComponent(), ['modelId' => $modelId, 'embedded' => true], key('embedded-detail'))
            </x-noerd::tab-panel>

            <x-noerd::tab-panel :number="2">
                @isset($modelId)
                    <div class="max-w-xl">
                        <livewire:noerd::user-update-password :userId="$modelId"/>
                    </div>
                @endisset
            </x-noerd::tab-panel>
        </x-noerd::tab-panels>
    </x-noerd::detail-grid>

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($modelId) && $this->assignedToCurrentTenant"/>
    </x-slot:footer>
</x-noerd::page>
