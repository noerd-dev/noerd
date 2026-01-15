<?php

use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Noerd\Noerd\Helpers\SetupCollectionHelper;
use Noerd\Noerd\Models\SetupCollection;
use Noerd\Noerd\Models\SetupCollectionEntry;
use Noerd\Noerd\Models\SetupLanguage;
use Noerd\Noerd\Traits\Noerd;
use Noerd\Noerd\Traits\SetupLanguageFilterTrait;

new class extends Component
{
    use Noerd;
    use SetupLanguageFilterTrait;

    public const COMPONENT = 'setup-collections-list';

    protected const ALLOWED_TABLE_FILTERS = ['language'];

    public string|int|null $collectionKey = null;

    public ?array $collectionLayout = null;

    #[Computed]
    public function tableFilters(): array
    {
        if (! $this->hasMultipleLanguages()) {
            return [];
        }

        return [$this->getLanguageFilter()];
    }

    public function storeActiveTableFilters(): void
    {
        session(['activeTableFilters' => $this->activeTableFilters]);

        if (! empty($this->activeTableFilters['language'])) {
            session(['selectedLanguage' => $this->activeTableFilters['language']]);
        }
    }

    public function mount(): void
    {
        // Ensure default languages exist
        SetupLanguage::ensureDefaultLanguages();

        if (! $this->collectionKey) {
            $this->collectionKey = request()->get('key');
        }

        // Load collection layout
        $this->collectionLayout = SetupCollectionHelper::getCollectionFields($this->collectionKey);

        if (request()->create) {
            $this->tableAction();
        }
    }

    public function tableAction(mixed $modelId = null, mixed $relationId = null): void
    {
        $this->dispatch(
            event: 'noerdModal',
            component: 'setup-collection-detail',
            source: self::COMPONENT,
            arguments: ['setupCollectionId' => $modelId, 'collectionKey' => $this->collectionKey, 'relationId' => $relationId],
        );
    }

    public function with(): array
    {
        if (! $this->collectionKey) {
            return [
                'rows' => collect([]),
                'tableConfig' => [
                    'title' => __('noerd_nav_collections'),
                    'newLabel' => __('noerd_label_new_entry'),
                    'disableSearch' => false,
                    'columns' => [],
                ],
            ];
        }

        // Get or create the parent collection
        $parentCollection = SetupCollection::firstOrCreate([
            'tenant_id' => auth()->user()->selected_tenant_id,
            'collection_key' => mb_strtoupper($this->collectionKey),
        ], [
            'name' => ucfirst($this->collectionKey),
        ]);

        // Get collection entries
        $query = SetupCollectionEntry::where('tenant_id', auth()->user()->selected_tenant_id)
            ->where('setup_collection_id', $parentCollection->id)
            ->orderBy('sort', 'asc')
            ->orderBy('created_at', 'desc');

        // Apply search if provided
        if (! empty($this->search)) {
            $query->where(function ($q): void {
                if ($this->collectionLayout && isset($this->collectionLayout['fields'])) {
                    foreach ($this->collectionLayout['fields'] as $field) {
                        $fieldName = $field['name'] ?? '';
                        $fieldKey = str_replace('model.', '', $fieldName);

                        // Skip image fields for search
                        if (($field['type'] ?? '') === 'image') {
                            continue;
                        }

                        // Search in translatable fields
                        $q->orWhereRaw("JSON_EXTRACT(data, \"$.{$fieldKey}.de\") LIKE ?", ['%'.$this->search.'%'])
                            ->orWhereRaw("JSON_EXTRACT(data, \"$.{$fieldKey}.en\") LIKE ?", ['%'.$this->search.'%'])
                            ->orWhereRaw("JSON_EXTRACT(data, \"$.{$fieldKey}\") LIKE ?", ['%'.$this->search.'%']);
                    }
                }
            });
        }

        $rows = $query->paginate(self::PAGINATION);

        $selectedLanguage = $this->activeTableFilters['language']
            ?? session('selectedLanguage')
            ?? $this->getDefaultLanguageCode();

        // Transform data for display
        $rows->getCollection()->transform(function ($entry) use ($selectedLanguage) {
            $data = is_array($entry->data) ? $entry->data : [];
            $transformedData = [
                'id' => $entry->id,
                'sort' => $entry->sort ?? 0,
                'updated_at' => $entry->updated_at->format('d.m.Y H:i'),
            ];

            // Add dynamic fields from YAML configuration
            if ($this->collectionLayout && isset($this->collectionLayout['fields'])) {
                foreach ($this->collectionLayout['fields'] as $field) {
                    $fieldName = $field['name'] ?? '';
                    $fieldKey = str_replace('model.', '', $fieldName);

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
                        $value = '✓ '.__('noerd_label_image_present');
                    }

                    $transformedData[$fieldKey] = $value ?: '-';
                }
            }

            return $transformedData;
        });

        $collectionTitle = $this->collectionLayout['titleList'] ?? ucfirst($this->collectionKey);
        $newLabel = $this->collectionLayout['buttonList'] ?? __('noerd_label_new_entry');

        // Generate dynamic columns from YAML fields
        $columns = [];
        if ($this->collectionLayout && isset($this->collectionLayout['fields'])) {
            foreach ($this->collectionLayout['fields'] as $field) {
                $fieldName = $field['name'] ?? '';
                $fieldKey = str_replace('model.', '', $fieldName);
                $label = $field['label'] ?? ucfirst($fieldKey);

                // Calculate width based on field type
                $width = match ($field['type'] ?? 'text') {
                    'image' => 15,
                    'translatableText' => 25,
                    'translatableTextarea' => 30,
                    default => 20,
                };

                $columns[] = [
                    'field' => $fieldKey,
                    'label' => $label,
                    'width' => $width,
                ];
            }
        }

        // Add standard columns
        $columns[] = ['field' => 'sort', 'label' => __('noerd_label_sort'), 'width' => 10];
        $columns[] = ['field' => 'updated_at', 'label' => __('noerd_label_last_modified'), 'width' => 15];

        return [
            'rows' => $rows,
            'tableConfig' => [
                'title' => $collectionTitle,
                'newLabel' => $newLabel,
                'disableSearch' => false,
                'columns' => $columns,
            ],
        ];
    }

    public function rendering(): void
    {
        $this->loadActiveTableFilters();

        $selectedLanguage = session('selectedLanguage');
        if ($selectedLanguage && empty($this->activeTableFilters['language'])) {
            $this->activeTableFilters['language'] = $selectedLanguage;
        }

        if (empty($this->activeTableFilters['language']) && empty(session('selectedLanguage'))) {
            $defaultCode = $this->getDefaultLanguageCode();
            $this->activeTableFilters['language'] = $defaultCode;
            session(['selectedLanguage' => $defaultCode]);
        }
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    @if($collectionKey)
        @include('noerd::components.table.table-build', [
            'rows' => $rows,
            'tableConfig' => $tableConfig,
            'component' => self::COMPONENT
        ])
    @else
        <div class="text-center py-8">
            <p class="text-gray-500">{{ __('noerd_please_select_collection') }}</p>
        </div>
    @endif
</x-noerd::page>
