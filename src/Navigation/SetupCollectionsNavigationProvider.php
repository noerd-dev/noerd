<?php

namespace Noerd\Navigation;

use Noerd\Contracts\DynamicNavigationProviderContract;
use Noerd\Contracts\SetupCollectionDefinitionRepositoryContract;
use Noerd\Support\SetupCollectionDefinitionData;

class SetupCollectionsNavigationProvider implements DynamicNavigationProviderContract
{
    public function __construct(
        protected readonly SetupCollectionDefinitionRepositoryContract $repository,
    ) {}

    public function type(): string
    {
        return 'setup-collections';
    }

    /**
     * @return array<int, array{title: string, link: string, heroicon: string}>
     */
    public function items(): array
    {
        return $this->repository->all()
            ->map(fn(SetupCollectionDefinitionData $d) => [
                'title' => $d->titleList,
                'link' => "/setup-collections?key={$d->filename}",
                'heroicon' => 'archive-box',
            ])
            ->values()
            ->toArray();
    }
}
