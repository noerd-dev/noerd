<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Models\SetupCollection;
use Noerd\Support\SetupCollectionDefinitionData;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use NoerdList;

    public ?string $detailRoute = 'noerd.setup-collection-definition.detail';

    public $detailComponent = 'noerd::setup-collection-definition-detail';

    /**
     * The rows are the collection definitions of the repository, not a model —
     * searched, sorted and paginated here.
     */
    public function listData(): array
    {
        $repository = app(SetupCollectionDefinitionRepositoryContract::class);

        // The tenant's buckets with their entry counts — the model is tenant-scoped.
        $entryCounts = SetupCollection::query()
            ->withCount('entries')
            ->pluck('entries_count', 'collection_key')
            ->all();

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

        return $this->buildList($rows, [
            'title' => __('Collection Definitions'),
            'actions' => [['label' => __('New Collection Definition'), 'action' => 'listAction']],
            'disableSearch' => false,
            'columns' => [
                ['field' => 'titleList', 'label' => __('Title (plural)')],
                ['field' => 'key', 'label' => __('Key')],
                ['field' => 'fieldCount', 'label' => __('Fields')],
                ['field' => 'entryCount', 'label' => __('Entries')],
            ],
        ]);
    }
} ?>

<x-noerd::page>
    <x-noerd::list />
</x-noerd::page>
