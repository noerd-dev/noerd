<?php

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Models\SetupLanguage;
use Noerd\Services\SetupFieldTypeConverter;
use Noerd\Traits\Noerd;
use Noerd\Traits\SetupLanguageFilterTrait;
use Noerd\Media\Models\Media;
use Noerd\Media\Services\MediaUploadService;
use Illuminate\Support\Facades\Storage;

new class extends Component
{
    use Noerd;
    use SetupLanguageFilterTrait;
    use WithFileUploads;

    public const COMPONENT = 'setup-collection-detail';
    public const LIST_COMPONENT = 'setup-collections-list';
    public const ID = 'entryId';

    #[Url(keep: false, except: '')]
    public $entryId = null;

    public array $entryData = [];
    public ?SetupCollectionEntry $entry = null;
    public ?array $collectionLayout = null;
    public ?string $collectionKey = null;
    public array $images = [];

    public function mount(SetupCollectionEntry $entry, ?string $collectionKey = null): void
    {
        // Ensure default languages exist
        SetupLanguage::ensureDefaultLanguages();

        if ($this->entryId) {
            $entry = SetupCollectionEntry::find($this->entryId) ?? new SetupCollectionEntry;
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
        $this->entryId = $entry->id;
        $this->entryId = $entry->id;

        // Load data from the JSON data field
        if ($this->entry->exists && $this->entry->data) {
            $rawData = is_array($this->entry->data) ? $this->entry->data : [];
            $this->entryData = SetupFieldTypeConverter::convertCollectionData($rawData, $this->collectionKey);
        } else {
            $this->entryData = [];
        }

        // Ensure sort field is available
        $this->entryData['sort'] ??= $this->entry->sort ?? 0;
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
        $convertedEntryData = SetupFieldTypeConverter::convertCollectionData($this->entryData, $this->collectionKey);

        $data = [
            'tenant_id' => auth()->user()->selected_tenant_id,
            'setup_collection_id' => $parentCollection->id,
            'data' => $convertedEntryData,
            'sort' => (int) ($this->entryData['sort'] ?? 0),
        ];

        $entry = SetupCollectionEntry::updateOrCreate(['id' => $this->entryId], $data);

        $this->showSuccessIndicator = true;

        if ($entry->wasRecentlyCreated) {
            $this->entryId = $entry->id;
            $this->entry = $entry;
            $this->entryId = $entry->id;
        }
    }

    public function delete(): void
    {
        $entry = SetupCollectionEntry::find($this->entryId);
        $entry?->delete();
        $this->closeModalProcess(self::LIST_COMPONENT);
    }

    public function updatedImages(): void
    {
        $mediaUploadService = app()->make(MediaUploadService::class);
        foreach ($this->images as $key => $image) {
            $media = $mediaUploadService->storeFromUploadedFile($image);
            $this->entryData[$key] = $this->urlWithoutDomain($media);
        }
    }

    public function deleteImage(string $fieldName): void
    {
        $this->entryData[$fieldName] = null;
    }

    public function openSelectMediaModal(string $fieldName): void
    {
        $token = uniqid('media_', true);
        $this->entryData['__mediaToken'] = $token;
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'media-list',
            arguments: ['selectMode' => true, 'selectContext' => $fieldName, 'selectToken' => $token],
        );
    }

    #[On('mediaSelected')]
    public function mediaSelected(int $mediaId, ?string $fieldName = 'image', ?string $token = null): void
    {
        if (($this->entryData['__mediaToken'] ?? null) !== $token) {
            return;
        }
        $media = Media::find($mediaId);
        if (! $media) {
            return;
        }
        $this->entryData[$fieldName ?? 'image'] = $this->urlWithoutDomain($media);
        unset($this->entryData['__mediaToken']);
    }

    #[On('setupLanguageChanged')]
    public function refresh(): void
    {
        $this->dispatch('$refresh');
    }

    private function urlWithoutDomain(Media $media): string
    {
        $url = Storage::disk($media->disk)->url($media->path);

        return mb_strstr($url, '/storage');
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-slot:header>
        <x-noerd::modal-title class="flex items-center">
            {{ $collectionLayout['title'] ?? __('noerd_collection_entry') }}

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
                    <label for="sort" class="text-sm text-gray-600 font-medium">{{ __('noerd_label_sort') }}:</label>
                    <input
                        wire:model="entryData.sort"
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
            <p class="text-gray-500">{{ __('noerd_collection_not_found') }}</p>
        </div>
    @endif

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="isset($entry) && $entry->exists"/>
    </x-slot:footer>
</x-noerd::page>
