<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Facades\Noerd;
use Noerd\Helpers\NoerdAuth;
use Noerd\Helpers\SetupCollectionHelper;
use Noerd\Helpers\StaticConfigHelper;
use Noerd\Models\SetupCollection;
use Noerd\Models\SetupCollectionEntry;
use Noerd\Support\SetupCollectionDefinitionData;
use Noerd\Traits\NoerdDetail;

new class extends Component
{
    use NoerdDetail;

    public ?string $detailPrimary = 'setupCollectionDefinitionId';

    public array $fields = [];

    public bool $isEditing = false;

    public array $originalFieldNames = [];

    /** True while the rename question is open — the next store() proceeds. */
    public bool $renameConfirmationPending = false;

    public array $pendingRenames = [];

    /**
     * Entry count for the delete confirmation. Computed (request-cached) —
     * the previous inline @php block ran two queries on EVERY render,
     * including every keystroke-triggered morph.
     */
    #[Computed]
    public function entryCount(): int
    {
        if (! $this->isEditing || ! $this->modelId) {
            return 0;
        }

        $collection = SetupCollection::where('tenant_id', NoerdAuth::user()->selected_tenant_id)
            ->where('collection_key', mb_strtoupper($this->modelId))
            ->first();

        return $collection ? $collection->entries()->count() : 0;
    }

    public function mount(): void
    {
        $this->initDetail();
        $this->pageLayout = StaticConfigHelper::getComponentFields('setup-collection-definition-detail');

        $repository = app(SetupCollectionDefinitionRepositoryContract::class);

        $this->detailData = [
            'filename' => '',
            'title' => '',
            'titleList' => '',
            'description' => '',
        ];

        if ($this->modelId) {
            $this->isEditing = true;

            $definition = $repository->find($this->modelId);

            if ($definition) {
                $this->detailData['filename'] = $definition->filename;
                $this->detailData['title'] = $definition->title;
                $this->detailData['titleList'] = $definition->titleList;
                $this->detailData['description'] = $definition->description ?? '';

                $this->fields = $definition->fields;
                foreach ($this->fields as $index => $field) {
                    $this->originalFieldNames[$index] = $field['name'];
                }
            }
        }
    }

    public function addField(): void
    {
        $this->fields[] = [
            'name' => '',
            'label' => '',
            'type' => 'text',
            'colspan' => 6,
        ];
    }

    public function removeField(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
    }

    public function store(): void
    {
        // Normalize filename: lowercase, strip .yml extension, replace dashes with underscores.
        $this->detailData['filename'] = mb_strtolower($this->detailData['filename']);
        $this->detailData['filename'] = preg_replace('/\.ya?ml$/i', '', $this->detailData['filename']);
        $this->detailData['filename'] = str_replace('-', '_', $this->detailData['filename']);

        $rules = [
            'detailData.filename' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
            'detailData.title' => ['required', 'string', 'max:255'],
            'detailData.titleList' => ['required', 'string', 'max:255'],
        ];

        foreach ($this->fields as $index => $field) {
            $rules["fields.{$index}.name"] = ['required', 'string', 'max:255'];
            $rules["fields.{$index}.label"] = ['required', 'string', 'max:255'];
            $rules["fields.{$index}.type"] = ['required', 'string'];
        }

        $this->validate($rules);

        $repository = app(SetupCollectionDefinitionRepositoryContract::class);
        $filename = $this->detailData['filename'];

        // Prevent duplicate filenames (when creating or renaming)
        $isRenaming = $this->isEditing && $filename !== $this->modelId;
        if ((! $this->isEditing || $isRenaming) && $repository->exists($filename)) {
            $this->addError('detailData.filename', __('A collection with this filename already exists.'));

            return;
        }

        // Detect renamed fields
        $renames = [];
        if ($this->isEditing) {
            foreach ($this->originalFieldNames as $index => $oldName) {
                if (isset($this->fields[$index]) && $this->fields[$index]['name'] !== $oldName && $oldName !== '') {
                    $renames[$oldName] = $this->fields[$index]['name'];
                }
            }
        }

        // If there are renames and user hasn't confirmed yet, ask
        if ($renames && ! $this->renameConfirmationPending) {
            $this->pendingRenames = $renames;
            $this->renameConfirmationPending = true;
            Noerd::modal('noerd::setup-collection-rename-modal', ['renames' => $renames]);

            return;
        }

        $key = mb_strtoupper($filename);
        $data = new SetupCollectionDefinitionData(
            filename: $filename,
            key: $key,
            title: $this->detailData['title'],
            titleList: $this->detailData['titleList'],
            description: $this->detailData['description'] ?: null,
            fields: array_values($this->fields),
        );

        $repository->save(
            $data,
            originalFilename: $this->isEditing ? $this->modelId : null,
        );

        // Update setup_collections.collection_key on rename (per-tenant scope)
        if ($isRenaming) {
            SetupCollection::where('tenant_id', NoerdAuth::user()->selected_tenant_id)
                ->where('collection_key', mb_strtoupper($this->modelId))
                ->update(['collection_key' => $key]);
        }

        // Ensure the SetupCollection instance bucket exists so the dynamic
        // sidebar entry lists the correct name.
        SetupCollection::firstOrCreate([
            'tenant_id' => NoerdAuth::user()->selected_tenant_id,
            'collection_key' => $key,
        ], [
            'name' => $this->detailData['titleList'],
        ]);

        $this->isEditing = true;
        $this->modelId = $filename;

        $this->dispatch('refreshList-noerd::setup-collection-definitions-list');
        $this->showSuccessIndicator = true;
    }

    #[On('collectionRenameConfirmed')]
    public function renameConfirmed(bool $apply): void
    {
        $apply ? $this->confirmRenameAndSave() : $this->skipRenameAndSave();
    }

    public function confirmRenameAndSave(): void
    {
        $this->renameFieldsInDatabase();
        $this->renameConfirmationPending = false;
        $this->syncOriginalFieldNames();
        $this->store();
    }

    public function skipRenameAndSave(): void
    {
        $this->pendingRenames = [];
        $this->renameConfirmationPending = false;
        $this->syncOriginalFieldNames();
        $this->store();
    }

    private function syncOriginalFieldNames(): void
    {
        $this->originalFieldNames = [];
        foreach ($this->fields as $index => $field) {
            $this->originalFieldNames[$index] = $field['name'];
        }
    }

    private function renameFieldsInDatabase(): void
    {
        $collectionKey = mb_strtoupper($this->modelId);
        $collection = SetupCollection::where('tenant_id', NoerdAuth::user()->selected_tenant_id)
            ->where('collection_key', $collectionKey)
            ->first();

        if (! $collection) {
            return;
        }

        $entries = SetupCollectionEntry::where('setup_collection_id', $collection->id)
            ->whereNotNull('data')
            ->get();

        foreach ($entries as $entry) {
            $data = is_array($entry->data) ? $entry->data : [];
            $changed = false;

            foreach ($this->pendingRenames as $oldKey => $newKey) {
                if (array_key_exists($oldKey, $data) && ! array_key_exists($newKey, $data)) {
                    $data[$newKey] = $data[$oldKey];
                    unset($data[$oldKey]);
                    $changed = true;
                }
            }

            if ($changed) {
                $entry->data = $data;
                $entry->saveQuietly();
            }
        }

        $this->pendingRenames = [];
    }

    public function copy(): void
    {
        if (! $this->modelId) {
            return;
        }

        $repository = app(SetupCollectionDefinitionRepositoryContract::class);

        try {
            $newFilename = $repository->copy($this->modelId);
        } catch (RuntimeException) {
            $this->addError('detailData.filename', __('A collection with this filename already exists.'));

            return;
        }

        // Mirror the copied definition into the setup_collections instance
        // table so it shows up in the sidebar with the correct name.
        $newDefinition = $repository->find($newFilename);
        if ($newDefinition) {
            SetupCollection::firstOrCreate([
                'tenant_id' => NoerdAuth::user()->selected_tenant_id,
                'collection_key' => $newDefinition->key,
            ], [
                'name' => $newDefinition->titleList,
            ]);
        }

        $this->dispatch('refreshList-noerd::setup-collection-definitions-list');
        $this->closeModalProcess('noerd::setup-collection-definitions-list');
    }

    public function delete(): void
    {
        if (! $this->modelId) {
            return;
        }

        $repository = app(SetupCollectionDefinitionRepositoryContract::class);

        // Remove the instance bucket + its entries (FK cascade wipes entries).
        $collectionKey = mb_strtoupper($this->modelId);
        $collection = SetupCollection::where('tenant_id', NoerdAuth::user()->selected_tenant_id)
            ->where('collection_key', $collectionKey)
            ->first();

        if ($collection) {
            SetupCollectionEntry::where('setup_collection_id', $collection->id)->delete();
            $collection->delete();
        }

        $repository->delete($this->modelId);

        $this->closeModalProcess('noerd::setup-collection-definitions-list');
    }
} ?>

<x-noerd::page>
    <x-slot:header>
        <x-noerd::modal-title>
            {{ __('Collection Definition') }}
        </x-noerd::modal-title>
    </x-slot:header>

    <x-noerd::tab-content :layout="$pageLayout" />

    <div class="px-6 py-4">
        <div class="text-sm font-medium text-gray-700 mb-3">{{ __('Fields') }}</div>

        @if(count($fields) === 0)
            <p class="text-sm text-gray-500 italic">{{ __('No fields defined yet.') }}</p>
        @else
            @php
                $thClass = 'border-b border-gray-300 bg-brand-navi/75 py-3.5 pr-3 pl-2 text-left text-sm font-semibold text-gray-900 backdrop-blur-sm backdrop-filter';
            @endphp
            <table class="min-w-full border-separate border-spacing-0">
                <thead>
                    <tr>
                        <th class="border-r first:pl-6 {{ $thClass }}">
                            {{ __('Field name') }}
                        </th>
                        <th class="border-r {{ $thClass }}">
                            {{ __('Field label') }}
                        </th>
                        <th class="border-r {{ $thClass }}" style="width: 200px;">
                            {{ __('Field type') }}
                        </th>
                        <th class="border-r {{ $thClass }}" style="width: 80px;">
                            {{ __('Colspan') }}
                        </th>
                        <th class="last:border-r-0 {{ $thClass }}" style="width: 50px;">
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fields as $index => $field)
                        <tr wire:key="field-{{ $index }}" class="group hover:bg-brand-bg border border-black/10">
                            <td class="py-1 first:pl-4 border-gray-300 border-r border-b">
                                <input type="text" wire:model="fields.{{ $index }}.name"
                                       placeholder="{{ __('Field name') }}"
                                       class="border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! p-0 bg-transparent w-full text-sm py-0.5 px-1.5">
                                @error("fields.{$index}.name") <span class="text-red-500 text-xs px-1.5">{{ $message }}</span> @enderror
                            </td>
                            <td class="py-1 border-gray-300 border-r border-b">
                                <input type="text" wire:model="fields.{{ $index }}.label"
                                       placeholder="{{ __('Field label') }}"
                                       class="border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! p-0 bg-transparent w-full text-sm py-0.5 px-1.5">
                                @error("fields.{$index}.label") <span class="text-red-500 text-xs px-1.5">{{ $message }}</span> @enderror
                            </td>
                            <td class="py-1 border-gray-300 border-r border-b">
                                <select wire:model="fields.{{ $index }}.type"
                                        class="border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! p-0 bg-transparent w-full text-sm py-0.5 px-1.5">
                                    @foreach (SetupCollectionHelper::FIELD_TYPES as $typeValue => $typeLabel)
                                        <option value="{{ $typeValue }}">{{ __($typeLabel) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-1 border-gray-300 border-r border-b">
                                <select wire:model="fields.{{ $index }}.colspan"
                                        class="border-transparent! ring-0! border-1! focus:ring-0! focus:border-1! p-0 bg-transparent w-full text-sm py-0.5 px-1.5">
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="6">6</option>
                                    <option value="12">12</option>
                                </select>
                            </td>
                            <td class="py-1 last:border-r-0 border-gray-300 border-b text-center">
                                <x-noerd::button variant="icon"
                                                 size="sm"
                                                 icon="x-mark"
                                                 wire:click="removeField({{ $index }})"/>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <x-noerd::button variant="secondary"
                         icon="plus"
                         wire:click="addField"
                         class="mt-3">
            {{ __('Add field') }}
        </x-noerd::button>
    </div>

    <x-slot:footer>
        <div class="flex items-center w-full gap-2">
            @if($isEditing)
                <div class="flex gap-2 mr-auto">
                    <x-noerd::button variant="secondary" wire:click="copy" wire:confirm="{{ __('Copy collection?') }}">
                        {{ __('Copy') }}
                    </x-noerd::button>
                </div>
            @endif
            <x-noerd::delete-save-bar :showDelete="$isEditing" :deleteMessage="__('Really delete this collection and all :count entries?', ['count' => $this->entryCount])" />
        </div>
    </x-slot:footer>
</x-noerd::page>
