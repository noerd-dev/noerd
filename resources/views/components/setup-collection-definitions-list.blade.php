<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Support\SetupCollectionDefinitionData;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use NoerdList;

    public function mount(): void
    {
        $this->listId = Str::random();
        $this->loadListFilters();
    }

    public function listAction(mixed $modelId = null, array $relations = []): void
    {
        $this->dispatch(
            event: 'noerdModal',
            modalComponent: 'noerd::setup-collection-definition-detail',
            source: $this->getComponentName(),
            arguments: ['modelId' => $modelId, 'relations' => $relations],
        );
    }

    public function with(): array
    {
        $repository = app(SetupCollectionDefinitionRepositoryContract::class);

        $collectionMeta = DB::table('setup_collections')
            ->leftJoin('setup_collection_entries', 'setup_collection_entries.setup_collection_id', '=', 'setup_collections.id')
            ->where('setup_collections.tenant_id', auth()->user()->selected_tenant_id)
            ->select(
                'setup_collections.collection_key',
                DB::raw('count(setup_collection_entries.id) as entry_count'),
            )
            ->groupBy('setup_collections.collection_key')
            ->get()
            ->keyBy('collection_key');

        $entryCounts = $collectionMeta->pluck('entry_count', 'collection_key')->toArray();

        $items = [];
        foreach ($repository->all() as $definition) {
            /** @var SetupCollectionDefinitionData $definition */
            $item = [
                'id' => $definition->filename,
                'titleList' => $definition->titleList,
                'key' => $definition->key,
                'fieldCount' => count($definition->fields),
                'entryCount' => (int) ($entryCounts[$definition->key] ?? 0),
            ];

            if (! empty($this->search)) {
                $searchLower = mb_strtolower($this->search);
                $matchesSearch = mb_strpos(mb_strtolower($item['titleList']), $searchLower) !== false
                    || mb_strpos(mb_strtolower($item['key']), $searchLower) !== false
                    || mb_strpos(mb_strtolower($definition->filename), $searchLower) !== false;

                if (! $matchesSearch) {
                    continue;
                }
            }

            $items[] = $item;
        }

        usort($items, fn ($a, $b) => strcasecmp($a['titleList'], $b['titleList']));

        $page = $this->getPage();
        $perPage = $this->perPage;
        $collection = collect($items);
        $rows = new LengthAwarePaginator(
            $collection->forPage($page, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
        );

        return [
            'listConfig' => $this->buildList($rows, [
                'title' => 'Collection Definitions',
                'actions' => [['label' => 'New Collection Definition', 'action' => 'listAction']],
                'disableSearch' => false,
                'columns' => [
                    ['field' => 'titleList', 'label' => __('Title (plural)')],
                    ['field' => 'key', 'label' => 'Key'],
                    ['field' => 'fieldCount', 'label' => __('Fields')],
                    ['field' => 'entryCount', 'label' => __('Entries')],
                ],
            ]),
        ];
    }
} ?>

<x-noerd::page :disableModal="$disableModal">
    <x-noerd::list />
</x-noerd::page>
