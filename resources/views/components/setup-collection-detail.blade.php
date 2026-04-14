<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Noerd\Contracts\MediaResolverContract;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Models\SetupLanguage;
use Noerd\Services\SetupFieldTypeConverter;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\SetupLanguageFilterTrait;

new class extends Component
{
    use NoerdDetail;
    use SetupLanguageFilterTrait;
    use WithFileUploads;

    #[Url(as: 'setupCollectionId', keep: false, except: '')]
    public $modelId = null;

    // Note: This component does NOT use DETAIL_CLASS because it uses custom layout from collectionLayout
    // instead of the standard YAML config system

    public array $detailData = [];
    public ?SetupCollectionEntry $entry = null;
    public ?array $collectionLayout = null;
    public ?string $collectionKey = null;
    public array $images = [];

    public function mount(mixed $model = null, ?string $collectionKey = null): void
    {
        // Note: We don't call initDetail here because this component uses custom layout
        // from collectionLayout instead of YAML config. The modelId is bound via #[Url] attribute.
        if ($model !== null) {
            $this->modelId = $model instanceof SetupCollectionEntry ? $model->id : $model;
        }

        // Ensure default languages exist for current tenant
        SetupLanguage::ensureDefaultLanguagesForTenant(auth()->user()->selected_tenant_id);

        $entry = new SetupCollectionEntry;
        if ($this->modelId) {
            $entry = SetupCollectionEntry::find($this->modelId) ?? new SetupCollectionEntry;
        }

        $this->entry = $entry->exists ? $entry : new SetupCollectionEntry;
        $this->collectionKey = $collectionKey;

        // Load collection layout if collectionKey is provided
        if ($this->collectionKey) {
            $this->collectionLayout = SetupCollectionHelper::getCollectionFields($this->collectionKey);
        }

        // Custom mount process - don't use mountModalProcess as it requires a YAML config
        // Instead, use the collection layout directly
        $this->pageLayout = $this->collectionLayout ?? ['fields' => []];

        // Load data from the JSON data field
        if ($this->entry->exists && $this->entry->data) {
            $rawData = is_array($this->entry->data) ? $this->entry->data : [];
            $this->detailData = SetupFieldTypeConverter::convertCollectionData($rawData, $this->collectionKey);
        } else {
            $this->detailData = [];
        }

        // Ensure sort field is available
        $this->detailData['sort'] ??= $this->entry->sort ?? 0;
    }

    public function store(): void
    {
        // Find or create the parent Collection
        $parentCollection = SetupCollection::firstOrCreate([
            'tenant_id' => auth()->user()->selected_tenant_id,
            'collection_key' => mb_strtoupper($this->collectionKey),
        ], [
            'name' => ucfirst($this->collectionKey),
        ]);

        // Apply field type conversion before saving
        $convertedEntryData = SetupFieldTypeConverter::convertCollectionData($this->detailData, $this->collectionKey);

        $data = [
            'tenant_id' => auth()->user()->selected_tenant_id,
            'setup_collection_id' => $parentCollection->id,
            'data' => $convertedEntryData,
            'sort' => (int) ($this->detailData['sort'] ?? 0),
        ];

        $entry = SetupCollectionEntry::updateOrCreate(['id' => $this->modelId], $data);

        $this->storeProcess($entry);

        if ($entry->wasRecentlyCreated) {
            $this->entry = $entry;
        }
    }

    public function delete(): void
    {
        $entry = SetupCollectionEntry::find($this->modelId);
        $entry?->delete();
        $this->closeModalProcess($this->getListComponent());
    }

    public function updatedImages(): void
    {
        $resolver = app(MediaResolverContract::class);
        foreach ($this->images as $key => $image) {
            $url = $resolver->storeUploadedFile($image);
            if ($url) {
                $this->detailData[$key] = $url;
            }
        }
    }

    public function deleteImage(string $fieldName): void
    {
        $this->detailData[$fieldName] = null;
    }

    public function openSelectMediaModal(string $fieldName): void
    {
        $token = uniqid('media_', true);
        $this->detailData['__mediaToken'] = $token;
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'media-list',
            arguments: ['selectMode' => true, 'selectContext' => $fieldName, 'selectToken' => $token],
        );
    }

    #[On('mediaSelected')]
    public function mediaSelected(int $mediaId, ?string $fieldName = 'image', ?string $token = null): void
    {
        if (($this->detailData['__mediaToken'] ?? null) !== $token) {
            return;
        }
        $resolver = app(MediaResolverContract::class);
        $url = $resolver->getRelativeUrl($mediaId);
        if (! $url) {
            return;
        }
        $this->detailData[$fieldName ?? 'image'] = $url;
        unset($this->detailData['__mediaToken']);
    }

    #[On('setupLanguageChanged')]
    public function refresh(): void
    {
        $this->dispatch('$refresh');
    }

} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title class="flex items-center">
            {{ $collectionLayout['title'] ?? __('Entry') }}

            <div class="ml-auto" :class="isModal ? 'mr-22' : ''">
                <livewire:setup-language-switcher/>
            </div>
        </x-noerd::modal-title>
    </x-slot:header>

    @if($collectionLayout)
        <!-- Sort Field -->
        <div class="flex">
            <div class="flex ml-auto items-center my-6 space-x-4">
                <div class="flex ml-auto items-center space-x-2">
                    <label for="sort" class="text-sm text-gray-600 font-medium">{{ __('Sort Order') }}:</label>
                    <input
                        wire:model="detailData.sort"
                        id="sort"
                        type="number"
                        min="0"
                        step="1"
                        class="w-16 rounded-md border border-zinc-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent"
                    />
                </div>
            </div>
        </div>

        <x-noerd::tab-content :layout="$collectionLayout" />
    @else
        <div class="text-center py-8">
            <p class="text-gray-500">{{ __('Collection not found') }}</p>
        </div>
    @endif

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($entry) && $entry->exists"/>
    </x-slot:footer>
</x-noerd::page>
