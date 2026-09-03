<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Noerd\Facades\Noerd;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Models\SetupLanguage;
use Noerd\Traits\NoerdList;
use Noerd\Traits\SetupLanguageFilterTrait;

new class extends Component
{
    use NoerdList;
    use SetupLanguageFilterTrait;

    public $listModel = SetupCollectionEntry::class;
    public ?string $detailRoute = 'noerd.setup-collection.detail';

    public string|int|null $collectionKey = null;

    // Resolved from the collection definition at mount; never a client input
    // (it feeds the JSON_EXTRACT search paths below).
    #[Locked]
    public ?array $collectionLayout = null;

    #[Computed]
    public function tableFilters(): array
    {
        if (! $this->hasMultipleLanguages()) {
            return [];
        }

        return [$this->getLanguageListFilter()];
    }

    public function storeActiveListFilters(): void
    {
        session(['listFilters' => $this->listFilters]);

        if (! empty($this->listFilters['language'])) {
            session([SetupLanguage::SESSION_KEY => $this->listFilters['language']]);
        }
    }

    public function mount(): void
    {
        $this->mountList();

        if (! $this->collectionKey) {
            $this->collectionKey = request()->get('key');
        }

        // Load collection layout
        $this->collectionLayout = SetupCollectionHelper::getCollectionFields($this->collectionKey);

        // Every collection definition owns one bucket row per tenant. firstOrCreate
        // is a single read that only writes when the bucket is missing — mount()
        // re-runs on every modal-stack update and must stay side-effect free
        // for a tenant that is already provisioned.
        if ($this->collectionKey) {
            SetupCollection::firstOrCreate([
                'tenant_id' => NoerdAuth::user()->selected_tenant_id,
                'collection_key' => mb_strtoupper($this->collectionKey),
            ], [
                'name' => $this->collectionLayout['titleList'] ?? ucfirst($this->collectionKey),
            ]);
        }

        if (request()->boolean('create')) {
            $this->listAction();
        }
    }

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        Noerd::modalFor('noerd.setup-collection.detail', 'noerd::setup-collection-detail', ['modelId' => $modelId, 'collectionKey' => $this->collectionKey, 'relations' => $relations]);
    }

    public function listData(): array
    {
        if (! $this->collectionKey) {
            return $this->buildList(collect([]), [
                'title' => __('Collections'),
                'actions' => [['label' => __('New Entry'), 'action' => 'listAction']],
                'disableSearch' => false,
                'columns' => [],
            ]);
        }

        // The bucket row is created on mount — rendering never writes.
        $parentCollectionId = SetupCollection::where('tenant_id', NoerdAuth::user()->selected_tenant_id)
            ->where('collection_key', mb_strtoupper($this->collectionKey))
            ->value('id');

        // Get collection entries
        $query = SetupCollectionEntry::where('tenant_id', NoerdAuth::user()->selected_tenant_id)
            ->where('setup_collection_id', $parentCollectionId ?? 0)
            ->orderBy('sort', 'asc')
            ->orderBy('created_at', 'desc');

        // Apply search if provided
        if (! empty($this->search)) {
            $escapedSearch = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $this->search) . '%';
            $languageCodes = SetupLanguage::activeCodes();

            $query->where(function ($q) use ($escapedSearch, $languageCodes): void {
                $searchable = 0;

                if ($this->collectionLayout && isset($this->collectionLayout['fields'])) {
                    foreach ($this->collectionLayout['fields'] as $field) {
                        $fieldName = $field['name'] ?? '';
                        $fieldKey = str_replace('detailData.', '', is_string($fieldName) ? $fieldName : '');

                        // Skip image fields for search
                        if (($field['type'] ?? '') === 'image') {
                            continue;
                        }

                        // The key is interpolated into a JSON_EXTRACT path below,
                        // so it must never carry anything but a plain field name —
                        // $collectionLayout is a public property and therefore
                        // client-writable, which made this a raw SQL injection.
                        if (! preg_match('/^[A-Za-z0-9_]+$/', $fieldKey)) {
                            continue;
                        }

                        // Search the plain value and every active language of a
                        // translatable value.
                        $q->orWhereRaw("JSON_EXTRACT(data, \"$.{$fieldKey}\") LIKE ? ESCAPE '!'", [$escapedSearch]);
                        foreach ($languageCodes as $languageCode) {
                            if (preg_match('/^[a-z]{2,5}$/i', $languageCode)) {
                                $q->orWhereRaw("JSON_EXTRACT(data, \"$.{$fieldKey}.{$languageCode}\") LIKE ? ESCAPE '!'", [$escapedSearch]);
                            }
                        }
                        $searchable++;
                    }
                }

                // No searchable field survived: an empty where-group would match
                // EVERY row, turning a failed search into a full listing.
                if ($searchable === 0) {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        $rows = $query->paginate($this->perPage);

        $selectedLanguage = $this->listFilters['language'] ?? SetupLanguage::selectedCode();

        // Transform data for display
        $rows->getCollection()->transform(function ($entry) use ($selectedLanguage) {
            $data = is_array($entry->data) ? $entry->data : [];
            $transformedData = [
                'id' => $entry->id,
                'sort' => $entry->sort ?? 0,
                'updated_at' => $entry->updated_at,
            ];

            // Add dynamic fields from YAML configuration
            if ($this->collectionLayout && isset($this->collectionLayout['fields'])) {
                foreach ($this->collectionLayout['fields'] as $field) {
                    $fieldName = $field['name'] ?? '';
                    $fieldKey = str_replace('detailData.', '', $fieldName);

                    $value = '';
                    if (isset($data[$fieldKey])) {
                        $fieldData = $data[$fieldKey];

                        // Handle translatable fields
                        if (is_array($fieldData)) {
                            $value = $fieldData[$selectedLanguage] ?? array_values($fieldData)[0] ?? '';
                        } else {
                            $value = $fieldData;
                        }
                    }

                    // Handle special field types
                    if (($field['type'] ?? '') === 'image' && $value) {
                        $value = '✓ '.__('Image present');
                    }

                    $transformedData[$fieldKey] = $value ?: '-';
                }
            }

            return $transformedData;
        });

        $collectionTitle = $this->collectionLayout['titleList'] ?? ucfirst($this->collectionKey);
        $actionLabel = $this->collectionLayout['buttonList'] ?? __('New Entry');

        // Generate dynamic columns from YAML fields
        $columns = [];
        if ($this->collectionLayout && isset($this->collectionLayout['fields'])) {
            foreach ($this->collectionLayout['fields'] as $field) {
                $fieldName = $field['name'] ?? '';
                $fieldKey = str_replace('detailData.', '', $fieldName);
                $label = $field['label'] ?? ucfirst($fieldKey);

                // Calculate width weight based on field type
                $width = match ($field['type'] ?? 'text') {
                    'image' => 0.8,
                    'translatableText' => 1.2,
                    'translatableTextarea' => 1.5,
                    default => 1,
                };

                $columns[] = [
                    'field' => $fieldKey,
                    'label' => $label,
                    'width' => $width,
                    // Marks the cell as language-dependent (light blue frame in the list).
                    'translatable' => str_starts_with($field['type'] ?? '', 'translatable'),
                ];
            }
        }

        // Add standard columns
        $columns[] = ['field' => 'sort', 'label' => __('Sort Order'), 'width' => 0.5];
        $columns[] = ['field' => 'updated_at', 'label' => __('Last Modified'), 'type' => 'datetime'];

        return $this->buildList($rows, [
            'title' => $collectionTitle,
            'actions' => [['label' => $actionLabel, 'action' => 'listAction']],
            'disableSearch' => false,
            'columns' => $columns,
        ]);
    }

    /**
     * The language filter follows the setup language switcher (session) and
     * falls back to the default language — read-only, the session is written
     * by the switcher and by storeActiveListFilters().
     */
    public function rendering(): void
    {
        $this->loadListFilters();

        if (empty($this->listFilters['language'])) {
            $this->listFilters['language'] = SetupLanguage::selectedCode();
        }
    }
} ?>

<x-noerd::page>
    @if($collectionKey)
        <x-noerd::list />
    @else
        <div class="text-center py-8">
            <p class="text-gray-500">{{ __('Please select a collection') }}</p>
        </div>
    @endif
</x-noerd::page>
