<?php

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Noerd\Contracts\MediaResolverContract;
use Noerd\Facades\Noerd;
use Noerd\Helpers\AccessHelper;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Support\LayoutFields;
use Noerd\Support\SetupFieldTypeConverter;
use Noerd\Traits\NoerdDetail;
use Noerd\Traits\SetupLanguageFilter;

new class extends Component
{
    use NoerdDetail;
    use SetupLanguageFilter;
    use WithFileUploads;

    public ?string $detailPrimary = 'setupCollectionId';

    // No $detailModel: the form layout comes from the collection definition
    // (collectionLayout), not from a detail YAML. Both are server state: the
    // layout decides which fields store() writes, the key which collection —
    // neither is ever a client update.

    #[Locked]
    public ?array $collectionLayout = null;

    #[Locked]
    public ?string $collectionKey = null;
    /** Fallback limit (KB) of a plain image upload when the media module sets none. */
    private const MAX_IMAGE_KILOBYTES = 10240;

    /** Plain uploads of the image fields (the image element binds `imageUploads.{field}`). */
    public array $imageUploads = [];

    /**
     * Token of the media picker this detail opened; a `mediaSelected` answer
     * is adopted only with it. Server state, never part of the entry data.
     */
    #[Locked]
    public ?string $mediaSelectToken = null;

    /** Whether the edited entry exists — drives the delete button. */
    public bool $entryExists = false;

    public function mount(mixed $model = null, ?string $collectionKey = null): void
    {
        // initDetail() is not used: the layout is the collection definition, the
        // modelId is bound via $detailPrimary.
        if ($model !== null) {
            $this->modelId = $model instanceof SetupCollectionEntry ? $model->id : $model;
        }

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
        if (! $this->canSaveObject()) {
            return;
        }

        $this->validateFromLayout();

        // Find or create the parent Collection (tenant-scoped by the model).
        $parentCollection = SetupCollection::firstOrCreate([
            'collection_key' => mb_strtoupper($this->collectionKey),
        ], [
            'name' => ucfirst($this->collectionKey),
        ]);

        // Apply field type conversion before saving; only the definition's
        // fields are entry data (sort is a column, nothing else is stored).
        $convertedEntryData = array_intersect_key(
            SetupFieldTypeConverter::convertCollectionData($this->detailData, $this->collectionKey),
            array_flip($this->definitionFieldNames()),
        );

        $data = [
            'setup_collection_id' => $parentCollection->id,
            'data' => $convertedEntryData,
            'sort' => (int) ($this->detailData['sort'] ?? 0),
        ];

        // $modelId is URL-bound: an id the tenant-scoped query cannot resolve
        // (another tenant's entry, a deleted one) stores nothing.
        if ($this->modelId) {
            $entry = SetupCollectionEntry::find($this->modelId);

            if (! $entry instanceof SetupCollectionEntry) {
                return;
            }

            $entry->fill($data)->save();
        } else {
            $entry = SetupCollectionEntry::create($data);
        }

        $this->finishStore($entry);
        $this->entryExists = true;
    }

    /**
     * No $detailModel (the layout is the collection definition), so the object
     * permissions are asked for the entry model explicitly — store(), delete()
     * and the image upload all go through them.
     */
    public function canWriteObject(): bool
    {
        return AccessHelper::canWriteObject(SetupCollectionEntry::class);
    }

    public function canCreateObject(): bool
    {
        return AccessHelper::canCreateObject(SetupCollectionEntry::class);
    }

    public function canDeleteObject(): bool
    {
        return AccessHelper::canDeleteObject(SetupCollectionEntry::class);
    }

    public function delete(): void
    {
        if (! $this->canDeleteObject()) {
            return;
        }

        $entry = SetupCollectionEntry::find($this->modelId);
        $entry?->delete();
        $this->closeModalProcess($this->getListComponent());
    }

    /**
     * An image chosen in a plain file input (no media library installed). The
     * upload channel is client input like any other: only the image fields of
     * the collection layout take a file, only a user who may save the entry
     * uploads, and only a raster image within the size limit is stored.
     */
    public function updatedImageUploads(): void
    {
        $imageFields = $this->imageFieldNames();

        if (! $this->canSaveObject()) {
            $this->imageUploads = [];

            return;
        }

        $maxKilobytes = (int) config('media.max_upload_size', self::MAX_IMAGE_KILOBYTES);
        $resolver = app(MediaResolverContract::class);

        foreach ($this->imageUploads as $fieldName => $uploadedFile) {
            if (! in_array((string) $fieldName, $imageFields, true)) {
                unset($this->imageUploads[$fieldName]);

                continue;
            }

            if (! $uploadedFile) {
                continue;
            }

            $this->validate([
                'imageUploads.' . $fieldName => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:' . $maxKilobytes],
            ]);

            // The media id when a library exists, the stored URL otherwise —
            // the image element resolves both.
            $reference = $resolver->storeUploadedFileReference($uploadedFile);
            if ($reference !== null && $reference !== '') {
                $this->detailData[$fieldName] = $reference;
                $this->imageUploads[$fieldName] = null;
            }
        }
    }

    public function deleteImage(string $fieldName): void
    {
        if (! in_array($fieldName, $this->imageFieldNames(), true)) {
            return;
        }

        $this->detailData[$fieldName] = null;
    }

    public function openSelectMediaModal(string $fieldName): void
    {
        $picker = app(MediaResolverContract::class)->pickerComponent();
        if (! $picker || ! in_array($fieldName, $this->imageFieldNames(), true)) {
            return;
        }

        $this->mediaSelectToken = uniqid('media_', true);
        Noerd::modal($picker, ['selectMode' => true, 'selectContext' => $fieldName, 'selectToken' => $this->mediaSelectToken]);
    }

    /**
     * The picker's answer: the media ID is stored (it survives the file being
     * moved), never its URL.
     */
    #[On('mediaSelected')]
    public function mediaSelected(int $mediaId, ?string $fieldName = 'image', ?string $token = null): void
    {
        if ($this->mediaSelectToken === null || $this->mediaSelectToken !== $token || ! $this->canSaveObject()) {
            return;
        }
        $fieldName ??= 'image';
        if (! in_array($fieldName, $this->imageFieldNames(), true)) {
            return;
        }
        if (! app(MediaResolverContract::class)->exists($mediaId)) {
            return;
        }
        $this->detailData[$fieldName] = $mediaId;
        $this->mediaSelectToken = null;
    }

    /**
     * The `sort` input above the form is bound by hand, not by the layout.
     *
     * @return list<string>
     */
    protected function additionalDetailFields(): array
    {
        return ['detailData.sort'];
    }

    /**
     * The detailData keys of the layout's `type: image` fields — the only keys
     * an upload or a media pick may write.
     *
     * @return list<string>
     */
    private function imageFieldNames(): array
    {
        $names = [];

        LayoutFields::walk($this->pageLayout['fields'] ?? [], function (array $field) use (&$names): void {
            $name = $field['name'] ?? null;
            if (($field['type'] ?? null) === 'image' && is_string($name) && str_starts_with($name, 'detailData.')) {
                $names[] = mb_substr($name, mb_strlen('detailData.'));
            }
        });

        return $names;
    }

    /**
     * The detailData keys the collection definition declares — the only keys
     * an entry stores.
     *
     * @return list<string>
     */
    private function definitionFieldNames(): array
    {
        $names = [];

        LayoutFields::walk($this->pageLayout['fields'] ?? [], function (array $field) use (&$names): void {
            $name = $field['name'] ?? null;
            if (is_string($name) && str_starts_with($name, 'detailData.')) {
                $names[] = (string) str($name)->after('detailData.')->before('.');
            }
        });

        return array_values(array_unique($names));
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
            {{ $pageLayout['title'] ?? __('Entry') }}

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

        <x-noerd::tab-content :layout="$pageLayout" :modelId="$modelId" />
    @else
        <div class="text-center py-8">
            <p class="text-gray-500">{{ __('Collection not found') }}</p>
        </div>
    @endif

    <x-slot:footer>
        <x-noerd::delete-save-bar :showDelete="$entryExists"/>
    </x-slot:footer>
</x-noerd::page>
