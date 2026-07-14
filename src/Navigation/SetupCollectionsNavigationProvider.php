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
     * The route takes its key from the query string rather than a route param, so
     * route() is given the key as an extra param and appends it as a query string.
     * Going through route() keeps this off the hardcoded-path list.
     *
     * @return array<int, array{title: string, link: string, heroicon: string}>
     */
    public function items(): array
    {
        return $this->repository->all()
            ->map(fn(SetupCollectionDefinitionData $d) => [
                'title' => $d->titleList,
                'link' => route('setup-collections', ['key' => $d->filename], absolute: false),
                'heroicon' => 'archive-box',
            ])
            ->values()
            ->toArray();
    }
}
