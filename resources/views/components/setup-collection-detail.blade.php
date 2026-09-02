<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Noerd\Contracts\MediaResolverContract;
use Noerd\Facades\Noerd;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Models\SetupLanguage;
use Noerd\Services\SetupFieldTypeConverter;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\SetupLanguageFilterTrait;
use Noerd\Helpers\NoerdAuth;

new class extends Component
{
    use NoerdDetail;
    use SetupLanguageFilterTrait;
    use WithFileUploads;

    public ?string $detailPrimary = 'setupCollectionId';

    // No $detailModel: the form layout comes from the collection definition
    // (collectionLayout), not from a detail YAML.

    public ?array $collectionLayout = null;
    public ?string $collectionKey = null;
    /** Plain uploads of the image fields (the image element binds `imageUploads.{field}`). */
    public array $imageUploads = [];

    /** Whether the edited entry exists — drives the delete button. */
    public bool $entryExists = false;

    public function mount(mixed $model = null, ?string $collectionKey = null): void
    {
        // initDetail() is not used: the layout is the collection definition, the
        // modelId is bound via $detailPrimary.
        if ($model !== null) {
            $this->modelId = $model instanceof SetupCollectionEntry ? $model->id : $model;
        }

        // Ensure default languages exist for current tenant
        SetupLanguage::ensureDefaultLanguagesForTenant(NoerdAuth::user()->selected_tenant_id);

        $entry = $this->modelId ? SetupCollectionEntry::find($this->modelId) : null;
        $this->entryExists = $entry !== null;
        $this->collectionKey = $collectionKey;

        // A deep link passes only the entry id — derive the collection from the
        // record, otherwise the data conversion below runs without a key.
        if (! $this->collectionKey && $entry) {
            $parentKey = SetupCollection::find($entry->setup_collection_id)?->collection_key;
            $this->collectionKey = $parentKey ? mb_strtolower($parentKey) : null;
        }

        // Load collection layout if collectionKey is provided
        if ($this->collectionKey) {
            $this->collectionLayout = SetupCollectionHelper::getCollectionFields($this->collectionKey);
        }

        $this->pageLayout = $this->collectionLayout ?? ['fields' => []];

        // Load data from the JSON data field
        $rawData = is_array($entry?->data) ? $entry->data : [];
        $this->detailData = $this->collectionKey && $rawData !== []
            ? SetupFieldTypeConverter::convertCollectionData($rawData, $this->collectionKey)
            : $rawData;

        // Ensure sort field is available
        $this->detailData['sort'] ??= $entry?->sort ?? 0;
    }

    public function store(): void
    {
        $this->validateFromLayout();

        // Find or create the parent Collection
        $parentCollection = SetupCollection::firstOrCreate([
            'tenant_id' => NoerdAuth::user()->selected_tenant_id,
            'collection_key' => mb_strtoupper($this->collectionKey),
        ], [
            'name' => ucfirst($this->collectionKey),
        ]);

        // Apply field type conversion before saving
        $convertedEntryData = SetupFieldTypeConverter::convertCollectionData($this->detailData, $this->collectionKey);

        $data = [
            'tenant_id' => NoerdAuth::user()->selected_tenant_id,
            'setup_collection_id' => $parentCollection->id,
            'data' => $convertedEntryData,
            'sort' => (int) ($this->detailData['sort'] ?? 0),
        ];

        $entry = SetupCollectionEntry::updateOrCreate(['id' => $this->modelId], $data);

        $this->storeProcess($entry);
        $this->entryExists = true;
    }

    public function delete(): void
    {
        $entry = SetupCollectionEntry::find($this->modelId);
        $entry?->delete();
        $this->closeModalProcess($this->getListComponent());
    }

    public function updatedImageUploads(): void
    {
        $resolver = app(MediaResolverContract::class);
        foreach ($this->imageUploads as $fieldName => $uploadedFile) {
            $url = $uploadedFile ? $resolver->storeUploadedFile($uploadedFile) : null;
            if ($url) {
                $this->detailData[$fieldName] = $url;
                $this->imageUploads[$fieldName] = null;
            }
        }
    }

    public function deleteImage(string $fieldName): void
    {
        $this->detailData[$fieldName] = null;
    }

    public function openSelectMediaModal(string $fieldName): void
    {
        $picker = app(MediaResolverContract::class)->pickerComponent();
        if (! $picker) {
            return;
        }

        $token = uniqid('media_', true);
        $this->detailData['__mediaToken'] = $token;
        Noerd::modal($picker, ['selectMode' => true, 'selectContext' => $fieldName, 'selectToken' => $token]);
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
        // The listener itself triggers the re-render that picks up the new language.
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>
            {{ $collectionLayout['title'] ?? __('Entry') }}

            <x-slot:actions>
                <livewire:noerd::setup-language-switcher/>
            </x-slot:actions>
        </x-noerd::modal-title>
    </x-slot:header>

    @if($collectionLayout)
        <div class="flex">
            <div class="flex ml-auto items-center my-6 space-x-2">
                <x-noerd::input-label for="sort" :value="__('Sort Order')" class="pb-0" />
                <x-noerd::text-input wire:model="detailData.sort" id="sort" type="number" min="0" step="1" class="w-16 mt-0" />
            </div>
        </div>

        <x-noerd::tab-content :layout="$collectionLayout" :modelId="$modelId" />
    @else
        <div class="text-center py-8">
            <p class="text-gray-500">{{ __('Collection not found') }}</p>
        </div>
    @endif

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="$entryExists"/>
    </x-slot:footer>
</x-noerd::page>
